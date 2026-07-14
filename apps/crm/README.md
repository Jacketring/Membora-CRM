# Membora CRM PHP

Aplicacion PHP monolitica para ejecutar Membora CRM en `/app/` dentro del mismo dominio que la web publica, sin Next.js, NestJS ni procesos Node en produccion.

La web comercial vive en `httpdocs/` y expone el CRM mediante `httpdocs/app/index.php`, manteniendo el codigo privado en `apps/crm`.

## Requisitos

- PHP 8.2 o superior.
- Extension PDO MySQL activada.
- MariaDB/MySQL existente.
- Apache con `mod_rewrite` activado.
- Document root unico apuntando a `httpdocs`.

## Configuracion

Crear `apps/crm/.env` a partir de `.env.example`.

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
- Clases y calendario.
- Tareas.
- Usuarios internos.
- Perfil.
- Configuracion visual.
- Panel de administracion SaaS con resumen, contactos, empresas, pagos, planes y web comercial.

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
- Planes SaaS: codigo, precio mensual, coste de alta, limites y prestaciones.
- Contactos: tabla unificada de solicitudes web y clientes comerciales, con edicion, conversion a cliente, alta manual y eliminacion controlada de leads.
- Web comercial: estado tecnico del formulario publico y logs de envios recibidos.

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

Los enlaces de demo de la web publica envian un `POST` al login demo del CRM. La demo cliente publica no depende del nombre exacto de `APP_ENV`, mientras que la demo de administrador solo se habilita con `APP_ENV=demo`. Cada acceso crea un usuario temporal con credenciales aleatorias, dura 20 minutos, muestra un contador y elimina ese usuario al cerrar sesion, caducar o cerrar la pestana. Al terminar devuelve al usuario a `WEB_APP_URL`.

El formulario `Empieza gratis` envia a `/app/api/trial`. El servidor verifica origen, honeypot y rate limit, envia un enlace de activacion de una hora y, tras confirmar el email, crea un tenant con plan `TRIAL` durante 14 dias. La persona define su contrasena mediante el flujo seguro de recuperacion; no se envian contrasenas por correo.

El formulario de `httpdocs` envia al webhook:

```text
/app/webhook/lead
```

El webhook acepta `POST` con JSON, `application/x-www-form-urlencoded` o `multipart/form-data`.
No es necesario copiar tokens en la web. El CRM valida el origen configurado en `WEB_APP_URL`, aplica honeypot y rate limit, y crea la solicitud en `Admin CRM > Contactos`. Desde esa seccion el administrador puede mantenerla como lead, actualizar su estado o convertirla en cliente.

Cuando la solicitud incluye un email valido, el CRM intenta enviar una confirmacion HTML al contacto. Para produccion se recomienda SMTP con `MAIL_MAILER`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_ENCRYPTION`, `SMTP_USERNAME` y `SMTP_PASSWORD`. Si el envio falla, el lead se crea igualmente y el fallo de correo queda registrado en `Admin CRM > Web`.

Para depurar el correo, entra en `Admin CRM > Web` y usa `Prueba de correo`. La pantalla muestra la configuracion detectada de correo y registra el resultado en `Ultimos envios tecnicos`.

## Seguridad

Las medidas de seguridad y la estrategia de captacion web quedan documentadas en:

```text
docs/09-seguridad-y-captacion-web.md
```

La app aplica sesiones endurecidas, cabeceras de seguridad, validacion de origen en POST internos, consultas preparadas, aislamiento por `tenant_id`, validacion de uploads, honeypot y rate limit en captacion web.

El flujo activo usa webhook HTTP. Tambien queda documentada una alternativa futura para insertar solicitudes directamente en base de datos desde la web PHP si se quiere simplificar el despliegue.
