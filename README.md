# Membora CRM

**Membora CRM** es una plataforma web SaaS responsive para gimnasios, centros fitness y estudios deportivos pequenos o medianos. Es una aplicacion de gestion para propietarios, recepcion, comerciales, entrenadores y administradores de la plataforma.

El proyecto se ha migrado a una app PHP monolitica para simplificar el despliegue en Plesk y evitar procesos Node.js en produccion.

El flujo de cambios funcionales es **spec → tests → implementación**: primero se valida la decisión de producto en `docs/specs/`, después se escribe la prueba automatizada y finalmente el código.

## Calidad y pruebas

```bash
cd apps/crm
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/captainhook install
```

La cobertura se genera en `apps/crm/coverage/`; el CI exige un mínimo del 80 % a la capa aislable de permisos, CSRF, helpers y webhook. La medición local del 11 de julio de 2026 alcanza **93,50 % de líneas (604/646)**, con 34 tests y 164 aserciones. En Plesk se sube `vendor/` o se ejecuta `composer install --no-dev --optimize-autoloader`.

Playwright está en `e2e/` y es solo para desarrollo/CI. Requiere una app y BD de prueba, además de `E2E_BASE_URL`, `E2E_EMAIL` y `E2E_PASSWORD`. Node.js no forma parte del despliegue.

## Estado actual

```text
Aplicacion PHP funcional para entrega MVP.
Despliegue: https://app.crm.josehurtado.dev
Produccion sin Node.js, sin npm install y sin npm run build.
Web comercial externa: https://app.web.josehurtado.dev
```

Pantallas disponibles:

- Login.
- Panel de control del gimnasio.
- Leads con filtros, conversion a socio, notas y acciones.
- Socios con foto, edicion y eliminacion controlada.
- Membresias con planes, precios, duracion y caducidad automatica.
- Pagos de socios con importe, metodo, estado, vencimiento e historial.
- Facturacion externa generica con configuracion, exportacion CSV, sincronizacion simulada y logs.
- Check-ins manuales de socios, con asociacion opcional a reservas de clase.
- Alertas de riesgo para pagos vencidos, tareas, membresias, leads y actividad.
- Auditoria de acciones internas.
- Permisos por rol para rutas y acciones.
- Clases con listado, calendario mensual y reservas de socios.
- Tareas internas con filtros, usuario responsable, estado y vencimiento.
- Usuarios internos del gimnasio.
- Perfil de usuario.
- Configuracion visual personal.
- Canal de novedades con version actual del CRM e historial de cambios.
- Panel de administracion de Membora CRM separado en resumen, contactos, empresas, facturacion, planes, web comercial y logs.
- Demo funcional desde la web publica con login automatico, contador de 20 minutos y retorno automatico a la web.

Pendiente o futuro:

- Pasarela de pagos real con cobro automatico.

Repositorio:

```text
https://github.com/Jacketring/Membora-CRM.git
```

## Stack

- PHP 8.2 o superior.
- MariaDB.
- PDO.
- HTML, CSS y JavaScript de navegador.
- Apache/Plesk con document root en `apps/crm/public`.
- Web comercial estatica en `httpdocs`.
- Sin Node.js en produccion.
- Sin `npm install`.
- Sin `npm run build`.

## Arquitectura actual

La aplicacion PHP usa una estructura monolitica sencilla:

- `public/index.php`: entrada unica, routing basico y carga de vistas.
- `src/Actions.php`: acciones POST de formularios.
- `src/Auth.php`: login, sesion y contexto de soporte.
- `src/Repositories.php`: consultas y creacion automatica de tablas auxiliares.
- `src/Views/`: pantallas HTML/PHP.
- `public/assets/app.css`: estilos de la interfaz.
- `public/assets/app.js`: interacciones de modales, buscadores y controles.

La base de datos mantiene separacion por `tenant_id` para datos de gimnasios. La administracion SaaS usa `platform_leads`, `platform_clients`, `empresas`, `empresa_payments` y `saas_plans` para controlar solicitudes web, clientes comerciales, empresas con CRM, planes comerciales, pagos, facturacion mensual y acceso de soporte.

## Estructura

La estructura mapea directamente sobre Plesk: cada subdominio apunta su document
root a una carpeta `public/` (o a `httpdocs/`), y el codigo de aplicacion,
la configuracion sensible y el almacenamiento comun quedan fuera del webroot.

