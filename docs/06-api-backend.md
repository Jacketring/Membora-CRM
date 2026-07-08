# Backend PHP, rutas y acciones - Membora CRM

Fecha de actualizacion: 30/06/2026.

## 1. Estado actual

La version actual no usa una API NestJS/JWT separada. El backend activo es una aplicacion PHP monolitica con entrada unica en:

```text
php-app/public/index.php
```

La navegacion se resuelve con el parametro `route` y las acciones de escritura se envian por `POST` con un campo `action`. La sesion PHP identifica al usuario autenticado y fija el contexto de gimnasio mediante `tenant_id`.

## 2. Rutas de pantalla

Rutas publicas o especiales:

- `?route=login`: formulario de login.
- `/webhook/lead`: endpoint publico para solicitudes desde la web comercial.
- `?action=webhook_lead`: alias compatible del webhook.

Rutas de gimnasio:

- `?route=dashboard`: panel principal.
- `?route=leads`: gestion de leads.
- `?route=members`: socios.
- `?route=memberships`: planes y suscripciones.
- `?route=payments`: pagos manuales de socios.
- `?route=billing`: configuracion y logs de facturacion externa generica.
- `?route=billing-export`: descarga CSV de pagos preparados para facturacion.
- `?route=checkins`: entradas y asistencias de socios.
- `?route=alerts`: alertas de riesgo operativas.
- `?route=audit`: auditoria de acciones internas.
- `?route=classes`: tipos de clase, calendario, sesiones y reservas.
- `?route=tasks`: tareas.
- `?route=users`: usuarios internos.
- `?route=profile`: perfil del usuario.
- `?route=settings`: configuracion visual.
- `?route=global-search&q=...`: buscador global en JSON.

Rutas de administracion SaaS:

- `?route=platform-dashboard`: resumen de Admin CRM.
- `?route=platform-contacts`: contactos unificados con leads web y clientes comerciales.
- `?route=platform-leads` y `?route=platform-clients`: rutas antiguas redirigidas a `platform-contacts`.
- `?route=platform-companies`: empresas cliente.
- `?route=platform-payments`: cobros SaaS.
- `?route=platform-plans`: catalogo de planes SaaS.
- `?route=platform-web`: estado tecnico de la web, webhook y correo.
- `?route=platform-audit`: logs de actividad de empresas cliente y plataforma.

## 3. Acciones POST principales

Autenticacion y perfil:

- `login`
- `logout`
- `update_profile`

Administracion SaaS:

- `update_platform_lead`
- `convert_platform_lead`
- `delete_platform_lead`
- `send_platform_test_email`
- `create_platform_client`
- `update_platform_client`
- `create_empresa`
- `update_empresa`
- `create_platform_payment`
- `update_platform_payment`
- `create_platform_plan`
- `update_platform_plan`
- `enter_empresa_crm`
- `exit_empresa_crm`

Gimnasio:

- `create_user`
- `update_user`
- `create_lead`
- `update_lead`
- `add_lead_note`
- `update_lead_note`
- `delete_lead_note`
- `update_lead_stage`
- `convert_lead`
- `mark_lead_lost`
- `delete_lead`
- `create_member`
- `update_member`
- `delete_member`
- `create_membership_plan`
- `update_membership_plan`
- `delete_membership_plan`
- `create_payment`
- `update_payment`
- `delete_payment`
- `create_checkin`
- `delete_checkin`
- `update_risk_alert_status`
- `save_billing_integration`
- `sync_billing_integration`
- `create_class_type`
- `create_class_session`
- `update_class_session`
- `delete_class_session`
- `create_reservation`
- `update_reservation_status`
- `create_task`
- `update_task`
- `update_task_status`
- `delete_task`

## 4. Webhook publico

Endpoint:

```text
POST /webhook/lead
```

Formatos aceptados:

- `application/json`
- `application/x-www-form-urlencoded`
- `multipart/form-data`

Comportamiento:

- Valida origen contra `WEB_APP_URL` cuando el navegador envia `Origin`.
- Acepta `OPTIONS` para CORS.
- Aplica validaciones anti-abuso en el repositorio de integracion.
- Crea o actualiza una entrada en `platform_leads`.
- Intenta enviar email HTML de confirmacion si hay email valido y SMTP configurado.
- Registra resultados tecnicos en `webhook_logs`.

Respuesta JSON:

```json
{
  "success": true,
  "message": "Solicitud recibida correctamente"
}
```

## 5. Seguridad de backend

- Sesiones PHP con cookies `HttpOnly`, `SameSite=Lax` y `Secure` si hay HTTPS.
- Regeneracion de ID de sesion tras login.
- Validacion de `Origin`/`Referer` en POST internos con `APP_URL`.
- `APP_STRICT_POST_ORIGIN` permite endurecer el bloqueo de POST sin origen.
- Consultas preparadas PDO.
- Escape de salida con `e($value)` en vistas.
- Validacion de uploads de imagen por tamano y MIME real.
- Aislamiento de datos operativos por `tenant_id`.
- Acceso a `Admin CRM` restringido a superadmin de plataforma.

## 6. Configuracion relacionada

Variables principales:

```env
APP_URL="https://app.crm.josehurtado.dev"
WEB_APP_URL="https://app.web.josehurtado.dev"
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
SMTP_PASSWORD="password"
```

Tambien se admite `DATABASE_URL` para la conexion MariaDB.

## 7. Pruebas tecnicas recomendadas

Antes de desplegar cambios:

```bash
php -l php-app/public/index.php
php -l php-app/src/Actions.php
php -l php-app/src/Repositories.php
php -l php-app/src/Auth.php
php -l php-app/src/Support.php
node --check php-app/public/assets/app.js
node --check web-app/public/assets/site.js
git diff --check
```

Pruebas manuales clave:

- Login de administrador de gimnasio.
- Login de superadmin.
- Demo temporal desde la web publica con contador de 20 minutos.
- Creacion y conversion de lead.
- Creacion/edicion de socio con foto.
- Asignacion de membresia.
- Registro y edicion de pagos de socios.
- Registro y eliminacion de check-ins.
- Generacion, resolucion y descarte de alertas de riesgo.
- Creacion de clase desde calendario.
- Reserva, asistencia, no-show y cancelacion.
- Creacion de tarea interna asignada a un usuario responsable.
- Formulario de la web publica hacia `/webhook/lead`.
- Prueba de correo desde `Admin CRM > Web`.
