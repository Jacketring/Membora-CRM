# Modelo de datos actual - Membora CRM

## Diagrama entidad-relación

```mermaid
erDiagram
    TENANTS ||--o{ USERS : contiene
    TENANTS ||--o{ LEADS : capta
    TENANTS ||--o{ MEMBERS : gestiona
    TENANTS ||--o{ MEMBERSHIP_PLANS : ofrece
    MEMBERS ||--o{ SUBSCRIPTIONS : contrata
    MEMBERSHIP_PLANS ||--o{ SUBSCRIPTIONS : define
    MEMBERS ||--o{ PAYMENTS : realiza
    MEMBERS ||--o{ CHECKINS : registra
    CLASS_SESSIONS ||--o{ RESERVATIONS : recibe
    MEMBERS ||--o{ RESERVATIONS : reserva
    TENANTS ||--o{ TASKS : organiza
    TENANTS ||--o{ AUDIT_LOGS : audita
    PLATFORM_LEADS |o--o| PLATFORM_CLIENTS : convierte
    PLATFORM_CLIENTS ||--o{ EMPRESAS : origina
    EMPRESAS ||--o{ EMPRESA_PAYMENTS : recibe
    EMPRESAS ||--o{ PLATFORM_INVOICES : factura
    PLATFORM_INVOICES ||--|{ PLATFORM_INVOICE_ITEMS : contiene
    PLATFORM_INVOICES ||--o{ PLATFORM_INVOICE_PAYMENTS : cobra
    USERS ||--o{ AUTH_TOKENS : autentica
    MEMBERS { string id PK string tenant_id FK string email string status }
    PAYMENTS { string id PK string tenant_id FK string member_id FK decimal amount string status }
```

Fecha de actualizacion: 16/07/2026.

## 1. Estado del documento

Este documento describe el modelo de datos usado por la version PHP actual. Sustituye al planteamiento inicial basado en Prisma/NestJS para reflejar la implementacion desplegable en Plesk.

La aplicacion usa una base de datos MariaDB compartida. Los datos operativos de cada gimnasio se aislan mediante `tenant_id`. La administracion SaaS de Membora CRM usa tablas globales de plataforma para leads web, clientes comerciales, empresas, planes y cobros.

## 2. Principios de datos

- Base de datos MariaDB unica.
- Identificadores de texto tipo CUID.
- Acceso a datos mediante PDO y consultas preparadas.
- Tablas operativas filtradas por `tenant_id`.
- Superadmin de plataforma sin `tenant_id` operativo propio.
- Creacion incremental de tablas/columnas auxiliares desde PHP cuando faltan.
- Sin migraciones Node, Prisma ni build en produccion.

## 3. Tablas operativas de gimnasio

Estas tablas pertenecen a un gimnasio concreto y deben consultarse siempre con `tenant_id`:

- `tenants`: gimnasio o centro cliente.
- `users`: usuarios internos del gimnasio y superadmin de plataforma.
- `roles`: catalogo global de roles.
- `pipeline_stages`: etapas comerciales.
- `leads`: leads del gimnasio.
- `lead_notes`: notas asociadas a leads.
- `members`: socios.
- `membership_plans`: planes de membresia.
- `subscriptions`: asignaciones de membresia a socios.
- `class_types`: tipos de clase.
- `class_sessions`: sesiones programadas.
- `reservations`: reservas de socios en clases.
- `tasks`: tareas comerciales u operativas.
- `task_members`: tabla historica de compatibilidad para tareas antiguas vinculadas a socios.
- `risk_alerts`: alertas generadas para priorizar riesgos operativos.
- `payments`: pagos manuales de socios, vencimientos y cobros.
- `billing_integrations`: configuracion de proveedor externo de facturacion por tenant.
- `billing_sync_logs`: registros de exportacion y sincronizacion de pagos.
- `checkins`: entradas/asistencias manuales de socios.
- `audit_logs`: registro de acciones internas con datos sanitizados.
- `demo_users`: usuarios temporales de demo, token de limpieza y fecha de caducidad.

Columnas auxiliares que PHP puede anadir:

- `users.avatar_path`.
- `members.photo_path`.
- `tenants.primary_color`.
- columnas de precio, periodicidad, duracion y estado en `membership_plans`.
- columnas de fecha y estado en `subscriptions`.
- columnas de descripcion, aforo, duracion y estado en `class_types`.
- columnas de tipo, instructor, inicio, fin, aforo y estado en `class_sessions`.

