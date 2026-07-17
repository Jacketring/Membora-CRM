# Membora CRM PHP

Aplicacion PHP monolitica para ejecutar Membora CRM en `/app/` dentro del mismo dominio que la web publica, sin Next.js, NestJS ni procesos Node en produccion.

La web comercial vive en `httpdocs/` y expone el CRM mediante `httpdocs/app/index.php`, manteniendo el codigo privado en `apps/crm`.

## Metodología y validación

El CRM se desarrolla mediante incrementos trazables desde requisitos e historias hasta especificaciones, pruebas, implementación, integración continua y despliegue. La metodología completa está en `../../docs/19-metodologia-desarrollo.md` y el plan de verificación en `../../docs/05-pruebas.md`.

Estado verificado el 17/07/2026: **54 tests y 251 aserciones** de PHPUnit, además de PHPStan sin errores.

## Requisitos

- PHP 8.2 o superior.
- Extension PDO MySQL activada.
- MariaDB/MySQL existente.
- Apache con `mod_rewrite` activado.
- Document root unico apuntando a `httpdocs`.

## Configuracion

Crear `apps/crm/.env` a partir de `.env.example`.

`.env.example` es la referencia completa para sesion, correo, datos fiscales, Stripe y Sentry. `MEMBORA_APP_PATH`, `MEMBORA_PUBLIC_ORIGIN` y `APP_WEB_URL` son variables opcionales de infraestructura descritas en `../../docs/06-api-backend.md`.

Configuracion recomendada:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
APP_URL="https://membora.es/app"
WEB_APP_URL="https://membora.es,https://www.membora.es"
APP_STRICT_POST_ORIGIN="false"
DB_HOST="localhost"
DB_PORT="3306"
DB_DATABASE="membora_crm"
DB_USERNAME="usuario"
DB_PASSWORD="password"
MAIL_ENABLED="true"
MAIL_MAILER="smtp"
MAIL_FROM_EMAIL="no-reply@josehurtado.dev"
MAIL_FROM_NAME="Membora CRM"
MAIL_REPLY_TO="contacto@josehurtado.dev"
SMTP_HOST="mail.josehurtado.dev"
SMTP_PORT="587"
SMTP_ENCRYPTION="tls"
SMTP_USERNAME="no-reply@josehurtado.dev"
SMTP_PASSWORD="password_de_la_cuenta_de_correo"
```

Tambien se admite:

```env
DATABASE_URL="mysql://usuario:password@localhost:3306/membora_crm"
```

## Despliegue en Plesk

1. Subir o actualizar el repositorio desde GitHub.
2. Configurar el dominio `membora.es` para que el document root apunte a:

```text
httpdocs
```

3. Crear `apps/crm/.env` en el servidor con la conexion real a MariaDB.
4. Verificar que PHP usa una version 8.2 o superior.
5. Abrir `https://membora.es/app/`.

No hace falta ejecutar `npm install`, `npm run build`, `prisma generate` ni reiniciar una app Node para esta version PHP.

## Pantallas incluidas

- Login.
- Login demo automatico con usuario temporal unico y caducidad de 20 minutos.
- Alta publica de prueba durante 14 dias con verificacion de email y creacion automatica de tenant y administrador.
- Cookie de sesion exclusiva mediante `SESSION_COOKIE_NAME`, evitando colisiones con otras aplicaciones PHP del dominio.
- Dashboard del gimnasio.
- Leads.
- Socios.
- Membresias.
- Pagos de socios y facturas imprimibles.
- Facturacion externa generica.
- Check-ins, alertas y auditoria.
- Clases y calendario.
- Tareas.
- Usuarios internos.
- Perfil.
- Configuracion visual.
- Novedades.
- Panel de administracion SaaS con resumen, contactos, empresas, usuarios, pagos, facturas, planes y auditoria; la herramienta de web/correo queda oculta del menu y accesible solo por URL directa para diagnostico.

## Administracion SaaS

La app crea y usa tablas de administracion SaaS para controlar clientes, empresas, cobros y catalogo comercial:

- Cliente comercial previo a la contratacion.
- Empresa cliente.
- Plan.
- Estado del CRM.
- Estado de pago.
- Precio mensual.
- Proximo pago para planes de pago.
- Dias de prueba configurables cuando el plan de la empresa es `Prueba`.
- Notas internas.
- Acceso de soporte al CRM de la empresa si tiene `tenant_id`.
- Creacion de tenant y usuario administrador al pasar de cliente a empresa.
- Pagos SaaS por empresa: concepto, importe, vencimiento, fecha de pago y estado.
- Facturas SaaS y de clientes: lineas, impuestos, emision, pagos asociados y vista imprimible.
- Usuarios de plataforma separados de los usuarios de gimnasio.
- Planes SaaS: codigo, precio mensual, coste de alta, limites y prestaciones.
- Contactos: tabla unificada de solicitudes web y clientes comerciales, con edicion, conversion, alta manual, eliminacion de leads/clientes y reparacion de vinculos ausentes con empresas.
- Empresas: alta, edicion, ciclo de renovacion, acceso de soporte y eliminacion controlada de datos operativos de pruebas antiguas.
- Web/correo: herramienta tecnica oculta del menu, disponible solo por URL directa para superadministradores.

Usuario de administracion de plataforma:

```text
Email: admin@membora.crm
Password: definida mediante `PLATFORM_ADMIN_PASSWORD` en `.env` durante el despliegue.
```

