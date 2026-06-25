# Alcance del MVP - Membora CRM

> Nota de estado actual: el proyecto ha evolucionado a una aplicacion PHP monolitica desplegable en Plesk. El alcance funcional sigue siendo el mismo como referencia academica, pero el estado real implementado se documenta en `docs/07-estado-actual-php.md`.

## 1. Nombre del proyecto

**Membora CRM**

## 2. Descripción general

Membora CRM es una plataforma web SaaS responsive orientada a gimnasios, centros fitness y estudios deportivos pequeños o medianos. Su objetivo es centralizar la gestión comercial y operativa básica del negocio, cubriendo el ciclo de vida del cliente desde que entra como lead hasta que se convierte en socio activo.

El sistema se plantea como un CRM vertical para el sector fitness, más específico que un CRM generalista y más simple que una suite integral de gestión deportiva. El MVP se centrará en funcionalidades realistas para un Trabajo de Fin de Máster de desarrollo de software, evitando módulos demasiado amplios como app móvil nativa, rutinas, nutrición, pasarela de pagos completa o integración con hardware de acceso.

## 3. Objetivo del MVP

El objetivo del MVP es desarrollar una aplicación funcional que permita a un gimnasio gestionar de forma centralizada:

- Captación y seguimiento de leads.
- Conversión de leads a socios.
- Gestión de socios y membresías.
- Registro manual de pagos.
- Creación de clases y sesiones.
- Reservas y cancelaciones.
- Check-in manual y mediante QR simple.
- Tareas comerciales y de retención.
- Alertas básicas por inactividad o pagos pendientes.
- Dashboard con indicadores clave del negocio.

El MVP debe demostrar una solución realista, útil y defendible académicamente, con una arquitectura web moderna y un modelo SaaS multiempresa basado en `tenant_id`.

## 4. Problema que resuelve

Muchos gimnasios independientes y centros fitness pequeños gestionan su actividad diaria mediante herramientas dispersas como hojas de cálculo, agendas manuales, WhatsApp, formularios, aplicaciones de calendario o CRM generalistas.

Esta situación provoca varios problemas:

- Pérdida de seguimiento comercial de leads interesados.
- Dificultad para saber qué leads han probado el centro y cuáles se han convertido en socios.
- Falta de visión centralizada del estado de cada socio.
- Gestión manual de membresías, pagos y vencimientos.
- Control limitado de reservas, asistencia y no-shows.
- Poca capacidad para detectar socios inactivos o en riesgo de baja.
- Ausencia de KPIs claros para tomar decisiones.

Membora CRM busca resolver estos problemas desde una propuesta ligera, vertical y adaptada al contexto de centros fitness pequeños o medianos.

## 5. Público objetivo

El sistema está dirigido a:

- Gimnasios independientes.
- Centros fitness pequeños o medianos.
- Estudios boutique.
- Boxes de entrenamiento funcional.
- Centros deportivos con clases dirigidas.
- Negocios deportivos que necesiten gestionar socios, reservas y seguimiento comercial.

No está orientado a grandes cadenas con necesidades avanzadas de multi-sede, control de acceso físico, facturación compleja o integraciones corporativas.

## 6. Usuarios y roles del sistema

### 6.1 Superadmin SaaS

Usuario responsable de administrar la plataforma a nivel global. En el MVP tendrá un alcance limitado, principalmente orientado a la gestión inicial de tenants o gimnasios demo.

Funciones principales:

- Crear o consultar gimnasios registrados.
- Acceder a información básica del tenant.
- Supervisar el estado general de la plataforma.

### 6.2 Administrador del gimnasio

Usuario propietario o responsable del centro fitness.

Funciones principales:

- Gestionar configuración básica del gimnasio.
- Consultar dashboard y KPIs.
- Gestionar usuarios internos.
- Supervisar leads, socios, pagos, clases y reservas.
- Consultar alertas y tareas.

### 6.3 Recepción / comercial

Usuario encargado de la atención al cliente, captación y operación diaria.

Funciones principales:

- Crear y actualizar leads.
- Mover leads por el pipeline comercial.
- Convertir leads en socios.
- Registrar pagos.
- Gestionar reservas.
- Realizar check-in manual o mediante QR.
- Crear tareas de seguimiento.

### 6.4 Entrenador

Usuario responsable de clases y sesiones.

Funciones principales:

- Consultar clases asignadas.
- Consultar reservas de una clase.
- Registrar asistencia o check-in.
- Ver información básica de socios relacionada con la actividad.

### 6.5 Socio

