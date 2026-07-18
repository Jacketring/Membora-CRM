# Backend PHP, rutas y acciones - Membora CRM

Fecha de actualizacion: 18/07/2026.

## 1. Arquitectura activa

El backend es una aplicacion PHP monolitica con entrada unica en `apps/crm/public/index.php`. En produccion, `httpdocs/app/index.php` actua como puente seguro desde `/app/`, sirve solo recursos permitidos y mantiene el codigo fuera del document root.

La navegacion usa el parametro `route`; las escrituras internas usan `POST` con un campo `action`. La sesion PHP identifica al usuario, fija el `tenant_id` y aplica la matriz de permisos antes de renderizar una ruta o ejecutar una accion.

## 2. Endpoints HTTP publicos y especiales

| Metodo | Ruta | Finalidad |
|---|---|---|
| `GET` | `/api/plans` | Devuelve en JSON los planes SaaS publicos. Alias: `?action=public_plans`. |
| `POST` | `/api/trial` | Solicita una prueba de 14 dias con verificacion de email. Alias: `?action=trial_registration`. |
| `POST` | `/webhook/lead` | Recibe captacion web por origen permitido o integraciones de tenant mediante token. Alias: `?action=webhook_lead`. |
| `POST` | `/stripe/webhook` | Recibe eventos Stripe y valida obligatoriamente `Stripe-Signature`. Alias: `?action=stripe_webhook`. |
| `GET` | `/stripe/checkout/success` o `?route=stripe-checkout-success` | Retorno autenticado; recupera la sesion en Stripe y puede reconciliar acceso y cobro pagados como respaldo del webhook. |
| `GET` | `/stripe/checkout/cancel` | Retorno de checkout cancelado sin activar acceso ni registrar cobro. |

La web publica dispone de proxies en `httpdocs/api/plans.php`, `trial.php` y `lead.php`. Estos proxies conservan el mismo origen publico, aplican tiempos limite y trasladan una respuesta JSON generica al navegador.

## 3. Rutas de pantalla y datos

Rutas publicas o de autenticacion:

- `?route=login`
- `?route=forgot-password`
- `?route=reset-password&token=...`
- `?route=activate-trial&token=...`
- `?route=trial-credentials&token=...`
- `?route=demo-expired`

Rutas autenticadas de gimnasio:

- `dashboard`, `leads`, `tasks`, `users`, `profile`, `settings` y `novedades`.
- `upgrade-plan`: dias restantes y catalogo de planes de pago. Todos los roles del tenant pueden consultar; solo `GYM_ADMIN` puede abrir el proveedor de checkout configurado.
- `simulated-checkout`: formulario interno exclusivo de `GYM_ADMIN`, con tarjeta ficticia y sin conexion bancaria.
- `members`, `memberships`, `payments`, `payment-invoice` y `client-invoice`.
- `billing`, `checkins`, `alerts`, `audit` y `classes`.
- `global-search`: buscador/autocompletado JSON limitado al tenant actual.
- `billing-export`: alias historico que redirige al modulo de facturacion externa.

Rutas de administracion SaaS:

- `platform-dashboard`, `platform-contacts` y `platform-companies`.
- `platform-users`, `platform-payments` y `platform-payment-invoice`.
- `platform-invoices`, `platform-invoice`, `platform-plans` y `platform-web`.
- `platform-audit`.
- `platform-leads` y `platform-clients`: alias antiguos redirigidos a `platform-contacts`.

La autorizacion efectiva se define en `route_permissions()` y `action_permissions()` de `apps/crm/src/Support.php`. Una ruta listada aqui no implica acceso para todos los roles.

## 4. Acciones POST

Autenticacion, sesion y perfil:

- `login`, `demo_login`, `keep_demo_session`, `schedule_demo_cleanup` y `logout`.
- `request_password_reset`, `reset_password`, `confirm_trial_activation`, `reveal_trial_credentials` y `update_profile`.

Administracion SaaS, contactos y empresas:

- `update_platform_lead`, `convert_platform_lead`, `delete_platform_lead`, `send_platform_test_email` y `reset_platform_trial_attempts`.
- `create_platform_client`, `update_platform_client` y `delete_platform_client`.
- `create_empresa`, `update_empresa`, `delete_empresa` y `update_empresa_subscription`.
- `renew_empresa_subscription`, `cancel_empresa_subscription` y `resume_empresa_subscription`.
- `create_empresa_stripe_checkout` y `cancel_empresa_stripe_subscription`.
- `create_tenant_stripe_checkout`: inicia Checkout para el tenant autenticado sin aceptar un `empresa_id` del navegador.
- `enter_empresa_crm` y `exit_empresa_crm`.

Usuarios de plataforma y gimnasio:

- `create_platform_user`, `update_platform_user` y `delete_platform_user`.
- `create_user` y `update_user`.

Cobros, facturas y planes SaaS:

- `create_platform_payment` y `update_platform_payment`.
- `create_platform_invoice`, `update_platform_invoice`, `issue_platform_invoice` y `add_platform_invoice_payment`.
- `create_client_invoice`, `update_client_invoice`, `issue_client_invoice` y `add_client_invoice_payment`.
- `create_platform_plan` y `update_platform_plan`.

Leads, socios y membresias:

- `create_lead`, `update_lead`, `update_lead_stage`, `convert_lead`, `mark_lead_lost` y `delete_lead`.
- `add_lead_note`, `update_lead_note` y `delete_lead_note`.
- `create_member`, `update_member`, `delete_member` y `renew_member_subscription`.
- `create_membership_plan`, `update_membership_plan` y `delete_membership_plan`.

Pagos y operacion del gimnasio:

- `create_payment`, `update_payment`, `mark_payment_paid`, `generate_recurring_payments` y `delete_payment`.
- `create_checkin`, `delete_checkin` y `update_risk_alert_status`.
- `save_billing_integration` y `sync_billing_integration`.
- `create_class_type`, `create_class_session`, `update_class_session` y `delete_class_session`.
- `create_reservation` y `update_reservation_status`.
- `create_task`, `update_task`, `update_task_status` y `delete_task`.

Todas las acciones pasan por seguridad de origen, CSRF salvo la excepcion controlada de demo, permisos por rol, bloqueo de suscripcion y auditoria sanitizada.

## 5. Contratos publicos resumidos

### Planes

`GET /api/plans` responde con `success`, moneda `EUR` y los cuatro planes comerciales activos (`Basic`, `Pro`, `Business` y `Enterprise`). Cada elemento incluye `monthly_price`, `max_users`, `max_members` y `features`. La web publica consume este endpoint mediante `httpdocs/api/plans.php` y solo usa su catalogo de fallback si fallan el proxy y `/app/api/plans`.

### Prueba self-service

`POST /api/trial` acepta nombre, empresa, email, consentimiento y honeypot. Valida el origen contra `WEB_APP_URL` y envia un enlace de activacion valido durante una hora. El limite adicional por IP y email esta desactivado por defecto durante la depuracion y se habilita con `TRIAL_RATE_LIMIT_ENABLED=true`. Solo tras una confirmacion `POST` crea o actualiza el contacto como `Cliente CRM`, vincula una empresa `TRIAL`, crea tenant y administrador y comprueba que ambos comparten `tenant_id`. La solicitud guarda `client_id`, `empresa_id`, `tenant_id` y `user_id` después de cada fase: si el proceso se interrumpe, continúa desde esas referencias sin duplicar ni eliminar la cuenta. El envío de credenciales es una fase posterior; un fallo SMTP conserva la cuenta en `EMAIL_FAILED` y permite reintentar con el mismo enlace de activación. La contraseña inicial aleatoria se cifra y se entrega mediante un segundo enlace: requiere confirmación, caduca en una hora y se consume al revelarse. Si el correo ya estaba ocupado, se busca un identificador de cuenta disponible sin duplicar el correo de entrega comercial.

### Captacion web

`POST /webhook/lead` acepta JSON, formulario URL-encoded y multipart. Sin token exige un origen publico permitido, honeypot vacio y rate limit; crea o actualiza `platform_leads` y envia confirmacion cuando SMTP esta disponible. Con token busca la configuracion del tenant y crea o actualiza el lead operativo correspondiente.

### Stripe

`POST /stripe/webhook` funciona cuando `PAYMENTS_MODE=stripe_test`, verifica la firma, registra `stripe_events` para idempotencia y sincroniza suscripciones, cobros y facturas. Es la via principal de confirmacion. La URL de retorno no confia en datos del navegador: recupera la Checkout Session con la clave secreta, comprueba la empresa autenticada y puede ejecutar la misma sincronizacion idempotente si Stripe confirma que la factura esta pagada.

