# Membora CRM

**Membora CRM** es una plataforma web SaaS responsive para gimnasios, centros fitness y estudios deportivos pequenos o medianos. Es una aplicacion de gestion para propietarios, recepcion, comerciales, entrenadores y administradores de la plataforma.

El proyecto se ha migrado a una app PHP monolitica para simplificar el despliegue en Plesk y evitar procesos Node.js en produccion.

El proyecto sigue una metodología incremental orientada a requisitos: **alcance → requisitos → historias → especificación → pruebas → implementación → CI → despliegue y validación**. El proceso completo, su trazabilidad y sus criterios de finalización están documentados en `docs/19-metodologia-desarrollo.md`.

## Calidad y pruebas

```bash
cd apps/crm
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/captainhook install
```

La ejecución local del 17 de julio de 2026 completa **58 tests y 278 aserciones** sin errores. La cobertura se genera en `apps/crm/coverage/`; el CI exige un mínimo del 80 % a la capa aislable de permisos, CSRF, helpers y webhook. La última medición de cobertura guardada, realizada el 11 de julio de 2026, alcanza **93,50 % de líneas (604/646)**. Es una medición histórica de la capa configurada, no de todo el producto. En Plesk se sube `vendor/` o se ejecuta `composer install --no-dev --optimize-autoloader`.

Playwright está en `e2e/` y es solo para desarrollo/CI; Node.js no forma parte del despliegue. Debe apuntar exclusivamente a una app y BD local o de staging preparadas para pruebas, nunca a producción:

```bash
cd e2e
npm ci
npx playwright install chromium
E2E_BASE_URL="https://staging.example.test" E2E_EMAIL="gym-admin@example.test" E2E_PASSWORD="..." npm test
```

`E2E_INVOICE_URL` es opcional y debe contener la ruta o URL de una factura existente en esa BD para activar la prueba de impresión. El usuario debe ser un `GYM_ADMIN` de pruebas con acceso a socios, leads, clases y pagos. En GitHub Actions se configura `E2E_BASE_URL` y, opcionalmente, `E2E_INVOICE_URL` como variables del repositorio; `E2E_EMAIL` y `E2E_PASSWORD` como secretos. Si no existe `E2E_BASE_URL`, el job se omite. No se incluye ni se inventa una URL de staging.

## Estado actual

```text
Aplicacion PHP funcional para entrega MVP.
Web publica: https://membora.es/
CRM: https://membora.es/app/
Produccion sin Node.js, sin npm install y sin npm run build.
Un unico dominio y un unico document root en Plesk.
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
- Panel de administracion de Membora CRM separado en resumen, contactos, empresas, usuarios, cobros, facturas, planes y logs; el diagnostico de web/correo esta oculto del menu.
- Demo funcional desde la web publica con usuario temporal unico, contador de 20 minutos, limpieza al cerrar o caducar y retorno automatico a la web.
- Alta self-service con verificacion por email, tenant propio y prueba gratuita de 14 dias sin tarjeta.

Pendiente o futuro:

- Activar Stripe Live y validar configuracion bancaria, fiscal y comercial. La integracion actual funciona solo con claves, precios y webhooks de Stripe Test.
- Incorporar cobro automatico de cuotas de socios; los pagos operativos del gimnasio siguen siendo registros manuales.

Repositorio:

```text
https://github.com/Jacketring/Membora-CRM.git
```

## Stack

- PHP 8.2 o superior.
- MariaDB.
- PDO.
- HTML, CSS y JavaScript de navegador.
- Apache/Plesk con un unico document root en `httpdocs`.
- Web comercial en `/` y CRM en `/app/`, bajo el mismo dominio.
- Sin Node.js en produccion.
- Sin `npm install`.
- Sin `npm run build`.

## Arquitectura actual

La aplicacion PHP usa una estructura monolitica sencilla:

- `public/index.php`: entrada unica, routing basico y carga de vistas.
- `src/Actions.php`: acciones POST de formularios.
- `src/Auth.php`: login, sesion y contexto de soporte.
- `src/Repositories.php`: agregador de repositorios cargados por el bootstrap.
- `src/Repositories/`: acceso a datos y evolucion incremental del esquema por dominio.
- `src/StripeBilling.php`: checkout, webhooks e integracion Stripe Billing en modo de prueba.
- `src/Views/`: pantallas HTML/PHP.
- `public/assets/app.css`: estilos de la interfaz.
- `public/assets/app.js`: interacciones de modales, buscadores y controles.

La base de datos mantiene separacion por `tenant_id` para datos de gimnasios. La administracion SaaS usa `platform_leads`, `platform_clients`, `empresas`, `empresa_payments` y `saas_plans` para controlar solicitudes web, clientes comerciales, empresas con CRM, planes comerciales, pagos, facturacion mensual y acceso de soporte.

## Estructura

La estructura mapea directamente sobre un unico dominio en Plesk. El document
root apunta a `httpdocs`; la entrada segura `httpdocs/app/index.php` carga el CRM
desde `apps/crm`, mientras el codigo, la configuracion sensible y el almacenamiento
comun permanecen fuera del webroot.

```text
membora-crm/                     # raiz del repo = raiz de la suscripcion Plesk
|-- httpdocs/                    # DOCROOT UNICO: https://membora.es/
|   |-- assets/
|   |-- app/                     # https://membora.es/app/
|   |   |-- .htaccess
|   |   |-- index.php            # puente seguro hacia apps/crm/public
|   |-- .htaccess
|   |-- index.html
|   |-- aviso-legal.html
|   |-- privacidad.html
|   |-- cookies.html
|   |-- demo.html
|-- apps/
|   |-- crm/                     # aplicacion CRM privada (PHP)
|   |   |-- public/              # recursos servidos de forma controlada por /app/
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
|   |   |   |-- Repositories/
|   |   |   |-- StripeBilling.php
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