```text
membora-crm/                     # raiz del repo = raiz de la suscripcion Plesk
|-- httpdocs/                    # WEB PUBLICA (marketing)  <- docroot de la web publica
|   |-- assets/
|   |-- .htaccess
|   |-- index.html
|   |-- aviso-legal.html
|   |-- privacidad.html
|   |-- cookies.html
|   |-- demo.html
|-- apps/
|   |-- crm/                     # aplicacion CRM (PHP)
|   |   |-- public/              # <- docroot subdominio app.crm
|   |   |   |-- assets/
|   |   |   |-- uploads/         # fotos de socios/usuarios (servidas como estaticas)
|   |   |   |-- .htaccess
|   |   |   |-- index.php
|   |   |-- src/
|   |   |   |-- Views/
|   |   |   |-- Actions.php
|   |   |   |-- Auth.php
|   |   |   |-- Database.php
|   |   |   |-- Repositories.php
|   |   |   |-- Support.php
|   |   |   |-- View.php
|   |   |   |-- bootstrap.php
|   |   |-- config/
|   |   |-- .env.example
|   |   |-- README.md
|-- shared/                      # comun a todo, fuera de cualquier public
|   |-- config/                  # configuracion global compartida (futuro .env comun)
|   |-- storage/                 # almacenamiento comun no publico (logs, exports)
|   |-- README.md
|-- docs/
|-- README.md
|-- .gitignore
```

Mapeo de despliegue en Plesk:

| Parte | Document root |
| --- | --- |
| Web publica (`app.web.josehurtado.dev`) | `.../httpdocs` |
| CRM (`app.crm.josehurtado.dev`) | `.../apps/crm/public` |

## Configuracion

Crear `apps/crm/.env` en local o en Plesk.

Opcion recomendada en Plesk, especialmente si la contrasena tiene caracteres especiales:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
APP_URL="https://app.crm.josehurtado.dev"
WEB_APP_URL="https://app.web.josehurtado.dev,https://membora.es,https://www.membora.es"
APP_STRICT_POST_ORIGIN="false"
DB_HOST="localhost"
DB_PORT="3306"
DB_DATABASE="nombre_base_datos"
DB_USERNAME="usuario_base_datos"
DB_PASSWORD="password_base_datos"
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