## 4. Tablas de administracion SaaS

Estas tablas son globales de Membora CRM y no representan datos internos de un gimnasio concreto:

- `platform_leads`: solicitudes recibidas desde la web publica.
- `platform_clients`: contactos comerciales antes de crear CRM.
- `empresas`: empresas cliente del SaaS, plan contratado, estado, pago y dias de prueba comercial.
- `empresa_payments`: cobros SaaS por empresa.
- `saas_plans`: catalogo comercial de planes SaaS.
- `platform_invoices`: cabeceras de facturas SaaS y facturas manuales emitidas a clientes.
- `platform_invoice_items`: lineas, cantidades, precios, impuestos y totales de cada factura.
- `platform_invoice_payments`: cobros parciales o totales asociados a facturas.
- `stripe_events`: eventos Stripe recibidos, resultado de proceso e idempotencia por `stripe_event_id`.
- `webhook_settings`: configuracion historica/tecnica de integraciones.
- `webhook_logs`: registros tecnicos de formularios, emails y diagnosticos.

Tablas transversales de autenticacion, demo y provisionamiento:

- `login_attempts`: intentos fallidos por IP y hash del email para limitar fuerza bruta.
- `auth_tokens`: tokens de un solo uso o rotatorios para recordar sesion y recuperar contrasena.
- `demo_resets`: control de reinicios periodicos de los datos demo.
- `trial_registrations`: solicitudes de prueba, token de verificacion, rate limit, estado y fecha de activacion.

## 5. Relaciones principales

- `Tenant` 1 -> N `User`, `Lead`, `Member`, `MembershipPlan`, `ClassType`, `ClassSession`, `Task`.
- `Role` 1 -> N `User`.
- `PipelineStage` 1 -> N `Lead`.
- `Lead` 1 -> N `LeadNote`.
- `Lead` 0..1 -> 1 `Member` a nivel funcional mediante `members.lead_id`.
- `Member` 1 -> N `Subscription`.
- `Member` 1 -> N `Payment`.
- `Member` 1 -> N `CheckIn`.
- `BillingIntegration` 1 -> N `BillingSyncLog`.
- `MembershipPlan` 1 -> N `Subscription`.
- `Subscription` 1 -> N `Payment`; la relacion es opcional desde cada pago.
- `ClassType` 1 -> N `ClassSession`.
- `ClassSession` 1 -> N `Reservation`.
- `ClassSession` 0..N -> N `CheckIn`.
- `Member` 1 -> N `Reservation`.
- `Task` N -> 1 `User` mediante `assigned_user_id` como responsable interno.
- `Task` puede conservar enlaces historicos con `Member` mediante `task_members`, pero la interfaz actual prioriza usuarios internos.
- `RiskAlert` 0..N -> `Member`, `Lead`, `Task`, `Payment` o `ClassSession`.
- `AuditLog` 0..N -> `User` y opcionalmente a una entidad funcional mediante `entity_type` y `entity_id`.
- `PlatformLead` 0..1 -> 1 `PlatformClient` al convertir una solicitud web.
- `PlatformClient` 0..N -> N `Empresa` como origen comercial.
- `Empresa` 0..1 -> 1 `Tenant` cuando el CRM esta creado.
- `Empresa` 1 -> N `EmpresaPayment`.
- `Empresa` 1 -> N `PlatformInvoice`.
- `PlatformInvoice` 1 -> N `PlatformInvoiceItem` y `PlatformInvoicePayment`.
- `SaasPlan` 1 -> N `Empresa` cuando se asigna un plan comercial.
- `Empresa.trial_days` define la duracion de la prueba cuando `plan` esta en `TRIAL`.
- `User` 1 -> N `AuthToken`; los tokens se guardan mediante selector y verificador derivado, no en claro.
- `StripeEvent` no pertenece a un tenant: registra eventos tecnicos globales y evita procesarlos dos veces.

## 6. Reglas de aislamiento