El socio será una entidad gestionada dentro del CRM, pero no tendrá acceso con login en el MVP obligatorio.

Un portal de socio responsive podrá considerarse como mejora futura.

## 7. Ciclo principal del sistema

El flujo principal que cubrirá Membora CRM será:

**lead -> prueba -> alta -> socio -> membresía -> reserva -> check-in -> pago -> retención**

Este ciclo permite justificar el proyecto como un CRM específico para gimnasios, ya que no se limita a almacenar contactos, sino que conecta la gestión comercial con operaciones básicas y retención.

## 8. Alcance funcional del MVP

### 8.1 Autenticación y roles

El sistema incluirá login, gestión básica de sesión y control de permisos según rol.

Funcionalidades incluidas:

- Inicio de sesión.
- Cierre de sesión.
- Usuario asociado a un tenant.
- Roles básicos.
- Protección de rutas privadas.
- Restricción de acciones según rol.

### 8.2 Modelo SaaS multiempresa

Membora CRM se diseñará como una aplicación SaaS multiempresa mediante una base de datos compartida y separación lógica por `tenant_id`.

Funcionalidades incluidas:

- Cada gimnasio será un tenant.
- Los datos principales estarán asociados a un `tenant_id`.
- Los usuarios solo podrán acceder a la información de su gimnasio.
- El backend aplicará filtros por tenant en las operaciones principales.

### 8.3 Gestión de leads

El sistema permitirá registrar y hacer seguimiento de personas interesadas en el gimnasio.

Funcionalidades incluidas:

- Crear lead.
- Editar lead.
- Consultar listado de leads.
- Filtrar leads por estado.
- Registrar origen del lead.
- Guardar datos de contacto.
- Registrar notas internas.
- Asignar responsable.

Campos orientativos:

- Nombre.
- Teléfono.
- Email.
- Origen.
- Interés.
- Estado.
- Responsable.
- Fecha de creación.
- Próxima acción.

### 8.4 Pipeline comercial fitness

El sistema incluirá un pipeline adaptado al proceso de captación de un gimnasio.

Etapas propuestas:

1. Nuevo lead.
2. Contactado.
3. Visita o prueba agendada.
4. Prueba realizada.
5. Alta propuesta.
6. Convertido a socio.
7. Perdido.

Funcionalidades incluidas:

- Visualización de leads por etapa.
- Cambio de etapa.
- Motivo de pérdida.
- Registro de tareas asociadas al lead.
- Conversión de lead a socio.

### 8.5 Conversión de lead a socio

Cuando un lead decide darse de alta, el sistema permitirá convertirlo en socio sin duplicar información.

Funcionalidades incluidas:

- Crear socio a partir de lead.
- Mantener datos de contacto.
- Cambiar estado del lead a convertido.
- Asociar plan de membresía inicial.
- Registrar fecha de alta.

### 8.6 Gestión de socios

El sistema permitirá administrar la información principal de los socios del gimnasio.

Funcionalidades incluidas:

- Crear socio.
- Editar socio.
- Consultar listado de socios.
- Ver ficha 360º del socio.
- Consultar estado del socio.
- Consultar membresía activa.
- Consultar pagos.
- Consultar reservas y asistencias.
- Consultar tareas y alertas asociadas.

Estados propuestos:

- Activo.
- Inactivo.
- En riesgo.
- Baja.
- Pendiente de pago.

### 8.7 Planes de membresía

El sistema permitirá definir planes comerciales del gimnasio.

Funcionalidades incluidas:

- Crear plan.
- Editar plan.
- Activar o desactivar plan.
- Definir precio mensual estimado.
- Definir duración o periodicidad.
- Definir descripción del plan.

Ejemplos de planes:

- Básico.
- Premium.
- Clases ilimitadas.
- Bono mensual.
- Entrenamiento funcional.

### 8.8 Asignación de membresías a socios

El sistema permitirá asignar una membresía a un socio.

Funcionalidades incluidas:

- Asociar socio a un plan.
- Definir fecha de inicio.
- Definir fecha de fin o renovación.
- Estado de la suscripción.
- Consulta de membresía activa.
- Detección de membresías vencidas.

Estados propuestos:

- Activa.
- Pendiente.
- Vencida.
- Cancelada.

### 8.9 Registro manual de pagos

El MVP no incluirá una pasarela de pagos real. Se registrarán pagos manuales para representar el estado de cobro de cada socio.

Funcionalidades incluidas:

- Crear pago.
- Asociar pago a socio.
- Asociar pago a membresía.
- Registrar importe.
- Registrar fecha.
- Registrar método de pago.
- Marcar como pagado, pendiente o vencido.
- Consultar pagos pendientes.

Métodos de pago propuestos:

- Efectivo.
- Tarjeta.
- Transferencia.
- Otro.

### 8.10 Clases y sesiones

El sistema permitirá crear sesiones de clases dirigidas.

Funcionalidades incluidas:

- Crear clase o sesión.
- Definir tipo de clase.
- Definir fecha y hora.
- Definir duración.
- Definir aforo máximo.
- Asignar entrenador.
- Consultar calendario de clases.
- Consultar ocupación.

Ejemplos de clases:

- Funcional.
- HIIT.
- Yoga.
- Pilates.
- Cycling.
- Fuerza.

### 8.11 Reservas y cancelaciones

El sistema permitirá gestionar reservas de socios en sesiones.

Funcionalidades incluidas:

- Crear reserva.
- Cancelar reserva.
- Consultar reservas por socio.
- Consultar reservas por clase.
- Controlar aforo disponible.
- Evitar reservas por encima del aforo.
- Registrar no-show de forma básica.

Estados propuestos:

- Reservada.
- Cancelada.
- Asistida.
- No-show.

### 8.12 Check-in manual y QR simple

El sistema incluirá registro de asistencia mediante dos vías:

- Check-in manual desde recepción.
- Check-in mediante QR simple asociado al socio o a la reserva.

Funcionalidades incluidas:

- Buscar socio y registrar entrada.
- Generar o mostrar QR identificativo.
- Registrar fecha y hora del check-in.
- Asociar check-in a socio.
- Asociar check-in a clase si corresponde.
- Consultar historial de asistencia.

El QR será una funcionalidad simple orientada a la demo y no estará conectado a hardware físico, tornos, RFID o Bluetooth.

### 8.13 Tareas comerciales y de retención

El sistema permitirá crear tareas relacionadas con leads o socios.

Funcionalidades incluidas:

- Crear tarea.
- Asignar responsable.
- Definir fecha de vencimiento.
- Marcar tarea como completada.
- Asociar tarea a lead o socio.
- Consultar tareas pendientes.
- Consultar tareas vencidas.

Ejemplos de tareas:

- Llamar a lead.
- Recordar prueba gratuita.
- Contactar socio inactivo.
- Revisar pago pendiente.
- Seguimiento post-alta.

### 8.14 Alertas básicas

El sistema generará alertas simples para ayudar a la retención y al control operativo.

Alertas propuestas:

- Socio con pago pendiente.
- Socio con membresía vencida.
- Socio sin check-in en los últimos días.
- Lead sin seguimiento.
- Tarea vencida.
- Clase con alta ocupación.

Estas alertas serán reglas simples, no modelos predictivos de inteligencia artificial.

### 8.15 Dashboard con KPIs

El sistema incluirá un panel inicial con indicadores clave del gimnasio.

KPIs propuestos:

- Socios activos.
- Altas del mes.
- Bajas del mes.
- Leads abiertos.
- Conversión lead-socio.
- Conversión prueba-alta.
- MRR estimado.
- ARPU estimado.
- Pagos pendientes.
- Asistencia semanal.
- Ocupación media de clases.
- No-shows.
- Socios inactivos.
- Socios en riesgo.
- Tareas vencidas.

## 9. Pantallas principales del MVP

Pantallas previstas:

1. Login.
2. Onboarding o selección del centro demo.
3. Dashboard.
4. Leads.
5. Pipeline comercial.
6. Ficha de lead.
7. Socios.
8. Ficha 360º de socio.
9. Membresías y planes.
10. Pagos.
11. Calendario de clases.
12. Reservas.
13. Check-in.
14. Tareas.
15. Alertas.
16. Configuración básica.

## 10. Alcance responsive

Membora CRM será una aplicación web responsive.

El diseño se optimizará para:

- Escritorio.
- Tablet.
- Teléfono móvil.

La versión móvil no será una app nativa, sino una adaptación responsive de la aplicación web. El objetivo es que recepción, entrenadores o responsables del centro puedan consultar información, registrar check-ins o revisar reservas desde el teléfono de forma cómoda.

## 11. Fuera del alcance del MVP

Para mantener el proyecto viable, se descartan expresamente del MVP las siguientes funcionalidades:

- App móvil nativa.
- Portal completo para socios.
- Rutinas de entrenamiento.
- Nutrición.
- Seguimiento deportivo avanzado.
- Wearables.
- Integración con Apple Health, Google Fit u otros dispositivos.
- Pasarela de pagos real.
- Stripe, Redsys o integración bancaria completa.
- SEPA completo.
- Facturación legal avanzada.
- Verifactu o TicketBAI completos.
- Control de acceso con tornos.
- RFID, Bluetooth o hardware físico.
- Inteligencia artificial predictiva real.
- POS.
- Inventario.
- Nóminas.
- Gestión avanzada de empleados.
- Multi-sede avanzada.
- Marketplace de entrenadores o profesionales.
- Chat interno avanzado.
- Automatizaciones complejas de marketing.

## 12. Funcionalidades futuras

Las siguientes funcionalidades podrán plantearse como líneas futuras:

- Portal responsive para socios.
- Reserva de clases por parte del socio.
- Lista de espera.
- Notificaciones por email.
- Plantillas de mensajes.
- Segmentación básica de socios.
- Exportación e importación CSV.
- Encuesta NPS simple.
- Reglas de automatización configurables.
- Integración con pasarela de pagos.
- Informes avanzados.
- Multi-sede.
- Integración con herramientas de marketing.
- App móvil nativa.

## 13. Stack tecnológico

El stack definido para el desarrollo será:

### Frontend

- React.
- Next.js.
- TypeScript.
- CSS Modules, Tailwind CSS o solución equivalente.
- Diseño responsive.

### Backend

- Node.js.
- NestJS.
- TypeScript.
- API REST.
- Validación de datos.
- Control de errores.
- Autenticación y autorización.

### Base de datos

- MariaDB.
- Prisma ORM.
- Modelo relacional.
- Separación por `tenant_id`.

### Autenticación

La opción recomendada para el MVP es:

- JWT.
- Contraseñas cifradas con bcrypt o argon2.
- Middleware de autenticación.
- Control de acceso por roles.

### Despliegue previsto

Opciones posibles:

- Frontend en Vercel.
- Backend en Render, Railway o plataforma equivalente.
- Base de datos MariaDB en Plesk o servicio compatible con MySQL/MariaDB.

La opción definitiva se concretará durante la fase de implementación.

## 14. Modelo de datos inicial

Entidades principales previstas:

- Tenant.
- User.
- Role.
- Lead.
- PipelineStage.
- Member.
- MembershipPlan.
- Subscription.
- Payment.
- ClassType.
- ClassSession.
- Reservation.
- CheckIn.
- Task.
- CommunicationLog.
- RiskAlert.
- AuditLog.

## 15. Gimnasio demo

Para pruebas, presentación y vídeo se utilizará un gimnasio ficticio.

Nombre propuesto:

**NexoFit Studio**

Datos demo previstos:

- Usuarios internos con distintos roles.
- Leads en diferentes etapas del pipeline.
- Socios activos e inactivos.
- Planes de membresía.
- Pagos pagados, pendientes y vencidos.
- Clases con reservas.
- Check-ins registrados.
- Tareas y alertas de retención.

## 16. Criterios de éxito del MVP

El MVP se considerará válido si permite demostrar:

- Inicio de sesión con roles.
- Separación de datos por gimnasio mediante `tenant_id`.
- Gestión completa de leads.
- Pipeline comercial funcional.
- Conversión de lead a socio.
- Gestión de socios y membresías.
- Registro manual de pagos.
- Creación de clases.
- Gestión de reservas.
- Check-in manual o QR.
- Tareas y alertas básicas.
- Dashboard con KPIs útiles.
- Interfaz responsive usable desde escritorio y teléfono.
- Código documentado y ejecutable.
- README completo.
- Datos demo para la presentación.

## 17. Entregables relacionados

El proyecto deberá preparar los siguientes entregables:

- Código fuente en GitHub.
- README.md completo.
- Instrucciones de instalación y ejecución.
- Usuario y contraseña de prueba.
- URL de despliegue si se realiza.
- Slides de presentación.
- Vídeo explicativo con captura de pantalla.
- Documentación complementaria en la carpeta `docs`.

## 18. Conclusión del alcance

Membora CRM se define como un CRM SaaS ligero y responsive para gimnasios y centros fitness pequeños o medianos. Su propuesta se centra en gestionar el ciclo de vida del socio desde la captación hasta la retención, integrando funcionalidades comerciales, operativas y analíticas básicas.

El alcance del MVP evita funcionalidades excesivamente complejas para mantener la viabilidad del desarrollo individual, pero conserva suficiente profundidad técnica y funcional para justificar un Trabajo de Fin de Máster de desarrollo de software.
