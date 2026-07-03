# Requisitos - Membora CRM

> Nota de estado: este documento combina requisitos del MVP y requisitos previstos. El estado implementado actual esta resumido en `docs/07-estado-actual-php.md`; la arquitectura PHP activa esta documentada en `docs/06-api-backend.md`.

## 1. Objetivo

Membora CRM debe ser una aplicacion web SaaS responsive para gimnasios y centros fitness pequenos o medianos. El sistema debe centralizar la gestion comercial y operativa basica del centro: leads, socios, membresias, pagos manuales, clases, reservas, check-ins, tareas, alertas y dashboard.

## 2. Requisitos funcionales

### RF-01 Autenticacion

El sistema debe permitir iniciar y cerrar sesion a usuarios internos.

Criterios:

- Login con email y contrasena.
- Contrasenas almacenadas con hash.
- Sesion protegida mediante JWT o mecanismo equivalente.
- Rutas privadas protegidas.

### RF-02 Roles

El sistema debe aplicar permisos basicos segun rol.

Roles del MVP:

- `SUPERADMIN`
- `GYM_ADMIN`
- `SALES_RECEPTION`
- `TRAINER`

### RF-03 Multiempresa por tenant

El sistema debe separar los datos de cada gimnasio mediante `tenant_id`.

Criterios:

- Los usuarios de gimnasio solo acceden a datos de su tenant.
- El backend obtiene el tenant desde el usuario autenticado.
- Las entidades principales incluyen `tenant_id`.
- No se confia en un `tenant_id` enviado libremente desde frontend.

### RF-04 Leads

El sistema debe permitir gestionar leads.

Criterios:

- Crear, editar, listar y consultar leads.
- Registrar origen, estado, interes, datos de contacto, responsable y proxima accion.
- Asociar leads a una etapa del pipeline.

### RF-05 Pipeline comercial

El sistema debe representar el proceso comercial del gimnasio.

Etapas previstas:

1. Nuevo lead.
2. Contactado.
3. Visita o prueba agendada.
4. Prueba realizada.
5. Alta propuesta.
6. Convertido a socio.
7. Perdido.

### RF-06 Conversion de lead a socio

El sistema debe permitir convertir un lead en socio sin duplicar manualmente los datos principales.

Criterios:

- Crear socio desde lead.
- Mantener trazabilidad con `lead_id`.
- Marcar lead como convertido.
- Evitar conversiones duplicadas a nivel de logica de backend.

### RF-07 Socios

El sistema debe permitir gestionar socios.

Criterios:

- Crear, editar, listar y consultar socios.
- Mostrar ficha 360 con membresias, pagos, reservas, asistencias, tareas y alertas.
- Gestionar estados: activo, inactivo, en riesgo, baja y pendiente de pago.

### RF-08 Membresias

El sistema debe permitir crear planes y asignarlos a socios.

Criterios:

- Crear y editar planes.
- Activar o desactivar planes.
- Asignar una suscripcion a un socio.
- Consultar membresias activas, vencidas o canceladas.

### RF-09 Pagos manuales

El sistema debe permitir registrar pagos sin pasarela real.

Criterios:

- Asociar pago a socio y opcionalmente a suscripcion.
- Registrar importe, moneda, metodo, estado, fecha de pago y vencimiento.
- Estados: pagado, pendiente y vencido.

### RF-10 Clases

El sistema debe permitir crear tipos de clase y sesiones.

Criterios:

- Definir tipo, fecha, hora, duracion, aforo y entrenador.
- Consultar calendario de sesiones.
- Controlar estado de sesion.

### RF-11 Reservas

El sistema debe permitir reservar clases.

Criterios:

- Crear y cancelar reservas.
- Evitar reservas por encima del aforo.
- Registrar asistencia o no-show.

### RF-12 Check-in

El sistema debe permitir registrar asistencia.

Criterios:

- Check-in manual desde recepcion.
- Check-in mediante QR simple orientado a demo.
- Asociar check-in a socio y, si aplica, a clase o reserva.

### RF-13 Tareas

El sistema debe permitir crear tareas comerciales, operativas y de retencion.

Criterios:

- Asignar tareas a un usuario interno responsable.
- Clasificar la tarea como bienvenida/alta, seguimiento de socio, cobro/renovacion, operacion interna u otra categoria.
- Definir vencimiento.
- Marcar como completada o cancelada.

### RF-14 Alertas

El sistema debe generar o mostrar alertas basicas.

Tipos previstos:

- Pago pendiente.
- Membresia vencida.
- Socio inactivo.
- Lead sin seguimiento.
- Tarea vencida.
- Clase con alta ocupacion.

### RF-15 Dashboard

El sistema debe mostrar KPIs utiles para el gimnasio.

KPIs previstos:

- Socios activos.
- Altas y bajas del mes.
- Leads abiertos.
- Conversion lead-socio.
- MRR y ARPU estimados.
- Pagos pendientes.
- Asistencia semanal.
- Ocupacion de clases.
- No-shows.
- Socios en riesgo.
- Tareas vencidas.

## 3. Requisitos no funcionales

### RNF-01 Responsive

La interfaz debe ser usable en escritorio, tablet y telefono.

### RNF-02 Seguridad basica

El sistema debe incluir:

- Hash de contrasenas.
- JWT o mecanismo equivalente para sesion.
- Control de acceso por roles.
- Validacion de entradas.
- Variables de entorno para secretos.
- Separacion de datos por tenant.

### RNF-03 Trazabilidad

El sistema debe registrar acciones criticas en `AuditLog` cuando aplique.

Acciones recomendadas:

- Login.
- Creacion de usuarios.
- Conversion de lead.
- Cambios de pago.
- Cancelaciones.
- Cambios de estado relevantes.

### RNF-04 Mantenibilidad

El proyecto debe mantener separacion clara entre frontend, backend y base de datos.

### RNF-05 Ejecutabilidad

El proyecto debe poder instalarse y ejecutarse siguiendo el README.

### RNF-06 Datos demo

El proyecto debe incluir datos demo suficientes para defender el TFM.

Tenant demo:

- NexoFit Studio.

Usuarios demo previstos:

- `admin@nexofit.demo`
- `recepcion@nexofit.demo`
- `entrenador@nexofit.demo`

## 4. Entregables relacionados

- README completo.
- Codigo fuente en GitHub.
- URL de despliegue si existe.
- Slides.
- Video explicativo con captura de pantalla.
- Credenciales de prueba.