Tambien se admite `DATABASE_URL`:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
DATABASE_URL="mysql://usuario:password@localhost:3306/nombre_base_datos"
```

## Despliegue en Plesk

### CRM

1. Clonar el repositorio desde GitHub.
2. Configurar `app.crm.josehurtado.dev` como hosting PHP.
3. Usar PHP 8.2 o superior.
4. Activar `pdo_mysql`.
5. Configurar la raiz del documento apuntando a:

```text
apps/crm/public
```

Si Plesk ha clonado el repositorio dentro de otra carpeta, la ruta debe acabar igualmente en:

```text
.../apps/crm/public
```

6. Crear `apps/crm/.env` con los datos reales de MariaDB.
7. Abrir el subdominio.

No hay que ejecutar comandos Node, compilar frontend ni reiniciar una app Node.

### Web comercial

Configurar `app.web.josehurtado.dev` como sitio web separado y apuntar la raiz del documento a:

```text
httpdocs
```

No hay que editar tokens en la web. El formulario envia al webhook del CRM y las solicitudes aparecen en `Admin CRM > Contactos`.
Si `MAIL_ENABLED` esta activo y el SMTP esta configurado, la persona que rellena el formulario recibe un email HTML de confirmacion indicando que el equipo revisara su solicitud y contactara en 24-48 horas. Los fallos de correo quedan visibles en `Admin CRM > Web`.
La seccion `Admin CRM > Web` incluye una prueba de correo para enviar un email tecnico a una direccion concreta, ver la configuracion detectada y registrar el error SMTP exacto si falla.
La web publica incluye enlaces a textos legales basicos: aviso legal, privacidad y cookies.
Los enlaces de demo de la web publica no abren una maqueta estatica: inician una sesion demo real del CRM durante 20 minutos. Al finalizar el contador, el CRM cierra la sesion y devuelve al usuario a `WEB_APP_URL`.

En el `.env` del CRM debe existir `WEB_APP_URL="https://app.web.josehurtado.dev"` para permitir el envio del formulario y la carga publica de planes entre subdominios. Si la web publica responde en varios dominios, se pueden indicar separados por comas, por ejemplo `WEB_APP_URL="https://app.web.josehurtado.dev,https://www.tudominio.com"`.

## Credenciales de prueba

Administrador de gimnasio:

```text
Email: admin@nexofit.demo
Password: MemboraDemo2026!
```

Recepcion / comercial:

```text
Email: recepcion@nexofit.demo
Password: MemboraDemo2026!
```

Entrenador:

```text
Email: entrenador@nexofit.demo
Password: MemboraDemo2026!
```

Administrador de la plataforma Membora:

```text
Email: admin@membora.crm
Password: definida mediante `PLATFORM_ADMIN_PASSWORD` en `.env` durante el despliegue.
```

Este usuario se crea automaticamente desde la aplicacion PHP si no existe.

Las correcciones de seguridad y los requisitos de despliegue se detallan en
[`docs/17-endurecimiento-seguridad-2026-07-11.md`](docs/17-endurecimiento-seguridad-2026-07-11.md).

## Funcionalidades actuales

### Gimnasio

- Login y cierre de sesion.
- Dashboard con KPIs principales.
- Buscador global superior.
- Gestion de leads.
- Pipeline comercial.
- Conversion de lead a socio.
- Notas en leads.
- Gestion de socios con foto.
- Membresias y suscripciones.
- Pagos de socios, vencimientos y cobros pendientes.
- Facturacion externa generica con exportacion CSV y sincronizacion simulada.
- Check-ins manuales y asociados a reservas.
- Alertas de riesgo generadas desde pagos, tareas, membresias, leads, check-ins y clases.
- Auditoria de acciones internas con datos sanitizados.
- Permisos por rol en rutas y acciones POST.
- Clases, calendario mensual y reservas.
- Tareas internas asignadas a usuarios del equipo.
- Usuarios internos y roles.
- Perfil, imagen de usuario y configuracion visual.

### Administracion Membora CRM

- Panel `Admin CRM`.
- Resumen SaaS con MRR, ARR, ARPA, riesgo, cobros y prioridades.
- Seccion `Contactos` para unificar solicitudes web y clientes comerciales, con estados, filtros, conversion a cliente y eliminacion controlada de leads.
- Email automatico de confirmacion para solicitudes recibidas desde la web publica.
- Alta manual de contactos comerciales antes de crear su CRM.
- Tabla `empresas`.
- Alta y edicion de empresas cliente desde un cliente comercial.
- Creacion de tenant y usuario administrador al crear una empresa CRM.
- Estado del CRM: activo, prueba, suspendido o cancelado.
- Estado de pago: al dia, pendiente, vencido o prueba.
- Precio mensual y proximo pago para planes de pago.
- Plan de prueba con duracion configurable por dias; solo cuando el plan es `Prueba` se oculta el proximo pago y no aparece renovacion.
- MRR estimado.
- Seccion `Facturacion` para gestionar facturas SaaS, pagos asociados, vencimientos, cobros pagados, pendientes y cancelados.
- Seccion `Planes` para definir catalogo comercial, precio mensual, setup, rebajas, limites y prestaciones sincronizadas con la web publica.
- Seccion `Web` para revisar el estado tecnico del formulario publico y envios recientes.
- Seccion `Logs` para filtrar actividad por empresa, accion, fecha y texto.
- Acceso de soporte al CRM de una empresa conectada.
- Banner de modo soporte y retorno al panel de administracion.

## Documentacion

- `docs/00-checklist-entrega-tfm.md`: checklist de entrega academica.
- `docs/01-alcance-mvp.md`: alcance funcional.
- `docs/02-requisitos.md`: requisitos.
- `docs/03-historias-usuario.md`: historias de usuario.
- `docs/04-modelo-datos.md`: modelo de datos.
- `docs/05-pruebas.md`: plan de pruebas.
- `docs/06-api-backend.md`: rutas, acciones POST y webhook de la version PHP.
- `docs/07-estado-actual-php.md`: estado actual de la version PHP.
- `docs/08-auditoria-testing-2026-06-29.md`: auditoria tecnica y checklist manual de testing.
- `docs/09-seguridad-y-captacion-web.md`: medidas de seguridad y estrategia webhook/base de datos.
- `docs/10-incidencias-y-soluciones.md`: incidencias tecnicas del TFM y soluciones aplicadas.
- `docs/13-historial-cambios-recientes.md`: resumen de cambios recientes en suscripciones, facturacion, pagos, web publica y despliegue.
- `docs/16-stripe-billing-saas.md`: integracion Stripe Billing para cobros SaaS de Membora a gimnasios.

## Presentacion TFM

- `docs/entrega/membora-crm-tfm-presentacion.pptx`: slides editables para la defensa/demo del proyecto.

## Notas

La aplicacion PHP reutiliza la base de datos MariaDB existente y crea algunas tablas/columnas auxiliares si faltan. La version Node anterior ya no es necesaria para produccion.
