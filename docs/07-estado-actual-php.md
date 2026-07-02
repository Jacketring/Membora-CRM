# Estado actual de la version PHP - Membora CRM

Fecha de actualizacion: 30/06/2026.

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

### Pagos de gimnasio

- Registro de pagos manuales por socio.
- Asociacion opcional a una suscripcion activa.
- Importe en EUR.
- Metodo: efectivo, tarjeta, transferencia, Bizum u otro.
- Estados pagado, pendiente, vencido o cancelado.
- Fecha de vencimiento, fecha de pago y notas.
- Filtros por texto, estado y fechas.
- Indicadores de cobrado este mes, importe pendiente y vencidos.

### Facturacion externa

- Configuracion de proveedor externo generico por gimnasio.
- Endpoint, clave API enmascarada, estado activo/inactivo, formato y notas.
- Exportacion CSV de pagos pagados pendientes de envio.
- Sincronizacion simulada para dejar trazabilidad sin depender de terceros.
- Marcado de pagos como pendientes, exportados o sincronizados.
- Logs de operacion con fecha, proveedor, numero de pagos, importe total, resultado y payload tecnico.

### Check-ins

- Registro de entradas manuales por socio.
- Asociacion opcional a reservas de clases.
- Marcado automatico de la reserva como asistida al registrar check-in asociado.
- Metodo manual o QR preparado a nivel de datos.
- Filtros por texto y fechas.
- Indicadores de hoy, ultimos 7 dias, manuales y con clase.

### Alertas de riesgo

- Generacion automatica al abrir dashboard o pantalla de alertas.
- Pagos vencidos.
- Tareas vencidas.
- Membresias caducadas.
- Socios sin check-in reciente.
- Leads sin seguimiento reciente.
- Clases llenas.
- Estados abierta, resuelta o descartada.
- Filtros por texto, estado y tipo.

### Auditoria

- Registro automatico de acciones POST internas.
- Guarda usuario, tenant, accion, tipo de entidad, identificador, ruta, IP y navegador.
- Sanitiza contrasenas y tokens antes de guardar metadatos.
- Filtros por texto, accion, usuario y fechas.
- Indicadores de actividad de hoy, ultimos 7 dias, cambios y eliminaciones.

### Permisos

- Control centralizado de acceso por rol.
- Validacion de rutas antes de renderizar pantallas.
- Validacion de acciones POST antes de modificar datos.
- Menu lateral adaptado a los modulos permitidos para cada usuario.
- Superadmin separado de usuarios de gimnasio y compatible con modo soporte.

### Clases

- Tipos de clase.
- Sesiones con fecha, hora de inicio y hora de finalizacion.
- Calendario mensual.
- Creacion desde un dia del calendario.
- Edicion y eliminacion de sesiones.
- El calendario permanece abierto tras crear o editar sesiones desde el modal.
- Reservas de socios por sesion con control de aforo.
- Cancelacion, asistencia y no-show por reserva.

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

- Panel `Admin CRM` separado en resumen, leads web, clientes, empresas, pagos, planes y web comercial.
- Resumen ejecutivo con MRR, ARR, ARPA, pagos pendientes, cobrado en el mes y prioridades.
- Tabla `platform_leads` para solicitudes recibidas desde la web publica.
- Gestion de leads web con estados nuevo, contactado, cualificado, convertido o perdido.
- Conversion de lead web a cliente comercial.
- Eliminacion controlada de leads web desde el panel de administracion.
- Tabla `platform_clients` para contactos comerciales del SaaS antes de crear su CRM.
- Alta y edicion de clientes comerciales con estado lead, cualificado, cliente o perdido.
- Tabla `empresas`.
- Alta y edicion de empresas cliente desde clientes comerciales.
- Creacion de tenant y usuario administrador al crear una empresa con CRM.
- Estado del CRM: activo, prueba, suspendido o cancelado.
- Estado de pago: al dia, pendiente, vencido o prueba.
- Precio mensual y proximo pago.
- MRR estimado.
- Tabla `empresa_payments` para cobros SaaS por empresa.
- Registro y edicion de pagos con concepto, importe, vencimiento, fecha de pago, estado y notas.
- Tabla `saas_plans` para catalogo comercial.
- Gestion de planes con precio mensual, setup, limites de usuarios/socios, estado y prestaciones.
- Web comercial externa en `web-app/public`.
- Web comercial con enlaces a aviso legal, privacidad y cookies.
- Webhook publico sin token manual para registrar solicitudes en `Admin CRM > Leads`.
- Email HTML automatico de confirmacion para el visitante cuando envia el formulario web.
- Acceso de soporte al CRM de una empresa conectada.
- Banner de modo soporte y retorno al panel de administracion.
- Logs de plataforma para filtrar actividad de empresas por accion, fecha y texto.

## 4. Modulos pendientes

- Pasarela de pagos real con cobro automatico.

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
- `platform_leads`.
- `platform_clients`.
- `empresa_payments`.
- `saas_plans`.
- `lead_notes`.
- `webhook_settings`.
- `webhook_logs`.
- `task_members`.
- `membership_plans`.
- `subscriptions`.
- `payments`.
- `billing_integrations`.
- `billing_sync_logs`.
- `checkins`.
- `risk_alerts`.
- `audit_logs`.
- `class_types`.
- `class_sessions`.
- `reservations`.

Tambien puede anadir columnas auxiliares para imagenes y configuracion visual si faltan.

## 7. Flujo de despliegue recomendado

1. Pull desde GitHub en Plesk.
2. Confirmar que el document root apunta a `php-app/public`.
3. Confirmar que `php-app/.env` tiene las credenciales reales.
4. Abrir el subdominio.
5. Iniciar sesion con un usuario demo o con el admin de plataforma.

No se debe ejecutar `npm install`, `npm run build` ni comandos Prisma para la version PHP.
