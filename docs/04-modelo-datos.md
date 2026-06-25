# Modelo de datos inicial - Membora CRM

> Nota de estado actual: este documento describe el modelo inicial planteado para el MVP. La version PHP actual reutiliza MariaDB y crea algunas tablas auxiliares de forma incremental, como `platform_clients`, `empresas`, `empresa_payments`, `saas_plans`, `lead_notes`, `task_members`, `membership_plans`, `subscriptions`, `class_types` y `class_sessions`. El estado actualizado esta resumido en `docs/07-estado-actual-php.md`.

## 1. Introducción

Este documento define el modelo relacional inicial de Membora CRM antes de crear el `schema.prisma`.

El objetivo es dejar cerradas las entidades principales, sus relaciones y las reglas de aislamiento de datos necesarias para un CRM SaaS web responsive orientado a gimnasios y centros fitness pequeños o medianos.

El modelo cubre el MVP descrito en el alcance del proyecto:

- Login y roles básicos.
- Modelo multiempresa con `tenant_id`.
- Gestión de leads y pipeline comercial.
- Conversión de lead a socio.
- Gestión de socios, membresías y pagos manuales.
- Clases, reservas y check-ins.
- Tareas comerciales y de retención.
- Alertas básicas.
- Dashboard con KPIs.
- Datos demo para NexoFit Studio.

Este documento no contiene código ejecutable ni define todavía el `schema.prisma`. Sirve como base técnica para diseñar posteriormente las tablas, migraciones, relaciones e índices en Prisma y MariaDB.

## 2. Decisión de arquitectura de datos SaaS multiempresa

Membora CRM usará una arquitectura SaaS multiempresa con base de datos compartida y separación lógica de datos mediante `tenant_id`.

Decisión adoptada:

- Una única base de datos MariaDB para todos los gimnasios.
- Una misma estructura de tablas compartida por todos los tenants.
- Cada gimnasio se representa mediante una entidad `Tenant`.
- Las entidades de negocio principales incluyen `tenant_id`.
- El backend debe filtrar siempre por `tenant_id` en las operaciones de lectura, escritura y actualización de datos de negocio.

Esta decisión es adecuada para el MVP porque:

- Reduce complejidad operativa frente a una base de datos por cliente.
- Encaja bien con Prisma y MariaDB usando el conector `mysql`.
- Permite demostrar arquitectura SaaS real sin introducir infraestructura avanzada.
- Facilita crear datos demo para un tenant principal, NexoFit Studio.

Alternativas descartadas para el MVP:

- Base de datos independiente por gimnasio: aporta más aislamiento, pero complica despliegue, migraciones y operación.
- Esquema independiente por gimnasio: no encaja de forma sencilla con Prisma y añade complejidad innecesaria.
- Multi-sede avanzada: queda fuera del alcance del MVP.

## 3. Uso de tenant_id para aislar datos entre gimnasios

`tenant_id` será la clave de aislamiento lógico entre gimnasios.

Regla general:

- Toda entidad que pertenezca a un gimnasio concreto debe incluir `tenant_id`.
- Toda consulta autenticada debe derivar el `tenant_id` desde el usuario autenticado o desde el contexto de seguridad del token JWT.
- El cliente frontend no debe decidir libremente el `tenant_id` de una operación sensible.
- El backend debe ignorar o validar cualquier `tenant_id` recibido desde el frontend.

Entidades que deben incluir `tenant_id`:

- `User`
- `PipelineStage`
- `Lead`
- `Member`
- `MembershipPlan`
- `Subscription`
- `Payment`
- `ClassType`
- `ClassSession`
- `Reservation`
- `CheckIn`
- `Task`
- `CommunicationLog`
- `RiskAlert`
- `AuditLog`

Entidad que no incluye `tenant_id` como campo de pertenencia:

- `Tenant`, porque representa al propio gimnasio o empresa.
- `Role`, porque será una tabla global con los roles disponibles en la plataforma.

Decisiones finales sobre usuarios y roles:

- `Role` será una tabla global sin `tenant_id`.
- Los roles disponibles serán `SUPERADMIN`, `GYM_ADMIN`, `SALES_RECEPTION` y `TRAINER`.
- `User.tenant_id` será opcional para permitir un superadmin global.
- El superadmin tendrá `tenant_id` null.
- Los usuarios normales de gimnasio deberán tener `tenant_id` obligatorio a nivel de lógica de backend.

