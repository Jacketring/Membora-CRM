# Alcance del MVP - Membora CRM

Fecha de actualizacion: 30/06/2026.

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
- Configuracion visual basica.

### Administracion SaaS

- Panel `Admin CRM`.
- Resumen con MRR, ARR, ARPA, pagos y prioridades.
- Contactos unificados con leads web y clientes comerciales.
- Conversion de lead web a cliente comercial.
- Alta y edicion de clientes comerciales desde la misma tabla de contactos.
- Empresas cliente.
- Creacion de tenant y usuario administrador al crear una empresa CRM.
- Estados de CRM y pago.
- Plan de prueba configurable por dias, sin proximo pago visible solo cuando el plan seleccionado es `Prueba`.
- Planes comerciales SaaS.
- Pagos SaaS por empresa.
- Web comercial: diagnostico de webhook, correo y ultimos envios.
- Acceso de soporte al CRM de una empresa conectada.

### Web comercial

- Web estatica desplegable en `web-app/public`.
- Acceso a demo funcional del CRM desde la web publica, con sesion temporal de 20 minutos.
- Formulario publico conectado al webhook del CRM.
- Email HTML de confirmacion cuando SMTP esta configurado.

## 4. Fuera del alcance actual

Quedan como mejora futura o no estan cerrados como modulo completo de gimnasio:

- Portal para socios.
- App movil nativa.
- Pasarela de pagos real.
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
https://app.crm.josehurtado.dev
Document root: php-app/public
```

Web comercial:

```text
https://app.web.josehurtado.dev
Document root: web-app/public
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

La tabla `empresas` incluye `trial_days` para controlar la duracion de la prueba comercial. Si el plan de una empresa es `TRIAL`, el CRM oculta la fecha de proximo pago, no marca renovacion y muestra la duracion de prueba configurada.

## 8. Recorrido recomendado de demo

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

## 9. Criterios de aceptacion del MVP

- La aplicacion carga desde Plesk sin Node.js.
- El login funciona con credenciales demo.
- Los datos de gimnasio se filtran por `tenant_id`.
- Los formularios principales crean y editan datos sin errores 500.
- La web publica registra leads en `Admin CRM > Contactos`.
- La documentacion explica instalacion, despliegue, credenciales, modelo de datos, pruebas y estado actual.
