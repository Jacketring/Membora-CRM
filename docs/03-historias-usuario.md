# Historias de usuario - Membora CRM

## 1. Autenticacion y roles

### HU-01 Login

Como usuario interno, quiero iniciar sesion con email y contrasena para acceder al CRM.

Criterios de aceptacion:

- Si las credenciales son validas, accedo a la aplicacion.
- Si las credenciales son invalidas, veo un error controlado.
- No puedo acceder a rutas privadas sin sesion.

### HU-02 Logout

Como usuario interno, quiero cerrar sesion para proteger el acceso a la informacion del gimnasio.

Criterios de aceptacion:

- Al cerrar sesion, el token o sesion queda invalidado en cliente.
- Se redirige al login.

### HU-03 Permisos por rol

Como administrador del gimnasio, quiero que cada usuario vea solo las funcionalidades permitidas por su rol.

Criterios de aceptacion:

- Recepcion puede gestionar leads, socios, pagos, reservas y check-ins.
- Entrenador puede consultar clases asignadas y registrar asistencia.
- Superadmin puede consultar informacion global o tenants demo.

## 2. Leads y pipeline

### HU-04 Crear lead

Como recepcion o comercial, quiero crear un lead para registrar una persona interesada en el gimnasio.

Criterios de aceptacion:

- Puedo guardar nombre, contacto, origen, interes y etapa.
- El lead queda asociado al tenant del usuario autenticado.

### HU-05 Mover lead por pipeline

Como comercial, quiero mover un lead entre etapas para reflejar su estado comercial.

Criterios de aceptacion:

- Puedo cambiar la etapa desde la vista de pipeline.
- El cambio queda guardado.
- No puedo asignar una etapa de otro tenant.

### HU-06 Convertir lead en socio

Como comercial, quiero convertir un lead en socio cuando decide darse de alta.

Criterios de aceptacion:

- Se crea un socio con los datos principales del lead.
- El lead queda marcado como convertido.
- No se puede convertir dos veces el mismo lead.

## 3. Socios y membresias

### HU-07 Consultar socios

Como recepcion, quiero ver el listado de socios para localizar rapidamente a una persona.

Criterios de aceptacion:

- Puedo consultar socios del tenant actual.
- Puedo ver estado y datos principales.

### HU-08 Ver ficha 360

Como administrador, quiero ver una ficha completa del socio para entender su situacion.

Criterios de aceptacion:

- La ficha muestra datos personales, membresia, pagos, reservas, check-ins, tareas y alertas.

### HU-09 Crear plan de membresia

Como administrador, quiero crear planes de membresia para comercializarlos en el gimnasio.

Criterios de aceptacion:

- Puedo definir nombre, precio, periodicidad y estado activo.

### HU-10 Asignar membresia

Como recepcion, quiero asignar una membresia a un socio para registrar su alta.

Criterios de aceptacion:

- Puedo seleccionar socio, plan, fecha de inicio y fecha de fin.
- La suscripcion queda asociada al mismo tenant.

## 4. Pagos

### HU-11 Registrar pago

Como recepcion, quiero registrar pagos manuales para controlar el estado de cobro.

Criterios de aceptacion:

- Puedo guardar importe, metodo, estado, fecha de pago y vencimiento.
- El pago queda asociado a un socio.

### HU-12 Consultar pagos pendientes

Como administrador, quiero consultar pagos pendientes o vencidos para hacer seguimiento.

Criterios de aceptacion:

- Puedo filtrar pagos por estado.
- Los pagos vencidos pueden aparecer como alerta.

## 5. Clases, reservas y check-in

### HU-13 Crear sesion de clase

Como administrador o entrenador autorizado, quiero crear sesiones de clase para organizar la agenda.

Criterios de aceptacion:

- Puedo definir tipo de clase, entrenador, fecha, hora y aforo.

### HU-14 Reservar plaza

Como recepcion, quiero reservar una plaza para un socio en una clase.

Criterios de aceptacion:

- No se permite superar el aforo.
- No se permite duplicar una reserva activa para el mismo socio y sesion.

### HU-15 Cancelar reserva

Como recepcion, quiero cancelar una reserva para liberar una plaza.

Criterios de aceptacion:

- La reserva pasa a estado cancelada.
- La reserva cancelada no cuenta contra el aforo.

### HU-16 Registrar check-in manual

Como recepcion, quiero registrar manualmente la entrada de un socio.

Criterios de aceptacion:

- Puedo seleccionar socio.
- Se registra fecha y hora.
- Si existe reserva, puede marcarse como asistida.

### HU-17 Check-in con QR

Como recepcion o entrenador, quiero usar un QR simple para agilizar el registro de asistencia.

Criterios de aceptacion:

- El QR identifica al socio o reserva.
- El sistema registra el check-in sin integracion con hardware externo.

## 6. Tareas, alertas y dashboard

### HU-18 Crear tarea

Como comercial, quiero crear tareas de seguimiento para no perder oportunidades o socios en riesgo.

Criterios de aceptacion:

- Puedo asociar la tarea a un lead o socio.
- Puedo asignar responsable y vencimiento.

### HU-19 Gestionar alerta

Como administrador, quiero ver alertas de riesgo para priorizar acciones.

Criterios de aceptacion:

- Puedo consultar alertas abiertas.
- Puedo resolver o descartar una alerta.

### HU-20 Consultar dashboard

Como administrador, quiero ver indicadores clave para entender la situacion del gimnasio.

Criterios de aceptacion:

- El dashboard muestra KPIs de socios, leads, pagos, asistencia, reservas y tareas.
- Los datos pertenecen al tenant actual.

## 7. Despliegue y demo

### HU-21 Acceder a demo

Como evaluador del TFM, quiero acceder al proyecto desplegado con credenciales de prueba para revisar la aplicacion.

Criterios de aceptacion:

- El README contiene URL de despliegue.
- El README contiene usuario y contrasena de prueba.
- Los datos demo permiten recorrer las funcionalidades principales.