| URL | Contenido | Document root |
| --- | --- | --- |
| `https://membora.es/` | Web publica | `.../httpdocs` |
| `https://membora.es/app/` | CRM | Mismo `httpdocs`, mediante `httpdocs/app/index.php` |

## Configuracion

Crear `apps/crm/.env` en local o en Plesk.

La referencia completa y actualizada de variables es `apps/crm/.env.example`; los bloques siguientes son ejemplos minimos de conexion y correo.

Opcion recomendada en Plesk, especialmente si la contrasena tiene caracteres especiales:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
APP_URL="https://membora.es/app"
WEB_APP_URL="https://membora.es,https://www.membora.es"
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

1. Clonar el repositorio desde GitHub.
2. Configurar `membora.es` como hosting PHP.
3. Usar PHP 8.2 o superior.
4. Activar `pdo_mysql`.
5. Configurar la raiz del documento apuntando a:

```text
httpdocs
```

Si Plesk ha clonado el repositorio dentro de otra carpeta, la ruta debe acabar igualmente en:

```text
.../membora-crm/httpdocs
```

6. Crear `apps/crm/.env` con los datos reales de MariaDB.
7. Abrir `https://membora.es/` para la web y `https://membora.es/app/` para el CRM.

No hay que ejecutar comandos Node, compilar frontend ni reiniciar una app Node.

No hay que editar tokens en la web. El formulario envia al webhook del CRM y las solicitudes aparecen en `Admin CRM > Contactos`.
Si `MAIL_ENABLED` esta activo y el SMTP esta configurado, la persona que rellena el formulario recibe un email HTML de confirmacion indicando que el equipo revisara su solicitud y contactara en 24-48 horas. Los fallos quedan registrados para su diagnostico tecnico.
La ruta interna `index.php?route=platform-web` se creo exclusivamente para depurar el envio de correos durante el desarrollo. Esta oculta del menu normal, solo admite superadministradores y permite enviar una prueba tecnica, revisar la configuracion detectada y consultar el error SMTP exacto.
La web publica incluye enlaces a textos legales basicos: aviso legal, privacidad y cookies.
Los enlaces de demo de la web publica no abren una maqueta estatica: crean un usuario temporal unico e inician una sesion real del CRM durante 20 minutos sin depender del nombre exacto de `APP_ENV`. El acceso publico solo habilita la demo cliente; la demo de administrador queda limitada al entorno interno `demo`. El usuario se elimina al cerrar sesion, al caducar o tras la senal de cierre de pestana; la limpieza por caducidad actua tambien como respaldo. Al finalizar, el CRM devuelve al usuario a `WEB_APP_URL`.

La web tambien ofrece una prueba gratuita self-service de 14 dias. El alta requiere verificar el email; despues crea o actualiza el contacto como `Cliente CRM` con el nombre de la empresa y el correo introducido, lo vincula a una empresa `TRIAL` y crea el tenant y su usuario administrador. Se genera una contrasena aleatoria y se envia un segundo correo con un enlace temporal: la credencial esta cifrada, solo se revela tras una confirmacion y no puede volver a verse despues. Las solicitudes mantienen validacion de origen y honeypot. El limite por IP y email esta temporalmente desactivado por defecto y puede recuperarse con `TRIAL_RATE_LIMIT_ENABLED=true`. Se recomienda configurar `APP_KEY`; los despliegues antiguos usan de forma compatible la credencial estable de base de datos para derivar la clave.