`POST action=create_tenant_stripe_checkout` acepta `plan_code` y `renewal_period`, pero obtiene la empresa exclusivamente desde el `tenant_id` de la sesion. Se ofrece desde `TRIAL` o desde un plan activo inferior que todavia no tenga una suscripcion Stripe vinculada; admite planes activos de pago y Price IDs configurados, rechaza descensos y evita crear una segunda suscripcion. Guarda la eleccion como pendiente, envia al administrador del gimnasio a Stripe Checkout y requiere `STRIPE_WEBHOOK_SECRET`. `invoice.paid` o la reconciliacion autenticada de la sesion pagada activan el plan, actualizan el acceso y crean pago y factura de forma idempotente; Membora no recibe ni almacena los datos de tarjeta.

`POST action=open_tenant_simulated_checkout` prepara la pantalla interna y `POST action=complete_tenant_simulated_checkout` valida exclusivamente la tarjeta ficticia documentada. La empresa siempre se obtiene de la sesion; el servidor recalcula plan, importe y periodo, exige que el destino sea superior al plan actual y crea pago, justificante y acceso dentro de una transaccion. La jerarquia es `TRIAL < BASIC < PRO < BUSINESS < ENTERPRISE`; los campos `card_*` se descartan y se censuran en auditoria.

## 6. Seguridad de backend

- Sesion estricta con cookie propia, `HttpOnly`, `SameSite=Lax` y `Secure` bajo HTTPS.
- Tokens CSRF en formularios y validacion de `Origin`/`Referer` en POST internos.
- Rate limit de login y captacion; el del alta de prueba es configurable mediante `TRIAL_RATE_LIMIT_ENABLED` y esta desactivado por defecto durante su depuracion.
- Tokens de recuerdo y recuperacion con selector/verificador, caducidad y uso unico cuando corresponde.
- Consultas preparadas PDO, escape de salida y validacion MIME/tamano de uploads.
- Aislamiento por `tenant_id`, permisos por ruta/accion y modo soporte explicito.
- Auditoria que elimina contrasenas, tokens y secretos de los metadatos.
- Firma Stripe e idempotencia de eventos externos.

## 7. Configuracion relacionada

La referencia completa es `apps/crm/.env.example`. Grupos principales:

- Aplicacion: `APP_NAME`, `APP_ENV`, `APP_URL`, `WEB_APP_URL`, `APP_STRICT_POST_ORIGIN`, `SESSION_COOKIE_NAME` y `APP_KEY`.
- Base de datos: `DATABASE_URL` o `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`.
- Administracion/demo: `PLATFORM_ADMIN_PASSWORD`, `DEMO_CLIENT_PASSWORD` y `TRIAL_RATE_LIMIT_ENABLED`.
- Correo: `MAIL_*` y `SMTP_*`.
- Facturas: `INVOICE_ISSUER_*`.
- Pagos SaaS: `PAYMENTS_MODE`, `CHECKOUT_PROVIDER`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY` y `STRIPE_WEBHOOK_SECRET`.
- Observabilidad: `SENTRY_DSN` y `SENTRY_ENVIRONMENT`.

Variables opcionales de infraestructura:

- `MEMBORA_APP_PATH`: cambia el prefijo `/app` usado por el puente y los helpers.
- `MEMBORA_PUBLIC_ORIGIN`: fija el origen que usan los proxies publicos si la deteccion por host no es suficiente.
- `APP_WEB_URL`: alias compatible adicional para origenes de la web; la configuracion principal es `WEB_APP_URL`.

## 8. Verificacion tecnica

```bash
cd apps/crm
composer test
composer analyse
php -l public/index.php
php -l src/Actions.php
php -l src/Auth.php
php -l src/Repositories.php
php -l src/StripeBilling.php
node --check public/assets/app.js
node --check ../../httpdocs/assets/site.js
git diff --check
```

El CI amplía la sintaxis PHP a `apps/crm` y `httpdocs/api`, genera cobertura de la capa configurada, exige el umbral del 80 % y ejecuta Playwright solo cuando existe `E2E_BASE_URL`.