- El `tenant_id` se obtiene desde la sesion autenticada, no desde formularios libres.
- Un usuario de gimnasio solo opera sobre su `tenant_id`.
- El superadmin puede entrar en modo soporte sobre una empresa conectada; durante ese modo la sesion fija el tenant objetivo.
- Las consultas de listados, ediciones y eliminaciones de gimnasio incluyen `tenant_id`.
- Las tablas globales de plataforma se protegen por rol de superadmin, no por `tenant_id`.
- Las rutas y acciones POST internas se validan contra una matriz de permisos por rol antes de ejecutarse.

## 7. Estados relevantes

Leads de gimnasio:

```text
OPEN, CONVERTED, LOST
```

Socios:

```text
ACTIVE, INACTIVE, AT_RISK, CANCELLED, PAYMENT_PENDING
```

Membresias y suscripciones:

```text
ACTIVE, INACTIVE, EXPIRED, CANCELLED
```

Clases:

```text
SCHEDULED, CANCELLED, COMPLETED
```

Reservas:

```text
reserved, cancelled, attended, no_show
```

Check-ins:

```text
MANUAL, QR
```

Tareas:

```text
PENDING, COMPLETED, CANCELLED
```

Alertas:

```text
OPEN, RESOLVED, DISMISSED
```

Leads web de plataforma:

```text
NEW, CONTACTED, QUALIFIED, CONVERTED, LOST
```

Clientes comerciales:

```text
LEAD, QUALIFIED, CUSTOMER, LOST
```

Empresas SaaS:

```text
ACTIVE, TRIAL, SUSPENDED, CANCELLED
```

Pagos SaaS:

```text
PAID, PENDING, OVERDUE, CANCELLED
```

Pagos de gimnasio:

```text
PAID, PENDING, OVERDUE, CANCELLED
```

Facturacion externa:

```text
ACTIVE, INACTIVE, PENDING, EXPORTED, SYNCED, ERROR, SUCCESS
```

## 8. Automatismos actuales

La aplicacion PHP puede crear o adaptar tablas auxiliares al cargar repositorios concretos. Esto permite desplegar cambios en Plesk con un `git pull` y sin ejecutar migraciones Node.

Automatismos principales:

- Crea `platform_leads`, `platform_clients`, `empresas`, `empresa_payments` y `saas_plans`.
- Crea `platform_invoices`, `platform_invoice_items` y `platform_invoice_payments` para facturacion SaaS y cobros asociados.
- Crea `lead_notes`, `task_members`, `membership_plans`, `subscriptions`, `class_types`, `class_sessions` y `reservations`.
- Crea `checkins` para entradas manuales y asistencias asociadas a reservas.
- Crea `risk_alerts` para pagos vencidos, tareas vencidas, membresias caducadas o proximas a renovar, leads sin seguimiento, socios sin actividad y clases llenas.
- Crea `audit_logs` para registrar acciones POST internas, usuario, ruta, IP, navegador y datos sin contrasenas ni tokens.
- Crea `billing_integrations` y `billing_sync_logs` para configurar proveedor externo, exportar pagos y registrar sincronizaciones.
- Anade columnas de sincronizacion externa a `payments`.
- Anade `empresas.trial_days` para pruebas comerciales configurables.
- Crea `webhook_settings` y `webhook_logs` para integraciones y diagnostico.
- Crea `trial_registrations` para altas self-service verificadas por email antes de provisionar el tenant.
- Crea `login_attempts`, `auth_tokens` y `demo_resets` para autenticacion, recuperacion y mantenimiento de la demo.
- Crea `stripe_events` y columnas Stripe en empresas, planes, pagos y facturas cuando se inicializa la integracion de prueba.
- Anade columnas auxiliares de imagen, color, planes, clases y suscripciones si faltan.

Requisito operativo:

- El usuario MariaDB usado por `apps/crm/.env` debe tener permisos para `CREATE TABLE` y `ALTER TABLE` durante actualizaciones incrementales.

## 9. Fuera del alcance actual

La integracion Stripe Billing funciona en modo `stripe_test`, con checkout alojado, webhooks firmados, suscripciones e idempotencia. Quedan fuera del alcance cerrado:

- Activacion de Stripe Live con claves de produccion y cuenta bancaria validada.
- Validacion fiscal/comercial definitiva antes de cobrar a clientes reales.
- Pasarela de pagos de socios dentro de cada gimnasio; los pagos de socios siguen siendo registros manuales.