En el `.env` del CRM debe existir `APP_URL="https://membora.es/app"` y
`WEB_APP_URL="https://membora.es,https://www.membora.es"`. Todo el flujo funciona
bajo el mismo dominio; la segunda variante permite que la web responda tambien con `www`.

## Credenciales de prueba

Administrador de gimnasio:

```text
Email: admin@nexofit.demo
Password: definida de forma segura en el entorno de pruebas (no se versiona).
```

Recepcion / comercial:

```text
Email: recepcion@nexofit.demo
Password: definida de forma segura en el entorno de pruebas (no se versiona).
```

Entrenador:

```text
Email: entrenador@nexofit.demo
Password: definida de forma segura en el entorno de pruebas (no se versiona).
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
- Seccion `Contactos` para unificar solicitudes web y clientes comerciales, con estados, filtros, conversion, eliminacion de leads o clientes y reparacion de contactos ausentes vinculados a empresas.
- Email automatico de confirmacion para solicitudes recibidas desde la web publica.
- Alta manual de contactos comerciales antes de crear su CRM.
- Tabla `empresas`.
- Alta, edicion y eliminacion controlada de empresas cliente desde un cliente comercial.
- Creacion de tenant y usuario administrador al crear una empresa CRM.
- Estado del CRM: activo, prueba, suspendido o cancelado.
- Estado de pago: al dia, pendiente, vencido o prueba.
- Precio mensual y proximo pago para planes de pago.
- Plan de prueba con duracion configurable por dias; solo cuando el plan es `Prueba` se oculta el proximo pago y no aparece renovacion.
- Aviso superior para cuentas `TRIAL` con los dias restantes y acceso a `Mejorar el plan`.
- Seleccion de planes de pago con proveedor configurable: checkout interno estrictamente simulado para la demostracion o Stripe Checkout alojado para pruebas de integracion reales.
- MRR estimado.
- Seccion `Facturacion` para gestionar facturas SaaS, pagos asociados, vencimientos, cobros pagados, pendientes y cancelados.
- Seccion `Usuarios` para gestionar cuentas de plataforma separadas de los usuarios de gimnasio.
- Seccion `Planes` para definir catalogo comercial, precio mensual, setup, rebajas, limites y prestaciones sincronizadas con la web publica.
- Stripe Billing en modo de prueba con checkout, webhooks firmados, idempotencia, renovaciones y cancelacion al final del periodo.
- El checkout se ofrece al administrador del gimnasio desde `Mejorar el plan`; los controles tecnicos y diagnosticos Stripe siguen ocultos en las pantallas administrativas.
- Ruta interna `platform-web`, oculta del menu y exclusiva de superadministradores, para diagnosticar el formulario publico y los envios cuando sea necesario.
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
- `docs/11-web-publica.md`: despliegue, proxies y formularios de la web comercial.
- `docs/12-migracion-facturas-saas.sql`: migracion SQL de facturacion SaaS.
- `docs/13-historial-cambios-recientes.md`: resumen de cambios recientes en suscripciones, facturacion, pagos, web publica y despliegue.
- `docs/14-cuotas-socios-recurrentes.sql`: apoyo SQL para cuotas recurrentes de socios.
- `docs/15-migracion-stripe-billing.sql`: migracion SQL de campos y eventos Stripe.
- `docs/16-stripe-billing-saas.md`: integracion Stripe Billing para cobros SaaS de Membora a gimnasios.
- `docs/17-endurecimiento-seguridad-2026-07-11.md`: correcciones verificadas de autenticacion, roles, secretos y endpoints.
- `docs/18-arquitectura-y-flujos.md`: arquitectura y recorridos de captacion, trial, demo, Stripe y soporte.
- `docs/18-auditoria-web-seo-accesibilidad-2026-07-14.md`: cierre verificable de la auditoria de contenido, SEO, accesibilidad y prueba publica.
- `docs/19-metodologia-desarrollo.md`: metodología incremental, trazabilidad, validación y criterio de finalización.
- `docs/adr/`: decisiones arquitectonicas vigentes.
- `docs/specs/`: especificaciones y criterios de aceptacion por incremento.
- `docs/entrega/guion-y-estructura-defensa-membora.md`: guion, tiempos, mensajes y checklist para grabar el video y defender el proyecto.

## Presentacion TFM

- `docs/entrega/membora-crm-tfm-presentacion.pptx`: slides editables para la defensa/demo del proyecto.

## Notas

La aplicacion PHP reutiliza la base de datos MariaDB existente y crea algunas tablas/columnas auxiliares si faltan. La version Node anterior ya no es necesaria para produccion.
