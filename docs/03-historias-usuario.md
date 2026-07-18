# Historias de usuario - Membora

> Nota de estado: estas historias recogen el alcance funcional del producto. La version PHP actual cubre el flujo principal e incluye permisos por rol, auditoria, pagos, check-ins, alertas e integracion generica de facturacion.

Las historias y sus criterios de aceptación forman parte de la metodología incremental documentada en `docs/19-metodologia-desarrollo.md`. Sirven como enlace entre los requisitos, las especificaciones de cada incremento y su validación.

## 1. Autenticacion y roles

### HU-01 Login

Como usuario interno, quiero iniciar sesion con email y contrasena para acceder a la plataforma.

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

## 8. Alta, autenticacion y configuracion personal

### HU-22 Solicitar una prueba gratuita

Como propietario de un gimnasio, quiero verificar mi email y crear una prueba de 14 dias para evaluar la plataforma con un espacio aislado.

Criterios de aceptacion:

- La solicitud valida consentimiento, origen y honeypot; el limite especifico de frecuencia es configurable y esta desactivado por defecto durante la depuracion final.
- Antes de verificar el email no se crea ninguna empresa ni cuenta operativa.
- El enlace de activacion caduca y solo se usa una vez.
- El contacto aparece como `Cliente` con empresa vinculada y el nuevo usuario entra en un tenant propio con plan `TRIAL` durante 14 dias.
- Un segundo correo entrega un enlace temporal para revelar la contrasena inicial una sola vez, sin incluirla en el cuerpo del mensaje.

### HU-23 Recuperar la contrasena

Como usuario, quiero solicitar un enlace de recuperacion para volver a entrar sin que el sistema revele si mi email esta registrado.

Criterios de aceptacion:

- La respuesta publica es neutra.
- El enlace tiene caducidad y no puede reutilizarse.
- La nueva contrasena se almacena mediante hash y revoca tokens anteriores.

### HU-24 Mantener la sesion

Como usuario, quiero marcar `Recordarme` para recuperar mi sesion de forma limitada y revocable.

Criterios de aceptacion:

- El token se almacena en cookie segura y se rota al usarlo.
- Cerrar sesion elimina la cookie y revoca su selector.

### HU-25 Personalizar mi cuenta

Como usuario interno, quiero editar mi perfil, imagen, color y tema, y consultar las novedades de la plataforma.

Criterios de aceptacion:

- Los cambios solo afectan al usuario o tenant autorizado.
- Las imagenes se validan por tamano, extension y MIME real.
- La pantalla de novedades muestra version e historial disponibles.

## 9. Administracion SaaS

### HU-26 Gestionar contactos y empresas

Como superadministrador, quiero convertir solicitudes web en clientes y empresas para controlar el ciclo comercial completo.

Criterios de aceptacion:

- Leads web y clientes se consultan desde una vista unificada.
- La conversion conserva los datos comerciales y evita duplicados accidentales.
- Crear una empresa puede provisionar tenant y administrador de gimnasio.

### HU-27 Gestionar usuarios de plataforma

Como superadministrador, quiero crear, editar y eliminar usuarios de plataforma sin exponer esos roles a administradores de gimnasio.

Criterios de aceptacion:

- Solo un usuario de plataforma autorizado accede a estas acciones.
- Un gimnasio no puede asignarse un rol global mediante un POST manipulado.

### HU-28 Emitir y cobrar una factura SaaS

Como superadministrador, quiero crear una factura con sus lineas, emitirla y registrar uno o varios cobros para mantener su estado financiero.

Criterios de aceptacion:

- La numeracion se sugiere de forma coherente por serie.
- Los totales se recalculan a partir de base, impuestos y retenciones.
- Los pagos parciales y totales actualizan saldo y estado sin eliminar historial.
- La factura dispone de vista imprimible.

### HU-29 Probar una suscripcion Stripe

Como superadministrador, quiero iniciar un checkout Stripe de prueba para validar altas, renovaciones y cancelaciones antes de activar cobros reales.

Criterios de aceptacion:

- Solo se aceptan claves `sk_test_` y precios Stripe configurados.
- El retorno del navegador no activa por confiar en sus parametros: el servidor debe recuperar la sesion y verificar el pago directamente en Stripe, o recibir el webhook firmado.
- Los webhooks firmados se procesan una sola vez y sincronizan cobro, factura y acceso.

### HU-30 Entrar en modo soporte

Como superadministrador, quiero entrar temporalmente en la plataforma de una empresa para resolver incidencias y volver de forma visible al panel SaaS.

Criterios de aceptacion:

- El contexto de soporte muestra un banner y fija el tenant objetivo.
- Salir restaura el contexto de plataforma sin mezclar datos.

### HU-33 Mejorar el plan contratado

Como administrador de gimnasio con un plan de pago, quiero identificar mi plan actual y comparar niveles superiores para ampliar capacidad sin seleccionar por error el mismo plan o uno inferior.

Criterios de aceptacion:

- La tarjeta contratada se distingue con la etiqueta `PLAN ACTUAL`.
- Basic solo puede subir a Pro, Business o Enterprise; Pro a Business o Enterprise; y Business a Enterprise.
- Enterprise no recibe una llamada de mejora porque ya es el nivel maximo.
- El servidor vuelve a validar la jerarquia antes de abrir o completar el checkout simulado.

## 10. Web comercial e integraciones

### HU-31 Consultar planes publicos

Como visitante, quiero ver planes y precios actualizados desde el catalogo de la plataforma.

Criterios de aceptacion:

- El endpoint solo devuelve planes activos y datos comerciales publicos.
- Una indisponibilidad de la plataforma produce un error generico y no filtra detalles internos.

### HU-32 Configurar facturacion externa

Como administrador de gimnasio, quiero exportar pagos y registrar sincronizaciones con mi proveedor de facturacion para mantener trazabilidad sin acoplarme a una marca concreta.

Criterios de aceptacion:

- La clave configurada se muestra enmascarada.
- Solo se exportan pagos que cumplen los estados previstos.
- Cada intento registra fecha, resultado, importes y payload tecnico sanitizado.