## 4. Entidades principales

### 4.1 Tenant

Propósito:

Representa un gimnasio, centro fitness o estudio deportivo dentro de la plataforma SaaS.

Campos principales:

- `id`: identificador único.
- `name`: nombre comercial del gimnasio.
- `slug`: identificador legible y único para URLs internas o referencias.
- `email`: email principal del centro.
- `phone`: teléfono principal.
- `address`: dirección opcional.
- `status`: estado del tenant.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Tiene muchos `User`.
- Tiene muchos `Lead`.
- Tiene muchos `Member`.
- Tiene muchos `MembershipPlan`.
- Tiene muchas `ClassSession`, `Task`, `RiskAlert` y entidades operativas.

Reglas importantes:

- `slug` debe ser único.
- El tenant demo principal será NexoFit Studio.
- No se debe borrar físicamente un tenant si tiene datos asociados; se recomienda usar estados funcionales como inactivo o suspendido.

Debe incluir `tenant_id`:

- No. `Tenant` es la entidad raíz del aislamiento.

### 4.2 User

Propósito:

Representa a un usuario interno de un gimnasio o a un superadmin SaaS que puede iniciar sesión en el sistema.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio al que pertenece el usuario. Será opcional para permitir superadmin global.
- `role_id`: rol asignado.
- `name`: nombre completo.
- `email`: email de login.
- `password_hash`: contraseña cifrada.
- `status`: estado del usuario.
- `last_login_at`: último acceso.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece opcionalmente a `Tenant`.
- Pertenece a `Role`.
- Puede ser responsable de muchos `Lead`.
- Puede ser entrenador asignado a muchas `ClassSession`.
- Puede ser responsable de muchas `Task`.
- Puede aparecer como actor en `AuditLog`.

Reglas importantes:

- `email` debe ser único al menos dentro de un tenant.
- La contraseña nunca se guarda en texto plano.
- Un usuario debe tener un rol válido.
- Para el MVP se contemplan roles como superadmin SaaS, administrador, recepción/comercial y entrenador.
- El usuario con rol `SUPERADMIN` tendrá `tenant_id` null.
- Los usuarios con rol `GYM_ADMIN`, `SALES_RECEPTION` o `TRAINER` deberán tener `tenant_id` obligatorio mediante validación de backend.
- Un usuario normal de gimnasio no puede operar sobre datos de otro tenant.

Debe incluir `tenant_id`:

- Sí, pero como campo opcional. Será null únicamente para superadmin global.

### 4.3 Role

Propósito:

Define permisos o perfiles de uso dentro del sistema.

Campos principales:

- `id`: identificador único.
- `name`: nombre del rol.
- `key`: clave interna del rol.
- `description`: descripción opcional.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Tiene muchos `User`.

Reglas importantes:

- `key` debe ser único globalmente.
- Roles disponibles: `SUPERADMIN`, `GYM_ADMIN`, `SALES_RECEPTION`, `TRAINER`.
- En el MVP los permisos pueden resolverse por rol, sin una tabla avanzada de permisos.
- `Role` será una tabla global de catálogo, no configurable por tenant en el MVP.

Debe incluir `tenant_id`:

- No.

### 4.4 Lead

Propósito:

Representa a una persona interesada en el gimnasio antes de convertirse en socio.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del lead.
- `pipeline_stage_id`: etapa comercial actual.
- `assigned_user_id`: usuario responsable.
- `first_name`: nombre.
- `last_name`: apellidos.
- `email`: email.
- `phone`: teléfono.
- `source`: origen del lead.
- `interest`: interés principal.
- `status`: estado comercial.
- `lost_reason`: motivo de pérdida, si aplica.
- `next_action_at`: fecha prevista para la próxima acción.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Pertenece a `PipelineStage`.
- Puede estar asignado a un `User`.
- Puede estar relacionado con un `Member` mediante `Member.lead_id`.
- Puede tener muchas `Task`.
- Puede tener muchos `CommunicationLog`.
- Puede generar `RiskAlert` o alertas comerciales básicas.

