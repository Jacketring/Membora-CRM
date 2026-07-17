# Alcance del MVP - Membora CRM

Fecha de actualizacion: 17/07/2026.

Este alcance constituye la primera fase de la metodología incremental descrita en `docs/19-metodologia-desarrollo.md`. Permite distinguir el MVP demostrable de las mejoras futuras antes de definir requisitos, historias y especificaciones.

## 1. Objetivo

Membora CRM es una aplicacion web SaaS responsive para gimnasios, estudios deportivos y centros fitness pequenos o medianos. El MVP centraliza la gestion comercial y operativa basica: leads, socios, membresias, clases, reservas, tareas, usuarios internos y administracion SaaS de empresas cliente.

La version final del proyecto se ha simplificado a una aplicacion PHP monolitica para facilitar el despliegue en Plesk, evitar procesos Node.js en produccion y reducir complejidad operativa.

## 2. Usuarios objetivo

- Propietario o administrador del gimnasio.
- Recepcion y equipo comercial.
- Entrenadores.
- Administrador de plataforma Membora CRM.
- Evaluador academico del TFM.

## 3. Alcance funcional implementado

### Gimnasio

- Login y cierre de sesion.
- Dashboard con KPIs principales.
- Buscador global.
- Leads con filtros, etapas, estados, notas y conversion a socio.
- Socios con foto, edicion, estado e historial de reservas.
- Planes de membresia con precio, periodicidad, duracion y estado.
- Asignacion de membresia a socios con calculo de caducidad.
- Pagos de socios con importe, metodo, estado, vencimiento, fecha de pago y notas.
- Integracion de facturacion externa generica con configuracion, exportacion CSV, sincronizacion simulada y logs.
- Check-ins manuales de socios, con asociacion opcional a reservas de clase.
- Alertas de riesgo para pagos vencidos, tareas vencidas, membresias caducadas o proximas a renovar, leads sin seguimiento, socios sin actividad y clases llenas.
- Auditoria de acciones internas con usuario, tenant, accion, entidad, ruta, IP, navegador y datos sanitizados.
- Tipos de clase.
- Sesiones de clase con fecha, hora, entrenador, aforo y estado.
- Calendario mensual de clases.
- Reservas de socios con control de aforo, asistencia, no-show y cancelacion.
- Tareas internas con usuario responsable, estado, fecha y seguimiento operativo.
- Usuarios internos del gimnasio.
- Permisos por rol para rutas y acciones internas.
- Perfil de usuario con imagen.
- Configuracion visual basica con modo claro por defecto y modo oscuro opcional para el usuario.
- Canal `Novedades` con version actual e historial de cambios visibles para usuarios autenticados.

### Administracion SaaS

- Panel `Admin CRM`.
- Resumen con MRR, ARR, ARPA, pagos y prioridades.
- Contactos unificados con leads web y clientes comerciales.
- Conversion de lead web a cliente comercial.
- Alta y edicion de contactos desde la misma tabla, incluyendo cambio de tipo para devolver un cliente a lead.
- Empresas cliente.
- Creacion de tenant y usuario administrador al crear una empresa CRM.
- Estados de CRM y pago.
- Plan de prueba configurable por dias, sin proximo pago visible solo cuando el plan seleccionado es `Prueba`.
- Gestion central de suscripcion SaaS por empresa cliente: fecha de alta, fecha desde la que paga, fecha de acceso hasta, plan, periodicidad mensual/anual, estado de renovacion, cancelacion al final del periodo y reactivacion.
- Bloqueo visual del CRM cliente cuando la demo o el acceso contratado han caducado, con modal para elegir plan y continuar el proceso de contratacion.
- Planes comerciales SaaS con precio mensual, alta, rebajas y sincronizacion publica con la web comercial.
- Pagos SaaS por empresa.
- Facturas SaaS emitidas por Membora a empresas cliente, con borradores, emision, serie/numero correlativo por empresa, datos historicos de emisor/cliente, lineas, descuentos, IVA desglosado, pagos parciales, estado de cobro y vista imprimible/PDF.
- Base funcional para checkout y cobros SaaS: la demo pide pocos datos y la contratacion debe completar datos fiscales, plan y forma de pago antes de activar el acceso.
- Diagnostico interno de webhook, correo y ultimos envios en una ruta directa exclusiva de superadministracion, oculta del menu por tratarse de una herramienta de depuracion.
- Acceso de soporte al CRM de una empresa conectada.

