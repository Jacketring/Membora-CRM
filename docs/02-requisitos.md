# Requisitos - Membora CRM

> Nota de estado: este documento combina requisitos del MVP y requisitos previstos. El estado implementado actual esta resumido en `docs/07-estado-actual-php.md`; la arquitectura PHP activa esta documentada en `docs/06-api-backend.md`.

Dentro de la metodología del proyecto, estos requisitos conectan el alcance con las historias de usuario, las especificaciones y las pruebas. La trazabilidad completa se explica en `docs/19-metodologia-desarrollo.md`.

## 1. Objetivo

Membora CRM debe ser una aplicacion web SaaS responsive para gimnasios y centros fitness pequenos o medianos. El sistema debe centralizar la gestion comercial y operativa basica del centro: leads, socios, membresias, pagos manuales, clases, reservas, check-ins, tareas, alertas y dashboard.

## 2. Requisitos funcionales

### RF-01 Autenticacion

El sistema debe permitir iniciar y cerrar sesion a usuarios internos.

Criterios:

- Login con email y contrasena.
- Contrasenas almacenadas con hash.
- Sesión protegida mediante sesiones PHP endurecidas: cookie `HttpOnly`, `SameSite=Lax`, `Secure` bajo HTTPS, modo estricto y token CSRF.
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

### RF-16 Administracion SaaS

El superadministrador debe disponer de un espacio separado para gestionar contactos, empresas cliente, usuarios de plataforma, planes, cobros, facturas y auditoria global. El diagnostico de web/correo se mantiene como herramienta interna oculta del menu.

- Los usuarios de gimnasio no deben acceder a rutas ni acciones de plataforma.
- El superadministrador puede entrar en modo soporte sobre una empresa conectada y volver al panel global.
- Los indicadores SaaS deben incluir MRR, ARR, ARPA, cobros y prioridades.

### RF-17 Captacion y contactos comerciales

El sistema debe recibir solicitudes desde la web publica, validarlas y gestionarlas junto con los clientes comerciales.

- La captacion publica debe aplicar origen permitido, honeypot y rate limit.
- Debe admitir integraciones de tenant autenticadas mediante token.
- Un lead web puede convertirse en cliente comercial sin perder su procedencia.
- Los errores tecnicos y de correo deben quedar registrados sin exponer secretos al visitante.

### RF-18 Alta self-service

Una persona debe poder solicitar una prueba gratuita de 14 dias desde la web publica.

- El email se verifica mediante un enlace de un solo uso y una hora de validez.
- No se crea tenant, empresa ni usuario antes de verificar el email.
- Tras la activacion se crea o actualiza un contacto `Cliente CRM`, se vincula una empresa `TRIAL`, un tenant aislado y su administrador.
- El sistema genera una contrasena inicial aleatoria y envia un segundo correo con un enlace temporal para revelarla una sola vez; la contrasena no viaja en el correo.
- La credencial temporal permanece cifrada, caduca en una hora y se consume antes de mostrarse para impedir una segunda visualizacion.
- El limite especifico por IP y email se puede activar con `TRIAL_RATE_LIMIT_ENABLED=true`; durante la depuracion final permanece desactivado por defecto, sin desactivar origen permitido ni honeypot.

### RF-19 Facturacion SaaS

El superadministrador debe poder crear y emitir facturas a empresas y clientes comerciales.

- La factura incluye serie, numero, emisor, receptor, lineas, impuestos y totales.
- Debe admitir borradores, emision, pagos parciales o totales y visualizacion imprimible.
- Los cobros asociados deben actualizar el estado pendiente, parcial o pagado sin perder historial.
- El sistema no debe presentarse como software Verifactu certificado.

### RF-20 Stripe Billing en modo de prueba

El sistema debe integrar Stripe Billing en modo `stripe_test` para validar el recorrido SaaS sin dinero real.

- Debe crear checkout alojado y asociarlo a empresa, plan y periodicidad.
- Debe verificar `Stripe-Signature` y procesar eventos de forma idempotente.
- El acceso y el cobro solo cambian tras confirmacion por webhook, no por la URL de retorno.
- Debe permitir cancelar al final del periodo y consultar el estado sincronizado de la suscripcion.
- Stripe Live queda pendiente de configuracion bancaria, fiscal y comercial.
- Una empresa `TRIAL` debe ver los dias restantes y poder elegir un plan de pago desde su propio CRM.
- Las empresas `BASIC`, `PRO` y `BUSINESS` deben ver una llamada de mejora; la pantalla debe marcar su plan actual y habilitar unicamente planes de rango superior.
- El backend debe rechazar la seleccion del mismo plan, los descensos y codigos fuera del catalogo, aunque se manipule el formulario.
- Solo el administrador del gimnasio puede iniciar Checkout; los demas roles pueden consultar los planes.
- En modo simulado solo se admite la tarjeta ficticia documentada; sus campos se descartan y censuran en auditoria, sin contactar con Stripe ni bancos.
- El pago simulado crea pago y justificante administrativo diferenciados y activa el acceso en una unica transaccion. Con el proveedor Stripe, el plan permanece pendiente hasta `invoice.paid`.

### RF-21 Autenticacion recuperable

El sistema debe permitir recordar una sesion y recuperar una contrasena sin guardar tokens reutilizables en claro.

- El login debe limitar intentos fallidos por IP y hash del email.
- El token de recuerdo debe rotarse y poder revocarse al cerrar sesion.
- La recuperacion debe responder de forma neutra para no revelar si un email existe.
- El token de restablecimiento debe caducar y usarse una sola vez.

### RF-22 Facturacion externa del gimnasio

Cada gimnasio debe poder configurar una integracion externa generica, exportar pagos cobrados y registrar una sincronizacion trazable sin depender de un proveedor concreto.

### RF-23 Novedades, perfil y configuracion

Los usuarios autenticados deben poder actualizar su perfil, imagen y preferencias visuales, y consultar la version actual y el historial de novedades del CRM.

### RF-24 Planes publicos

La web comercial debe poder consultar mediante un endpoint de solo lectura los planes SaaS activos, su precio y prestaciones sin acceder al panel administrativo.

- El catalogo publico debe contener Basic, Pro, Business y Enterprise con los mismos precios, limites y prestaciones que `saas_plans`.
- La landing debe consultar primero la API y utilizar un fallback equivalente solo cuando fallen el proxy y el endpoint directo.
- Los precios deben indicarse sin IVA y el marcado `schema.org` debe generarse a partir del catalogo mostrado.

## 3. Requisitos no funcionales

### RNF-01 Responsive

La interfaz debe ser usable en escritorio, tablet y telefono.

### RNF-02 Seguridad basica

El sistema debe incluir:

- Hash de contrasenas.
- Sesiones PHP endurecidas y protección CSRF como mecanismo de autenticación web.
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