Reglas importantes:

- Un lead convertido no debe convertirse dos veces.
- Al convertir un lead, se debe crear un `Member` con datos de contacto reutilizados.
- El lead convertido debe conservarse para trazabilidad comercial.
- La etapa debe pertenecer al mismo tenant que el lead.
- No se guardará `converted_member_id` en `Lead` para evitar una relación circular.
- La relación de conversión se resolverá con `Member.lead_id` opcional.
- A nivel funcional, un lead solo podrá convertirse en un socio, pero esa regla se validará en servicios NestJS.

Debe incluir `tenant_id`:

- Sí.

### 4.5 PipelineStage

Propósito:

Define las etapas del pipeline comercial fitness.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario de la etapa.
- `name`: nombre visible de la etapa.
- `key`: clave interna.
- `order`: orden de visualización.
- `is_final`: indica si es etapa final.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Tiene muchos `Lead`.

Reglas importantes:

- Las etapas demo recomendadas son: nuevo lead, contactado, visita o prueba agendada, prueba realizada, alta propuesta, convertido a socio y perdido.
- `order` debe ser único dentro de cada tenant.
- `key` debe ser único dentro de cada tenant.
- No se debe eliminar una etapa si tiene leads asociados; se recomienda desactivarla o impedir el borrado.

Debe incluir `tenant_id`:

- Sí.

### 4.6 Member

Propósito:

Representa a un socio gestionado por el CRM.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del socio.
- `lead_id`: lead de origen si el socio procede de una conversión. Será opcional y no tendrá restricción única en base de datos.
- `first_name`: nombre.
- `last_name`: apellidos.
- `email`: email.
- `phone`: teléfono.
- `birth_date`: fecha de nacimiento opcional.
- `status`: estado del socio.
- `joined_at`: fecha de alta.
- `cancelled_at`: fecha de baja, si aplica.
- `notes`: notas internas.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Puede proceder de un `Lead`.
- Tiene muchas `Subscription`.
- Tiene muchos `Payment`.
- Tiene muchas `Reservation`.
- Tiene muchos `CheckIn`.
- Tiene muchas `Task`.
- Tiene muchos `CommunicationLog`.
- Puede tener muchas `RiskAlert`.

Reglas importantes:

- Un socio puede existir sin lead previo si se da de alta directamente.
- Un lead solo puede originar un socio a nivel funcional.
- La unicidad de conversión se validará en el backend antes de crear el socio, no mediante una restricción `UNIQUE` sobre `Member.lead_id`.
- El email no debería repetirse dentro del mismo tenant si se usa como dato principal de contacto.
- La ficha 360º del socio se construirá agregando sus suscripciones, pagos, reservas, check-ins, tareas y alertas.
- El estado del socio puede derivarse parcialmente de pagos, membresía e inactividad, pero debe existir un estado persistido para simplificar el MVP.

Debe incluir `tenant_id`:

- Sí.

### 4.7 MembershipPlan

Propósito:

Define los planes comerciales o membresías que ofrece un gimnasio.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del plan.
- `name`: nombre del plan.
- `description`: descripción.
- `price`: precio estimado.
- `billing_period`: periodicidad.
- `duration_days`: duración orientativa en días.
- `is_active`: indica si el plan está disponible.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Tiene muchas `Subscription`.

Reglas importantes:

- Un plan inactivo no debe asignarse a nuevas suscripciones.
- No se debe borrar físicamente un plan con suscripciones históricas.
- El precio se usa para MRR y ARPU estimados, no para una facturación legal avanzada.

Debe incluir `tenant_id`:

- Sí.

### 4.8 Subscription

Propósito:

Representa la asignación de un plan de membresía a un socio.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario de la suscripción.
- `member_id`: socio asociado.
- `membership_plan_id`: plan asignado.
- `status`: estado de la suscripción.
- `start_date`: fecha de inicio.
- `end_date`: fecha de fin o renovación.
- `cancelled_at`: fecha de cancelación, si aplica.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Pertenece a `Member`.
- Pertenece a `MembershipPlan`.
- Puede tener muchos `Payment`.

Reglas importantes:

- Un socio puede tener varias suscripciones históricas.
- Para el MVP se recomienda permitir como máximo una suscripción activa por socio.
- La suscripción debe pertenecer al mismo tenant que el socio y el plan.
- Las membresías vencidas pueden generar alertas.

Debe incluir `tenant_id`:

- Sí.

### 4.9 Payment

Propósito:

Registra pagos manuales asociados a socios y, opcionalmente, a una suscripción.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del pago.
- `member_id`: socio asociado.
- `subscription_id`: suscripción asociada, si aplica.
- `amount`: importe.
- `currency`: moneda.
- `payment_method`: método de pago.
- `status`: estado del pago.
- `paid_at`: fecha de pago, si está pagado.
- `due_date`: fecha de vencimiento, si aplica.
- `notes`: notas internas.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Pertenece a `Member`.
- Puede pertenecer a `Subscription`.

Reglas importantes:

- No representa una pasarela real de pagos.
- Los pagos pendientes o vencidos pueden generar alertas.
- El importe debe guardarse como decimal, no como número flotante.
- El pago debe pertenecer al mismo tenant que el socio y la suscripción.

Debe incluir `tenant_id`:

- Sí.

### 4.10 ClassType

Propósito:

Define tipos de clases ofrecidas por el gimnasio, como HIIT, Yoga, Pilates o Fuerza.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del tipo de clase.
- `name`: nombre del tipo de clase.
- `description`: descripción.
- `default_duration_minutes`: duración por defecto.
- `is_active`: indica si se puede usar para nuevas sesiones.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Tiene muchas `ClassSession`.

Reglas importantes:

- No se debe borrar un tipo de clase con sesiones históricas.
- Un tipo inactivo no debe usarse para crear nuevas sesiones.

Debe incluir `tenant_id`:

- Sí.

### 4.11 ClassSession

Propósito:

Representa una sesión concreta de clase con fecha, hora, aforo y entrenador.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario de la sesión.
- `class_type_id`: tipo de clase.
- `trainer_user_id`: entrenador asignado.
- `starts_at`: fecha y hora de inicio.
- `ends_at`: fecha y hora de fin.
- `capacity`: aforo máximo.
- `status`: estado de la sesión.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Pertenece a `ClassType`.
- Puede tener un `User` entrenador.
- Tiene muchas `Reservation`.
- Puede tener muchos `CheckIn`.

Reglas importantes:

- El aforo debe ser mayor que cero.
- No se deben permitir reservas por encima del aforo.
- El entrenador debe pertenecer al mismo tenant y tener rol compatible.
- Una sesión cancelada no debe aceptar nuevas reservas.

Debe incluir `tenant_id`:

- Sí.

### 4.12 Reservation

Propósito:

Representa la reserva de un socio en una sesión de clase.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario de la reserva.
- `member_id`: socio que reserva.
- `class_session_id`: sesión reservada.
- `status`: estado de la reserva.
- `reserved_at`: fecha de creación de la reserva.
- `cancelled_at`: fecha de cancelación, si aplica.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Pertenece a `Member`.
- Pertenece a `ClassSession`.
- Puede tener un `CheckIn` asociado.

Reglas importantes:

- Un socio no debería tener dos reservas activas para la misma sesión.
- Las reservas duplicadas activas se validarán en el backend, no mediante una restricción única estricta en base de datos.
- Una reserva activa cuenta contra el aforo.
- Una reserva cancelada no cuenta contra el aforo.
- Puede marcarse como asistida o no-show.
- La reserva debe pertenecer al mismo tenant que el socio y la sesión.

Debe incluir `tenant_id`:

- Sí.

### 4.13 CheckIn

Propósito:

Registra la asistencia de un socio, ya sea manualmente desde recepción o mediante QR simple.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del check-in.
- `member_id`: socio que realiza el check-in.
- `class_session_id`: sesión asociada, si aplica.
- `reservation_id`: reserva asociada, si aplica.
- `method`: método de check-in.
- `checked_in_at`: fecha y hora del check-in.
- `created_by_user_id`: usuario que registró el check-in, si aplica.
- `created_at`: fecha de creación.

Relaciones:

- Pertenece a `Tenant`.
- Pertenece a `Member`.
- Puede pertenecer a `ClassSession`.
- Puede pertenecer a `Reservation`.
- Puede ser creado por un `User`.