## Automatismos de base de datos

La aplicacion crea algunas tablas o columnas auxiliares si no existen, por ejemplo:

- `empresas`.
- `platform_leads`.
- `platform_clients`.
- `empresa_payments`.
- `saas_plans`.
- `platform_invoices`.
- `platform_invoice_items`.
- `platform_invoice_payments`.
- `lead_notes`.
- `webhook_settings`.
- `webhook_logs`.
- `task_members`.
- `membership_plans`.
- `subscriptions`.
- `class_types`.
- `class_sessions`.
- `reservations`.
- `payments`.
- `billing_integrations`.
- `billing_sync_logs`.
- `checkins`.
- `risk_alerts`.
- `audit_logs`.
- `demo_users` y `demo_resets`.
- `login_attempts` y `auth_tokens`.
- `trial_registrations`.
- `trial_credential_deliveries` para la entrega cifrada y de una sola visualizacion de la contrasena inicial.
- `stripe_events`.
- columnas de imagen para usuarios/socios.

Esto permite desplegar cambios incrementales en Plesk sin ejecutar migraciones Node.

La integracion Stripe esta habilitada exclusivamente con `PAYMENTS_MODE=stripe_test` y claves `sk_test_`. La activacion Live queda fuera del estado cerrado actual; consulta `../../docs/16-stripe-billing-saas.md`.

El estado tecnico de Stripe, sus identificadores y la URL del webhook no se muestran en Facturas CRM ni en el formulario de suscripcion. La integracion y el endpoint `/stripe/webhook` siguen activos internamente; se ocultan para no mezclar diagnostico tecnico con la gestion diaria.

## Web comercial

La captacion web se revisa desde el panel de administradores de Membora CRM, no desde cada gimnasio cliente.

Los enlaces de demo de la web publica envian un `POST` al login demo del CRM. La demo cliente publica no depende del nombre exacto de `APP_ENV`, mientras que la demo de administrador solo se habilita con `APP_ENV=demo`. Cada acceso crea un usuario temporal con credenciales aleatorias, dura 20 minutos, muestra un contador y elimina ese usuario al cerrar sesion, caducar o cerrar la pestana. Al terminar devuelve al usuario a `WEB_APP_URL`.

El formulario `Empieza gratis` envia a `/app/api/trial`. El servidor verifica origen y honeypot y envia un enlace de activacion de una hora. El rate limit especifico de esta prueba esta desactivado por defecto mientras se depura el alta; se reactiva con `TRIAL_RATE_LIMIT_ENABLED=true`. Tras confirmar el email crea o actualiza un contacto `Cliente CRM` con el nombre de la empresa y el correo real, vincula ese contacto a la empresa, crea el tenant `TRIAL` de 14 dias y crea automaticamente su usuario administrador. Antes de completar el alta comprueba de nuevo que el usuario esta activo y comparte el `tenant_id` de la empresa. La contrasena inicial se genera aleatoriamente y se entrega mediante un segundo correo que contiene un enlace temporal, no la contrasena: la credencial permanece cifrada con una clave derivada de `APP_KEY` (o `DB_PASSWORD` como compatibilidad), requiere una confirmacion explicita para revelarse y queda consumida inmediatamente para que no pueda verse de nuevo.

El formulario de `httpdocs` envia al webhook:

```text
/app/webhook/lead
```

El webhook acepta `POST` con JSON, `application/x-www-form-urlencoded` o `multipart/form-data`.
No es necesario copiar tokens en la web. El CRM valida el origen configurado en `WEB_APP_URL`, aplica honeypot y rate limit, y crea la solicitud en `Admin CRM > Contactos`. Desde esa seccion el administrador puede mantenerla como lead, actualizar su estado o convertirla en cliente.

La vista `Contactos` comprueba ademas la integridad entre empresas y clientes. Si encuentra una empresa con email cuyo `client_id` esta vacio o apunta a un registro inexistente, crea o recupera automaticamente el `Cliente CRM`, lo marca como cliente y repara el vinculo. Esto permite recuperar altas antiguas incompletas sin editar la base de datos.

Cuando la solicitud incluye un email valido, el CRM intenta enviar una confirmacion HTML al contacto. Para produccion se recomienda SMTP con `MAIL_MAILER`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME` y `SMTP_PASSWORD`. Si el envio falla, el lead se crea igualmente y el fallo queda registrado para diagnostico.

La ruta `index.php?route=platform-web` y su vista `platform-web.php` se crearon como herramienta interna durante la depuracion del flujo de correo. No forman parte de la interfaz funcional y se mantienen ocultas del menu. Un superadministrador puede abrir la ruta directamente cuando necesite usar `Prueba de correo`, revisar la configuracion detectada o consultar `Ultimos envios tecnicos`; nunca se muestran secretos SMTP completos.

## Seguridad

Las medidas de seguridad y la estrategia de captacion web quedan documentadas en:

```text
docs/09-seguridad-y-captacion-web.md
```

La app aplica sesiones endurecidas, cabeceras de seguridad, validacion de origen en POST internos, consultas preparadas, aislamiento por `tenant_id`, validacion de uploads, honeypot y rate limit en captacion web.

El flujo activo usa webhook HTTP. Tambien queda documentada una alternativa futura para insertar solicitudes directamente en base de datos desde la web PHP si se quiere simplificar el despliegue.
