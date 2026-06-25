# Estado actual de la version PHP - Membora CRM

Fecha de actualizacion: 25/06/2026.

## 1. Resumen

Membora CRM se encuentra actualmente como una aplicacion PHP monolitica desplegable en Plesk. La decision tecnica principal ha sido abandonar el despliegue productivo con Node.js para reducir problemas de build, consumo de recursos y mantenimiento en hosting compartido.

La aplicacion funciona en un unico subdominio:

```text
https://app.crm.josehurtado.dev
```

## 2. Stack actual

- PHP 8.2 o superior.
- MariaDB.
- PDO.
- HTML renderizado desde PHP.
- CSS propio.
- JavaScript de navegador para modales, buscadores, filtros y controles.
- Plesk como entorno de despliegue.

No se usa Node.js en produccion.

## 3. Modulos implementados

### Autenticacion

- Login con usuarios existentes.
- Roles internos.
- Sesion PHP.
- Cierre de sesion.
- Usuario superadmin de plataforma.

### Panel de gimnasio

- Dashboard con indicadores.
- Buscador global superior.
- Navegacion lateral.
- Modo claro/oscuro y color personal.
- Perfil de usuario con imagen.

### Leads

- Listado de leads.
- Filtros por texto, etapa, estado y fechas.
- Creacion y edicion.
- Cambio de etapa comercial.
- Estado automatico segun etapa.
- Conversion de lead a socio.
- Eliminacion.
- Notas por lead con edicion y eliminacion.
- Telefono con prefijo internacional.

### Socios

- Listado de socios.
- Creacion y edicion.
- Foto de socio.
- Eliminacion con reactivacion del lead asociado cuando procede.
- Estado simplificado en espanol.
- Vinculacion visual con membresia activa.

### Membresias

- Planes de membresia.
- Precio.
- Periodicidad semanal, mensual o anual.
- Calculo automatico de fecha de caducidad al asignar membresia.
- Suscripciones activas por socio.

### Clases

- Tipos de clase.
- Sesiones con fecha, hora de inicio y hora de finalizacion.
- Calendario mensual.
- Creacion desde un dia del calendario.
- Edicion y eliminacion de sesiones.
- El calendario permanece abierto tras crear o editar sesiones desde el modal.

### Tareas

- Listado de tareas.
- Filtros por texto, tipo, estado, responsable y fechas.
- Creacion y edicion.
- Acciones con iconos.
- Confirmacion visual de eliminacion.
- Vinculacion a varios socios mediante tabla `task_members`.

### Usuarios internos

- Listado de usuarios del gimnasio.
- Creacion y edicion.
- Roles en espanol.
- Separacion respecto a socios/clientes.

### Administracion SaaS de Membora CRM

- Panel `Admin CRM`.
- Tabla `empresas`.
- Alta y edicion de empresas cliente.
- Estado del CRM: activo, prueba, suspendido o cancelado.
- Estado de pago: al dia, pendiente, vencido o prueba.
- Precio mensual y proximo pago.
- MRR estimado.
- Acceso de soporte al CRM de una empresa conectada.
- Banner de modo soporte y retorno al panel de administracion.

## 4. Modulos pendientes

- Pagos completos del gimnasio.
- Reservas de clase.
- Check-ins.
- Alertas de riesgo.
- Mejoras avanzadas de facturacion SaaS.
- Auditoria de acciones.
- Permisos finos por rol.

## 5. Credenciales principales

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

Administrador de plataforma:

```text
Email: admin@membora.crm
Password: MemboraAdmin2026!
```

## 6. Tablas auxiliares creadas por PHP

La aplicacion PHP puede crear de forma incremental:

- `empresas`.
- `lead_notes`.
- `task_members`.
- `membership_plans`.
- `subscriptions`.
- `class_types`.
- `class_sessions`.

Tambien puede anadir columnas auxiliares para imagenes y configuracion visual si faltan.

## 7. Flujo de despliegue recomendado

1. Pull desde GitHub en Plesk.
2. Confirmar que el document root apunta a `php-app/public`.
3. Confirmar que `php-app/.env` tiene las credenciales reales.
4. Abrir el subdominio.
5. Iniciar sesion con un usuario demo o con el admin de plataforma.

No se debe ejecutar `npm install`, `npm run build` ni comandos Prisma para la version PHP.