Reglas importantes:

- Puede existir check-in sin reserva si se registra entrada general.
- Si existe reserva, debe pertenecer al mismo socio, sesión y tenant.
- Un check-in puede marcar una reserva como asistida.
- El QR será simple y orientado a demo, sin integración con hardware.

Debe incluir `tenant_id`:

- Sí.

### 4.14 Task

Propósito:

Representa tareas comerciales, operativas o de retención asociadas a leads, socios o usuarios internos.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario de la tarea.
- `assigned_user_id`: usuario responsable.
- `lead_id`: lead asociado, si aplica.
- `member_id`: socio asociado, si aplica.
- `title`: título.
- `description`: descripción.
- `type`: tipo de tarea.
- `status`: estado.
- `due_at`: fecha de vencimiento.
- `completed_at`: fecha de finalización, si aplica.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Puede pertenecer a un `Lead`.
- Puede pertenecer a un `Member`.
- Puede estar asignada a un `User`.

Reglas importantes:

- Una tarea puede estar asociada a lead o socio, pero no es obligatorio que tenga ambos.
- Las tareas vencidas pueden generar alertas.
- La tarea debe pertenecer al mismo tenant que sus entidades relacionadas.

Debe incluir `tenant_id`:

- Sí.

### 4.15 CommunicationLog

Propósito:

Registra interacciones o notas de comunicación relacionadas con leads o socios.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario del registro.
- `lead_id`: lead asociado, si aplica.
- `member_id`: socio asociado, si aplica.
- `user_id`: usuario que registró la comunicación.
- `channel`: canal de comunicación.
- `direction`: dirección de la comunicación.
- `summary`: resumen.
- `occurred_at`: fecha de la comunicación.
- `created_at`: fecha de creación.

Relaciones:

- Pertenece a `Tenant`.
- Puede pertenecer a `Lead`.
- Puede pertenecer a `Member`.
- Pertenece a `User`.

Reglas importantes:

- Sirve para trazabilidad comercial y de retención.
- Debe tener al menos un lead o socio asociado.
- No sustituye a una integración real de email, WhatsApp o telefonía.

Debe incluir `tenant_id`:

- Sí.

### 4.16 RiskAlert

Propósito:

Representa alertas básicas generadas por reglas simples del sistema.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio propietario de la alerta.
- `member_id`: socio asociado, si aplica.
- `lead_id`: lead asociado, si aplica.
- `task_id`: tarea asociada, si aplica.
- `type`: tipo de alerta.
- `severity`: severidad.
- `status`: estado.
- `message`: mensaje resumido.
- `detected_at`: fecha de detección.
- `resolved_at`: fecha de resolución, si aplica.
- `created_at`: fecha de creación.
- `updated_at`: fecha de última actualización.

Relaciones:

- Pertenece a `Tenant`.
- Puede pertenecer a `Member`.
- Puede pertenecer a `Lead`.
- Puede pertenecer a `Task`.

Reglas importantes:

- Las alertas se basan en reglas simples, no en IA predictiva real.
- Ejemplos: pago pendiente, membresía vencida, socio inactivo, lead sin seguimiento, tarea vencida, clase con alta ocupación.
- Una alerta resuelta debe conservarse para histórico.

Debe incluir `tenant_id`:

- Sí.

### 4.17 AuditLog

Propósito:

Registra acciones relevantes para trazabilidad, seguridad y defensa técnica del proyecto.

Campos principales:

- `id`: identificador único.
- `tenant_id`: gimnasio relacionado con la acción, si aplica.
- `user_id`: usuario que ejecutó la acción.
- `action`: acción realizada.
- `entity_type`: tipo de entidad afectada.
- `entity_id`: identificador de la entidad afectada.
- `metadata`: datos adicionales serializados en formato JSON.
- `ip_address`: IP opcional.
- `user_agent`: agente de usuario opcional.
- `created_at`: fecha de creación.

Relaciones:

- Puede pertenecer a `Tenant`.
- Puede pertenecer a `User`.

Reglas importantes:

- Debe registrar acciones críticas: login, creación de usuarios, conversión de lead, cambios de pago, cancelaciones y cambios de estado relevantes.
- No debe guardar contraseñas ni datos sensibles innecesarios.
- Puede usar una relación lógica mediante `entity_type` y `entity_id`, sin claves foráneas directas a todas las tablas.

Debe incluir `tenant_id`:

- Sí para acciones de un gimnasio concreto. Puede ser opcional para acciones globales del superadmin SaaS.

## 5. Relaciones principales entre entidades

Relaciones de tenant:

- `Tenant` 1 -> N `User`
- `Tenant` 1 -> N `Lead`
- `Tenant` 1 -> N `Member`
- `Tenant` 1 -> N `MembershipPlan`
- `Tenant` 1 -> N `ClassType`
- `Tenant` 1 -> N entidades operativas

Relaciones de autenticación y roles:

- `Role` 1 -> N `User`
- `Role` es global y no pertenece a `Tenant`.
- `User` 1 -> N `Lead` como responsable comercial
- `User` 1 -> N `ClassSession` como entrenador
- `User` 1 -> N `Task` como responsable

Relaciones comerciales:

- `PipelineStage` 1 -> N `Lead`
- `Lead` 1 -> N `Member` a nivel técnico mediante `Member.lead_id`, limitado funcionalmente a 0..1 desde servicios NestJS.
- `Lead` 1 -> N `Task`
- `Lead` 1 -> N `CommunicationLog`

Relaciones de socios y membresías:

- `Member` 1 -> N `Subscription`
- `MembershipPlan` 1 -> N `Subscription`
- `Subscription` 1 -> N `Payment`
- `Member` 1 -> N `Payment`

Relaciones de clases y asistencia:

- `ClassType` 1 -> N `ClassSession`
- `ClassSession` 1 -> N `Reservation`
- `Member` 1 -> N `Reservation`
- `Reservation` 0..1 -> 1 `CheckIn`
- `Member` 1 -> N `CheckIn`
- `ClassSession` 1 -> N `CheckIn`

Relaciones de retención y trazabilidad:

- `Member` 1 -> N `Task`
- `Member` 1 -> N `CommunicationLog`
- `Member` 1 -> N `RiskAlert`
- `Lead` 1 -> N `RiskAlert`
- `Task` 1 -> N `RiskAlert`
- `User` 1 -> N `AuditLog`

## 6. Enumeraciones recomendadas

Enums recomendados para Prisma y MariaDB:

MariaDB se usará mediante `provider = "mysql"` en Prisma. Estos valores se definirán como enums de Prisma para mantener tipos cerrados en el cliente generado.

- `TenantStatus`: `ACTIVE`, `INACTIVE`, `SUSPENDED`
- `UserStatus`: `ACTIVE`, `INACTIVE`, `INVITED`
- `RoleKey`: `SUPERADMIN`, `GYM_ADMIN`, `SALES_RECEPTION`, `TRAINER`
- `LeadStatus`: `OPEN`, `CONVERTED`, `LOST`
- `LeadSource`: `WALK_IN`, `WEBSITE`, `PHONE`, `SOCIAL_MEDIA`, `REFERRAL`, `OTHER`
- `MemberStatus`: `ACTIVE`, `INACTIVE`, `AT_RISK`, `CANCELLED`, `PAYMENT_PENDING`
- `SubscriptionStatus`: `ACTIVE`, `PENDING`, `EXPIRED`, `CANCELLED`
- `BillingPeriod`: `MONTHLY`, `QUARTERLY`, `YEARLY`, `CUSTOM`
- `PaymentStatus`: `PAID`, `PENDING`, `OVERDUE`
- `PaymentMethod`: `CASH`, `CARD`, `TRANSFER`, `OTHER`
- `ClassSessionStatus`: `SCHEDULED`, `CANCELLED`, `COMPLETED`
- `ReservationStatus`: `RESERVED`, `CANCELLED`, `ATTENDED`, `NO_SHOW`
- `CheckInMethod`: `MANUAL`, `QR`
- `TaskType`: `SALES`, `RETENTION`, `PAYMENT`, `OPERATIONAL`, `OTHER`
- `TaskStatus`: `PENDING`, `COMPLETED`, `CANCELLED`
- `CommunicationChannel`: `PHONE`, `EMAIL`, `WHATSAPP`, `IN_PERSON`, `OTHER`
- `CommunicationDirection`: `INBOUND`, `OUTBOUND`, `INTERNAL_NOTE`
- `RiskAlertType`: `PAYMENT_PENDING`, `MEMBERSHIP_EXPIRED`, `INACTIVE_MEMBER`, `LEAD_WITHOUT_FOLLOW_UP`, `OVERDUE_TASK`, `HIGH_CLASS_OCCUPANCY`
- `RiskAlertSeverity`: `LOW`, `MEDIUM`, `HIGH`
- `RiskAlertStatus`: `OPEN`, `RESOLVED`, `DISMISSED`

