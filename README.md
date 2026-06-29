# Membora CRM

**Membora CRM** es una plataforma web SaaS responsive para gimnasios, centros fitness y estudios deportivos pequenos o medianos. Es una aplicacion de gestion para propietarios, recepcion, comerciales, entrenadores y administradores de la plataforma.

El proyecto se ha migrado a una app PHP monolitica para simplificar el despliegue en Plesk y evitar procesos Node.js en produccion.

## Estado actual

```text
Aplicacion PHP funcional en migracion avanzada.
Despliegue previsto/actual: https://app.crm.josehurtado.dev
Produccion sin Node.js, sin npm install y sin npm run build.
```

Pantallas disponibles:

- Login.
- Panel de control del gimnasio.
- Leads con filtros, conversion a socio, notas y acciones.
- Captacion Web con webhook para recibir leads externos.
- Socios con foto, edicion y eliminacion controlada.
- Membresias con planes, precios, duracion y caducidad automatica.
- Clases con listado, calendario mensual y reservas de socios.
- Tareas con filtros, responsables y varios socios vinculados.
- Usuarios internos del gimnasio.
- Perfil de usuario.
- Configuracion visual personal.
- Panel de administracion de Membora CRM separado en resumen, clientes, empresas, pagos y planes.

Pendiente o futuro:

- Pagos completos.
- Check-ins.
- Alertas de riesgo.
- Auditoria de acciones.
- Integraciones futuras de facturacion externa.

Repositorio:

```text
https://github.com/Jacketring/Membora-CRM.git
```

## Stack

- PHP 8.2 o superior.
- MariaDB.
- PDO.
- HTML, CSS y JavaScript de navegador.
- Apache/Plesk con document root en `php-app/public`.
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

La base de datos mantiene separacion por `tenant_id` para datos de gimnasios. La administracion SaaS usa `platform_clients`, `empresas`, `empresa_payments` y `saas_plans` para controlar clientes comerciales, empresas con CRM, planes comerciales, pagos, facturacion mensual y acceso de soporte.

## Estructura

```text
membora-crm/
|-- php-app/
|   |-- config/
|   |-- public/
|   |   |-- assets/
|   |   |-- uploads/
|   |   |-- .htaccess
|   |   |-- index.php
|   |-- src/
|   |   |-- Views/
|   |   |-- Actions.php
|   |   |-- Auth.php
|   |   |-- Database.php
|   |   |-- Repositories.php
|   |   |-- Support.php
|   |   |-- View.php
|   |   |-- bootstrap.php
|   |-- .env.example
|   |-- README.md
|-- docs/
|-- README.md
|-- .gitignore
```

## Configuracion

Crear `php-app/.env` en local o en Plesk.

Opcion recomendada en Plesk, especialmente si la contrasena tiene caracteres especiales:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
DB_HOST="localhost"
DB_PORT="3306"
DB_DATABASE="nombre_base_datos"
DB_USERNAME="usuario_base_datos"
DB_PASSWORD="password_base_datos"
```

Tambien se admite `DATABASE_URL`:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
DATABASE_URL="mysql://usuario:password@localhost:3306/nombre_base_datos"
```

## Despliegue en Plesk

1. Clonar el repositorio desde GitHub.
2. Configurar el subdominio como hosting PHP.
3. Usar PHP 8.2 o superior.
4. Activar `pdo_mysql`.
5. Configurar la raiz del documento apuntando a:

```text
php-app/public
```

Si Plesk ha clonado el repositorio dentro de otra carpeta, la ruta debe acabar igualmente en:

```text
.../php-app/public
```

6. Crear `php-app/.env` con los datos reales de MariaDB.
7. Abrir el subdominio.

No hay que ejecutar comandos Node, compilar frontend ni reiniciar una app Node.

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
Password: MemboraAdmin2026!
```

Este usuario se crea automaticamente desde la aplicacion PHP si no existe.

## Funcionalidades actuales

### Gimnasio

- Login y cierre de sesion.
- Dashboard con KPIs principales.
- Buscador global superior.
- Gestion de leads.
- Captacion Web por webhook con token por tenant, ejemplos copiables y logs.
- Pipeline comercial.
- Conversion de lead a socio.
- Notas en leads.
- Gestion de socios con foto.
- Membresias y suscripciones.
- Clases, calendario mensual y reservas.
- Tareas con varios socios vinculados.
- Usuarios internos y roles.
- Perfil, imagen de usuario y configuracion visual.

### Administracion Membora CRM

- Panel `Admin CRM`.
- Resumen SaaS con MRR, ARR, ARPA, riesgo, cobros y prioridades.
- Seccion `Clientes` para contactos comerciales antes de crear su CRM.
- Tabla `empresas`.
- Alta y edicion de empresas cliente desde un cliente comercial.
- Creacion de tenant y usuario administrador al crear una empresa CRM.
- Estado del CRM: activo, prueba, suspendido o cancelado.
- Estado de pago: al dia, pendiente, vencido o prueba.
- Precio mensual y proximo pago.
- MRR estimado.
- Seccion `Pagos` para registrar cobros SaaS, vencimientos, pagados, pendientes y cancelados.
- Seccion `Planes` para definir catalogo comercial, precio mensual, setup, limites y prestaciones.
- Acceso de soporte al CRM de una empresa conectada.
- Banner de modo soporte y retorno al panel de administracion.

## Documentacion

- `docs/00-checklist-entrega-tfm.md`: checklist de entrega academica.
- `docs/01-alcance-mvp.md`: alcance funcional.
- `docs/02-requisitos.md`: requisitos.
- `docs/03-historias-usuario.md`: historias de usuario.
- `docs/04-modelo-datos.md`: modelo de datos.
- `docs/05-pruebas.md`: plan de pruebas.
- `docs/07-estado-actual-php.md`: estado actual de la version PHP.

## Notas

La aplicacion PHP reutiliza la base de datos MariaDB existente y crea algunas tablas/columnas auxiliares si faltan. La version Node anterior ya no es necesaria para produccion.
