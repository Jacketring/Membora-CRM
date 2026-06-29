# Membora CRM PHP

Aplicacion PHP monolitica para ejecutar Membora CRM en un unico subdominio, sin Next.js, NestJS ni procesos Node en produccion.

La web comercial publica no vive dentro de `php-app`; esta separada en `web-app/public` para desplegarla en otro subdominio.

## Requisitos

- PHP 8.2 o superior.
- Extension PDO MySQL activada.
- MariaDB/MySQL existente.
- Apache con `mod_rewrite` activado.
- Document root apuntando a `php-app/public`.

## Configuracion

Crear `php-app/.env` a partir de `.env.example`.

Configuracion recomendada:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
APP_URL="https://app.crm.josehurtado.dev"
WEB_APP_URL="https://app.web.josehurtado.dev"
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
2. Configurar el subdominio para que el document root apunte a:

```text
php-app/public
```

3. Crear `php-app/.env` en el servidor con la conexion real a MariaDB.
4. Verificar que PHP usa una version 8.2 o superior.
5. Abrir el subdominio.

No hace falta ejecutar `npm install`, `npm run build`, `prisma generate` ni reiniciar una app Node para esta version PHP.

## Pantallas incluidas

- Login.
- Dashboard del gimnasio.
- Leads.
- Socios.
- Membresias.
- Clases y calendario.
- Tareas.
- Usuarios internos.
- Perfil.
- Configuracion visual.
- Panel de administracion SaaS con resumen, leads web, clientes, empresas, pagos, planes y web comercial.

## Administracion SaaS

La app crea y usa tablas de administracion SaaS para controlar clientes, empresas, cobros y catalogo comercial:

- Cliente comercial previo a la contratacion.
- Empresa cliente.
- Plan.
- Estado del CRM.
- Estado de pago.
- Precio mensual.
- Proximo pago.
- Notas internas.
- Acceso de soporte al CRM de la empresa si tiene `tenant_id`.
- Creacion de tenant y usuario administrador al pasar de cliente a empresa.
- Pagos SaaS por empresa: concepto, importe, vencimiento, fecha de pago y estado.
- Planes SaaS: codigo, precio mensual, coste de alta, limites y prestaciones.
- Leads web: solicitudes comerciales recibidas desde la web publica, con edicion, conversion a cliente y eliminacion controlada.
- Web comercial: estado tecnico del formulario publico y logs de envios recibidos.

Usuario de administracion de plataforma:

```text
Email: admin@membora.crm
Password: MemboraAdmin2026!
```

## Automatismos de base de datos

La aplicacion crea algunas tablas o columnas auxiliares si no existen, por ejemplo:

- `empresas`.
- `platform_leads`.
- `platform_clients`.
- `empresa_payments`.
- `saas_plans`.
- `lead_notes`.
- `webhook_logs`.
- `task_members`.
- `membership_plans`.
- `subscriptions`.
- `class_types`.
- `class_sessions`.
- columnas de imagen para usuarios/socios.

Esto permite desplegar cambios incrementales en Plesk sin ejecutar migraciones Node.

## Web comercial

La captacion web se revisa desde el panel de administradores de Membora CRM, no desde cada gimnasio cliente.

El formulario de `web-app/public` envia al webhook:

```text
/webhook/lead
```

El webhook acepta `POST` con JSON, `application/x-www-form-urlencoded` o `multipart/form-data`.
No es necesario copiar tokens en la web. El CRM valida el origen configurado en `WEB_APP_URL`, aplica honeypot y rate limit, y crea la solicitud en `Admin CRM > Leads`. Desde esa seccion el administrador puede mantenerla como lead, actualizar su estado o convertirla en cliente.

Cuando la solicitud incluye un email valido, el CRM intenta enviar una confirmacion HTML al contacto. Para produccion se recomienda usar SMTP con `MAIL_MAILER`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME` y `SMTP_PASSWORD`. Si Plesk no tiene correo saliente configurado, el lead se crea igualmente y el fallo de correo queda registrado en `Admin CRM > Web`.

Para depurar el correo, entra en `Admin CRM > Web` y usa `Prueba de correo`. La pantalla muestra la configuracion detectada de correo y registra el resultado en `Ultimos envios tecnicos`.