## 7. Decisiones de diseño para MariaDB y Prisma

Identificadores:

- Usar identificadores `String` con CUID en Prisma para simplificar generación desde aplicación.
- Mantener nombres de campos consistentes y claros.
- Definir claves foráneas explícitas entre entidades principales.

Fechas:

- Usar `created_at` y `updated_at` en tablas principales.
- Usar campos específicos como `paid_at`, `due_date`, `checked_in_at`, `completed_at` o `cancelled_at` cuando representen eventos de negocio.

Importes:

- Guardar importes con tipo decimal.
- No usar números flotantes para dinero.
- Incluir `currency`, aunque el MVP use previsiblemente `EUR`.

Borrado:

- Evitar borrado físico en entidades con histórico relevante.
- No se aplicará `deleted_at` general en el MVP.
- Preferir estados funcionales como `INACTIVE`, `CANCELLED`, `EXPIRED` o `SUSPENDED`.
- Usar campos de evento como `cancelled_at`, `resolved_at` o `completed_at` solo cuando representen hechos concretos del negocio.

Índices recomendados:

- Índices por `tenant_id` en todas las entidades multiempresa.
- Índices compuestos frecuentes, por ejemplo:
  - `tenant_id + email` en `User`, `Lead` y `Member`.
  - `tenant_id + status` en `Lead`, `Member`, `Payment`, `Task` y `RiskAlert`.
  - `tenant_id + starts_at` en `ClassSession`.
  - `tenant_id + due_at` en `Task`.
  - `tenant_id + checked_in_at` en `CheckIn`.
- Restricciones únicas compuestas cuando corresponda:
  - `Tenant.slug`
  - `Role.key`
  - `PipelineStage.tenant_id + PipelineStage.key`
  - `PipelineStage.tenant_id + PipelineStage.order`
- No se definirá una restricción única estricta sobre `Member.lead_id`; la regla de un lead convertido como máximo en un socio se validará en el backend.
- No se definirá una restricción única estricta para reservas activas duplicadas; esa regla se validará en el backend porque depende del estado de la reserva.

Metadatos:

- `AuditLog.metadata` se almacenará como `Json` en Prisma.
- No usar metadatos serializados para información principal que deba filtrarse o relacionarse con frecuencia.

Prisma:

- Definir relaciones explícitas entre modelos.
- Usar enums de Prisma para estados y tipos cerrados.
- Mantener los nombres del dominio en inglés para modelos y campos técnicos, alineados con entidades como `Lead`, `Member`, `Payment` y `ClassSession`.
- Mapear a nombres de tabla si se decide usar snake_case en MariaDB.

## 8. Reglas de seguridad relacionadas con tenant_id

Reglas obligatorias:

- El `tenant_id` debe obtenerse desde el usuario autenticado o el token JWT.
- Ningún endpoint de tenant debe confiar ciegamente en un `tenant_id` enviado por el frontend.
- Todas las consultas de entidades multiempresa deben filtrar por `tenant_id`.
- Todas las escrituras deben asignar `tenant_id` desde el contexto autenticado.
- Todas las relaciones deben validarse dentro del mismo tenant antes de crear o actualizar datos.
- Los usuarios con rol `SUPERADMIN` tendrán `tenant_id` null.
- Los usuarios normales de gimnasio deberán tener `tenant_id` obligatorio a nivel de backend.
- Los roles son globales y no participan en el aislamiento por tenant.

Ejemplos de validación:

- No crear un `Lead` con un `pipeline_stage_id` de otro tenant.
- No crear una `Subscription` usando un `member_id` y un `membership_plan_id` de tenants diferentes.
- No crear una `Reservation` si el `member_id` y la `class_session_id` no pertenecen al mismo tenant.
- No asignar una `Task` a un usuario de otro tenant.
- No permitir que un entrenador consulte clases de otro gimnasio.

Reglas para superadmin:

- El superadmin SaaS puede requerir permisos globales para consultar tenants.
- El superadmin SaaS tendrá `tenant_id` null.
- Las acciones globales deben quedar auditadas.
- Incluso para superadmin, las operaciones sobre datos de negocio deben indicar explícitamente el tenant objetivo.

Reglas de auditoría:

- Registrar acciones críticas en `AuditLog`.
- No guardar contraseñas, tokens JWT ni secretos en logs.
- Evitar exponer datos de otros tenants en errores o respuestas.

## 9. Datos demo previstos para NexoFit Studio

Tenant demo:

- `Tenant`: NexoFit Studio.
- `slug`: `nexofit-studio`.
- Estado: activo.

Usuarios demo:

- Administrador: `admin@nexofit.demo`.
- Recepción / comercial: `recepcion@nexofit.demo`.
- Entrenador: `entrenador@nexofit.demo`.

Roles demo:

- `SUPERADMIN`: superadmin SaaS global.
- `GYM_ADMIN`: administrador del gimnasio.
- `SALES_RECEPTION`: recepción / comercial.
- `TRAINER`: entrenador.

Pipeline demo:

1. Nuevo lead.
2. Contactado.
3. Visita o prueba agendada.
4. Prueba realizada.
5. Alta propuesta.
6. Convertido a socio.
7. Perdido.

Planes demo:

- Básico.
- Premium.
- Clases ilimitadas.
- Bono mensual.
- Entrenamiento funcional.

Leads demo:

- Leads repartidos entre distintas etapas del pipeline.
- Algunos leads con próxima acción pendiente.
- Algunos leads perdidos con motivo.
- Al menos un lead convertido a socio.

Socios demo:

- Socios activos.
- Socios inactivos.
- Socios en riesgo.
- Socios con pago pendiente.
- Socios con membresía vencida.

Pagos demo:

- Pagos pagados.
- Pagos pendientes.
- Pagos vencidos.
- Métodos: efectivo, tarjeta, transferencia y otro.

Clases demo:

- Funcional.
- HIIT.
- Yoga.
- Pilates.
- Cycling.
- Fuerza.

Reservas y check-ins demo:

- Sesiones con reservas confirmadas.
- Sesiones con aforo parcial y alto.
- Reservas canceladas.
- Reservas asistidas.
- No-shows.
- Check-ins manuales.
- Check-ins mediante QR simple.

Tareas y alertas demo:

- Tareas de llamada a lead.
- Tareas de seguimiento post-alta.
- Tareas de contacto a socio inactivo.
- Alertas por pago pendiente.
- Alertas por membresía vencida.
- Alertas por inactividad.
- Alertas por tarea vencida.

## 10. Decisiones finales para crear el schema.prisma

El siguiente paso técnico, después de validar este documento, será traducir estas decisiones a un `schema.prisma` inicial dentro del backend.

Decisiones cerradas:

- `Role` será una tabla global sin `tenant_id`.
- Los roles disponibles serán `SUPERADMIN`, `GYM_ADMIN`, `SALES_RECEPTION` y `TRAINER`.
- `User.tenant_id` será opcional para permitir superadmin global.
- El superadmin tendrá `tenant_id` null.
- Los usuarios normales de gimnasio deberán tener `tenant_id` obligatorio a nivel de lógica de backend.
- La relación `Lead` -> `Member` se resolverá guardando `lead_id` opcional en `Member`.
- `Member.lead_id` no tendrá restricción única en base de datos; la regla 1 -> 0..1 se validará en servicios NestJS.
- No se usará `converted_member_id` en `Lead`.
- Las reservas duplicadas activas se validarán en el backend, no mediante una restricción única estricta en base de datos.
- Se usarán CUIDs como identificadores.
- No se aplicará `deleted_at` general en el MVP; se usarán estados funcionales como `INACTIVE`, `CANCELLED`, `EXPIRED` o `SUSPENDED`.
