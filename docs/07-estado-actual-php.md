# Estado actual de la version PHP - Membora CRM

Fecha de actualizacion: 17/07/2026.

## 1. Resumen

Membora CRM se encuentra actualmente como una aplicacion PHP monolitica desplegable en Plesk. La decision tecnica principal ha sido abandonar el despliegue productivo con Node.js para reducir problemas de build, consumo de recursos y mantenimiento en hosting compartido.

La aplicacion funciona bajo un unico dominio y el prefijo `/app/`:

```text
https://membora.es/app/
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

## 3. Metodología y calidad

El desarrollo sigue el proceso incremental documentado en `docs/19-metodologia-desarrollo.md`: alcance, requisitos, historias, especificación, pruebas, implementación, integración continua y validación del despliegue.

Estado verificado el 17/07/2026:

- PHPUnit: **60 tests y 291 aserciones**, sin errores.
- PHPStan: sin errores.
- GitHub Actions: sintaxis PHP, PHPUnit, umbral de cobertura y PHPStan; E2E condicionado a un staging configurado.

## 4. Modulos implementados

### Autenticacion

- Login con usuarios existentes.
- Login demo automatico para cliente y administrador mediante un usuario temporal unico por acceso.
- Sesiones demo temporales de 20 minutos con contador y cierre automatico.
- Eliminacion del usuario demo al cerrar sesion, al caducar o tras el cierre de la pestana, con limpieza diferida de respaldo.
- Roles internos.
- Sesion PHP.
- Cierre de sesion.
- Opcion `Recordarme` con token rotatorio y revocable.
- Recuperacion de contrasena mediante enlace de un solo uso y respuesta publica neutra.
- Limite de intentos de login por IP y hash del email.
- Usuario superadmin de plataforma.

### Panel de gimnasio

- Dashboard con indicadores.
- Buscador global superior.
- Navegacion lateral.
- Modo claro por defecto, modo oscuro opcional y color personal.
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
- Membresias caducadas o proximas a renovar.
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
- Asignacion de tareas a usuarios internos mediante `assigned_user_id`.
- La tabla `task_members` queda como compatibilidad historica para datos antiguos.

### Usuarios internos

- Listado de usuarios del gimnasio.
- Creacion y edicion.
- Roles en espanol.
- Separacion respecto a socios/clientes.

### Administracion SaaS de Membora CRM

- Panel `Admin CRM` separado en resumen, contactos, empresas, usuarios de plataforma, pagos, facturas, planes y auditoria. La herramienta de web/correo permanece accesible solo por URL directa para diagnostico y esta oculta del menu.
- Resumen ejecutivo con MRR, ARR, ARPA, pagos pendientes, cobrado en el mes y prioridades.
- Tabla unificada de `Contactos` en la interfaz de administracion, combinando `platform_leads` y `platform_clients`.
- Gestion de solicitudes web con estados nuevo, contactado, cualificado, convertido o perdido.
- Gestion de clientes comerciales con estado lead, cualificado, cliente o perdido.
- Conversion de lead web a cliente comercial y eliminacion controlada de leads web desde la misma seccion.
- Eliminacion tanto de leads como de clientes comerciales, sin exigir cambiar artificialmente su tipo antes de borrarlos.
- Tabla `empresas`.
- Alta y edicion de empresas cliente desde clientes comerciales.
- Eliminacion controlada de empresas de prueba y de sus datos operativos asociados, conservando el contacto comercial y la auditoria.
- Creacion de tenant y usuario administrador al crear una empresa con CRM.
- Estado del CRM: activo, prueba, suspendido o cancelado.
- Estado de pago: al dia, pendiente, vencido o prueba.
- Precio mensual y proximo pago para planes de pago.
- Plan de prueba configurable por dias. Solo cuando el plan seleccionado es `Prueba`, se oculta el proximo pago, se muestra la duracion de prueba y no se ofrece renovacion.
- Banner superior para cuentas `TRIAL` con dias restantes y para clientes Basic, Pro o Business con una llamada a `Mejorar el plan`; Enterprise queda excluido del upselling.
- Catalogo exclusivo de planes de pago dentro del CRM del gimnasio. La tarjeta contratada se marca como `PLAN ACTUAL`; solo los niveles superiores quedan disponibles y el backend rechaza repeticiones o descensos.
- MRR estimado.
- Tabla `empresa_payments` para cobros SaaS por empresa.
- Registro y edicion de pagos con concepto, importe, vencimiento, fecha de pago, estado y notas.
- Facturas SaaS y facturas manuales para clientes, con lineas, impuestos, emision, vista imprimible y pagos parciales o totales.
- Usuarios de plataforma gestionados en una ruta exclusiva para administradores globales.
- Tabla `saas_plans` para catalogo comercial.
- Gestion de los planes canonicos Basic 49 EUR, Pro 89 EUR, Business 149 EUR y Enterprise 299 EUR, con precio mensual sin IVA, setup, limites de usuarios/socios, estado y prestaciones.
- Endpoint publico de cuatro planes consumido por la web comercial; la landing usa fallback equivalente solo si fallan el proxy y la ruta directa y actualiza `schema.org` desde el catalogo mostrado.
- Web comercial externa en `httpdocs`.
- Web comercial con enlaces a aviso legal, privacidad y cookies.
- El alta self-service verificada crea automaticamente un contacto `Cliente CRM`, su empresa `TRIAL` de 14 dias, el tenant y un usuario `GYM_ADMIN` activo y vinculado al mismo `tenant_id`.
- La contrasena inicial se entrega en un segundo correo mediante un enlace cifrado, temporal y de una sola visualizacion; no se incluye en el mensaje.
- Al abrir Contactos se reparan registros comerciales ausentes a partir de empresas existentes, lo que evita empresas sin su `Cliente CRM` visible.
- Enlaces de demo desde la web publica hacia una sesion funcional del CRM durante 20 minutos.
- Webhook publico sin token manual para registrar solicitudes en `Admin CRM > Contactos`.
- Email HTML automatico de confirmacion para el visitante cuando envia el formulario web.
- Acceso de soporte al CRM de una empresa conectada.
- Banner de modo soporte y retorno al panel de administracion.
- Logs de plataforma para filtrar actividad de empresas por accion, fecha y texto.
- Stripe Billing funcional en modo `stripe_test`: checkout alojado, webhook firmado, idempotencia, suscripciones, cancelacion al final del periodo y sincronizacion de cobros y facturas.
- Los controles tecnicos de checkout/cancelacion Stripe y el bloque de diagnostico se mantienen fuera de empresas y facturas. Stripe Checkout visible conserva el alta desde `TRIAL`; los ascensos de clientes pagados se realizan solo con el proveedor simulado para no crear suscripciones Stripe duplicadas.
- En modo simulado se crean inmediatamente un pago y justificante diferenciados y se activa el plan, tanto desde `TRIAL` como al subir de Basic, Pro o Business; en modo Stripe el plan no se activa hasta `invoice.paid`.

## 5. Pendiente para operacion real

- Activacion de Stripe Live con cuenta, banco, claves, precios y webhook de produccion.
- Validacion fiscal y comercial definitiva antes de cobrar a clientes reales.
- Pasarela de pago automatico para cuotas de socios dentro de cada gimnasio; esos pagos siguen siendo registros manuales.

## 6. Credenciales principales

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

Administrador de plataforma:

```text
Email: admin@membora.crm
Password: definida mediante `PLATFORM_ADMIN_PASSWORD` en `.env` durante el despliegue.
```

## 7. Tablas auxiliares creadas por PHP

La aplicacion PHP puede crear de forma incremental:

- `empresas`.
- `platform_leads`.
- `platform_clients`.
- `empresa_payments`.
- `saas_plans`.
- `empresas.trial_days`.
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
- `demo_users`.
- `demo_resets`.
- `login_attempts`.
- `auth_tokens`.
- `trial_registrations`.
- `platform_invoices`.
- `platform_invoice_items`.
- `platform_invoice_payments`.
- `stripe_events`.

Tambien puede anadir columnas auxiliares para imagenes, configuracion visual, facturacion y referencias Stripe si faltan.

## 8. Flujo de despliegue recomendado

1. Pull desde GitHub en Plesk.
2. Confirmar que el document root apunta a `httpdocs` y que `/app/` carga el CRM.
3. Confirmar que `apps/crm/.env` tiene las credenciales reales.
4. Abrir la URL del CRM.
5. Iniciar sesion con un usuario demo o con el admin de plataforma.

No se debe ejecutar `npm install`, `npm run build` ni comandos Prisma para la version PHP.