### Web comercial

- Web estatica desplegable en `httpdocs`.
- Acceso a demo funcional del CRM desde la web publica, con sesion temporal de 20 minutos.
- Seccion de planes cargada desde el catalogo activo de `Admin CRM > Planes`.
- Formulario publico conectado al webhook del CRM.
- Email HTML de confirmacion cuando SMTP esta configurado.

## 4. Fuera del alcance actual

Quedan como mejora futura o no estan cerrados como modulo completo de gimnasio:

- Portal para socios.
- App movil nativa.
- Pasarela de pagos real.
- Integracion Stripe real con claves de produccion, webhooks, suscripciones recurrentes y checkout alojado.
- Verifactu completo y envio fiscal certificado.
- Importacion/exportacion CSV avanzada.
- Multi-sede avanzada.

## 5. Stack final

- PHP 8.2 o superior.
- MariaDB/MySQL.
- PDO.
- HTML renderizado desde PHP.
- CSS propio.
- JavaScript de navegador.
- Apache/Plesk.
- Web comercial estatica separada.

No se usa en produccion:

- Node.js.
- Next.js.
- NestJS.
- Prisma.
- `npm install`.
- `npm run build`.

## 6. Arquitectura de despliegue

CRM:

```text
https://membora.es/app/
Document root unico: httpdocs (CRM publicado en /app/)
```

Web comercial:

```text
https://membora.es/
Document root: httpdocs
```

El CRM recibe solicitudes comerciales en:

```text
POST /webhook/lead
```

## 7. Modelo multiempresa

Los datos de gimnasio se separan mediante `tenant_id`. Cada usuario interno trabaja dentro de su gimnasio. El superadmin de plataforma gestiona datos globales y puede entrar en modo soporte sobre una empresa conectada.

Tablas operativas principales:

- `tenants`
- `users`
- `roles`
- `pipeline_stages`
- `leads`
- `members`
- `membership_plans`
- `subscriptions`
- `payments`
- `billing_integrations`
- `billing_sync_logs`
- `checkins`
- `class_types`
- `class_sessions`
- `reservations`
- `tasks`
- `risk_alerts`
- `audit_logs`

Tablas SaaS principales:

- `platform_leads`
- `platform_clients`
- `empresas`
- `empresa_payments`
- `saas_plans`
- `webhook_logs`

La tabla `empresas` incluye `trial_days` para controlar la duracion de la prueba comercial. Si el plan de una empresa es `TRIAL`, el CRM oculta la fecha de proximo pago, no marca renovacion y muestra la duracion de prueba configurada. Tambien centraliza la suscripcion SaaS con `subscription_started_at`, `paid_since`, `access_until`, `renewal_period`, `renewal_status` y `cancelled_at`, de forma que una empresa puede cancelar la renovacion y conservar acceso hasta la fecha contratada.

## 8. Stripe, cobros y checkout

Para los pagos de Membora se usa Stripe como proveedor principal. El MVP incluye una integracion funcional restringida a `stripe_test`: checkout alojado, webhooks firmados, suscripciones recurrentes, cancelacion al final del periodo, facturas, cobros e idempotencia. No se habilita Stripe Live ni se realizan cargos reales hasta completar la configuracion bancaria, fiscal y comercial.

El backend conserva las acciones de checkout y cancelacion Stripe para validacion tecnica, pero esos controles y el bloque de diagnostico no se muestran en las pantallas actuales de empresas o facturas. En la demo visible, el ciclo de vida se gestiona con el estado local de renovacion; Stripe debe presentarse como integracion tecnica en modo de prueba, no como cobro comercial activo.

Las empresas en prueba disponen de un recorrido visible independiente: un banner muestra los dias restantes, `Mejorar el plan` presenta exclusivamente planes de pago y el administrador del gimnasio completa el metodo de pago en Stripe Checkout. La seleccion no modifica el plan por la URL de retorno; queda pendiente hasta que `invoice.paid` confirma el cobro y crea el pago y la factura en Admin CRM.

Cuando Jose cree la cuenta de Stripe, debera configurar:

- Cuenta de Stripe y datos bancarios.
- Claves de prueba y de produccion.
- Productos y precios equivalentes a los planes activos de Membora.
- Webhooks para confirmar pagos, fallos de cobro, cancelaciones y renovaciones.
- URL de exito y cancelacion para el checkout.

El flujo objetivo de contratacion sera:

1. El usuario solicita demo con pocos datos, idealmente nombre y correo.
2. Al contratar, el sistema pide datos completos: datos fiscales, direccion, telefono, plan y forma de pago.
3. Se crea una sesion de checkout de Stripe.
4. Stripe confirma el pago mediante webhook.
5. Membora activa o renueva la empresa, actualiza `next_payment_at` y `access_until`, y registra el pago.
6. El usuario recibe una confirmacion clara: "Enhorabuena, ya tienes acceso y puedes usar la aplicacion".

Las renovaciones Stripe se sincronizan mediante webhooks y nunca se marcan como cobradas por la URL de retorno. Los pagos manuales de socios pueden generarse de forma recurrente dentro del CRM, pero no constituyen un cargo bancario automatico.

## 9. Facturacion de Membora y Verifactu

Membora necesita una zona de facturas para las facturas emitidas por Jose a los gimnasios que pagan el SaaS. Esta facturacion es distinta de la facturacion interna que cada gimnasio pueda llevar con sus propios socios.

La tabla de facturas SaaS debera mostrar como minimo:

- Fecha de emision.
- Fecha de vencimiento.
- Serie y numero de factura.
- Cliente o empresa.
- Suscripcion asociada.
- Concepto.
- Base imponible.
- IVA.
- Total.
- Forma de pago.
- Estado de cobro.

El sistema debera sugerir el siguiente numero disponible al crear una factura manual. En Espana se debe respetar una logica de serie y numero, por ejemplo `M-2026/0001`, `M-2026/0002`, manteniendo continuidad dentro de cada serie.

Tipos de factura previstos:

- Facturas automaticas por suscripcion SaaS.
- Facturas manuales por servicios puntuales: integracion a medida, web, desarrollo especial, migracion, soporte extraordinario u otros trabajos facturables.

Verifactu no se implementara desde cero dentro del MVP porque requiere cumplimiento legal, trazabilidad, firma/huella, codigos QR, registros inalterables y adaptacion a normativa fiscal. Segun la informacion de la Agencia Tributaria consultada el 10/07/2026, los plazos son:

- 1 de enero de 2027 para contribuyentes sujetos al Impuesto sobre Sociedades.
- 1 de julio de 2027 para el resto de contribuyentes.

La estrategia recomendada es documentar e integrar un proveedor especializado cuando se acerque la obligacion. Se deja como opcion futura revisar KubiFactu u otro proveedor equivalente, comparando coste por volumen, API, soporte de Verifactu, emision de facturas, rectificativas, estado de envio y exportacion contable.

Hasta entonces, el MVP puede guardar facturas internas y estados de cobro, pero no debe presentarse como sistema Verifactu certificado.

## 10. Recorrido recomendado de demo

1. Entrar como administrador de gimnasio.
2. Revisar dashboard.
3. Crear lead, anadir nota y convertirlo en socio.
4. Revisar el socio convertido y asignar membresia.
5. Crear una clase desde calendario.
6. Registrar un pago pendiente o pagado para un socio.
7. Configurar facturacion externa, exportar CSV y ejecutar sincronizacion simulada.
8. Registrar un check-in manual o asociado a reserva.
9. Revisar y resolver alertas de riesgo.
10. Reservar plaza para un socio y marcar asistencia/no-show.
11. Crear una tarea interna asignada a un usuario del equipo.
12. Revisar auditoria para comprobar las acciones registradas.
13. Revisar usuarios internos, perfil y configuracion.
14. Entrar como administrador de plataforma.
15. Revisar contactos, empresas, pagos, planes y web comercial.
16. Entrar en modo soporte sobre una empresa y volver a Admin CRM.

## 11. Criterios de aceptacion del MVP

- La aplicacion carga desde Plesk sin Node.js.
- El login funciona con credenciales demo.
- Los datos de gimnasio se filtran por `tenant_id`.
- Los formularios principales crean y editan datos sin errores 500.
- La web publica registra leads en `Admin CRM > Contactos`.
- La documentacion explica instalacion, despliegue, credenciales, modelo de datos, pruebas y estado actual.
