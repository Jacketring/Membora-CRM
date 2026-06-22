# API backend - Membora CRM

## 1. Introduccion

Este documento recoge los endpoints backend implementados para el MVP de Membora CRM.

La API esta construida con NestJS, Prisma y MariaDB. Todas las rutas de negocio estan protegidas con JWT y aplican aislamiento por `tenantId`.

URL base en despliegue:

```text
https://crm.josehurtado.dev/api
```

URL base local prevista:

```text
http://localhost:3001/api
```

## 2. Autenticacion

La autenticacion usa JWT.

Para acceder a rutas protegidas se debe enviar:

```http
Authorization: Bearer <ACCESS_TOKEN>
```

Credenciales demo:

```text
Admin gimnasio
Email: admin@nexofit.demo
Password: MemboraDemo2026!

Recepcion / comercial
Email: recepcion@nexofit.demo
Password: MemboraDemo2026!

Entrenador
Email: entrenador@nexofit.demo
Password: MemboraDemo2026!

Superadmin
Email: superadmin@membora.demo
Password: MemboraDemo2026!
```

Nota: las rutas de negocio actuales requieren usuario con `tenantId`. El superadmin global existe para el modelo SaaS, pero no se usa todavia para operar sobre datos de un gimnasio.

## 3. Reglas multiempresa

Reglas aplicadas:

- El `tenantId` se obtiene desde el JWT.
- El frontend no debe enviar `tenantId` para crear o consultar datos.
- Las rutas de negocio filtran siempre por el `tenantId` del usuario autenticado.
- Las relaciones se validan dentro del mismo tenant antes de crear datos.
- Si un usuario no tiene `tenantId`, la ruta de negocio responde con error de permisos.

## 4. Health

### GET `/health`

Comprueba que el backend esta levantado.

No requiere JWT.

Respuesta ejemplo:

```json
{
  "status": "ok",
  "service": "membora-crm-backend",
  "timestamp": "2026-06-22T05:50:33.612Z"
}
```

## 5. Auth

### POST `/auth/login`

Inicia sesion y devuelve token JWT.

Body:

```json
{
  "email": "admin@nexofit.demo",
  "password": "MemboraDemo2026!"
}
```

Respuesta:

```json
{
  "accessToken": "jwt...",
  "user": {
    "id": "user_id",
    "tenantId": "tenant_id",
    "tenantName": "NexoFit Studio",
    "role": "GYM_ADMIN",
    "name": "Laura Martin",
    "email": "admin@nexofit.demo"
  }
}
```

### GET `/auth/me`

Devuelve el usuario autenticado desde el token.

Requiere JWT.

## 6. Dashboard

### GET `/dashboard`

Devuelve KPIs principales y listas resumidas para la pantalla inicial.

Requiere JWT.

KPIs incluidos:

- `activeMembers`
- `openLeads`
- `newMembersThisMonth`
- `pendingPayments`
- `overduePayments`
- `overdueTasks`
- `upcomingReservations`
- `weeklyCheckIns`
- `openAlerts`
- `estimatedMrr`

Tambien devuelve:

- `recentLeads`
- `upcomingTasks`
- `openRiskAlerts`

## 7. Pipeline stages

### GET `/pipeline-stages`

Devuelve las etapas comerciales del tenant autenticado.

Requiere JWT.

Respuesta ejemplo:

```json
[
  {
    "id": "stage_id",
    "name": "Nuevo lead",
    "key": "NEW_LEAD",
    "order": 1,
    "isFinal": false
  }
]
```

## 8. Leads

### GET `/leads`

Lista leads del tenant autenticado.

Requiere JWT.

Incluye:

- etapa de pipeline
- usuario asignado

### GET `/leads/:id`

Devuelve detalle de un lead del tenant.

Requiere JWT.

Incluye:

- etapa de pipeline
- usuario asignado
- tareas
- comunicaciones

### POST `/leads`

Crea un lead.

Requiere JWT.

Body minimo:

```json
{
  "pipelineStageId": "stage_id",
  "firstName": "Lucia"
}
```

Body completo ejemplo:

```json
{
  "pipelineStageId": "stage_id",
  "assignedUserId": "user_id",
  "firstName": "Lucia",
  "lastName": "Romero",
  "email": "lucia.romero@example.com",
  "phone": "+34 688 888 888",
  "source": "WALK_IN",
  "interest": "Prueba gratuita",
  "nextActionAt": "2026-06-24T10:00:00.000Z"
}
```

Reglas:

- `pipelineStageId` debe pertenecer al mismo tenant.
- `assignedUserId`, si se envia, debe pertenecer al mismo tenant.
- `tenantId` se asigna desde el token.

### PATCH `/leads/:id`

Actualiza un lead o lo mueve de etapa.

Requiere JWT.

Body ejemplo:

```json
{
  "pipelineStageId": "stage_contacted_id",
  "status": "OPEN"
}
```

### POST `/leads/:id/convert`

Convierte un lead en socio.

Requiere JWT.

Reglas:

- El lead debe pertenecer al tenant.
- El lead no debe estar convertido previamente.
- Crea un `Member` con los datos del lead.
- Guarda `leadId` en `Member`.
- Cambia el lead a `CONVERTED`.
- Mueve el lead a la etapa `CONVERTED`.

### POST `/leads/:id/revert-conversion`

Revierte una conversion hecha por error.

Requiere JWT.

Reglas:

- El lead debe pertenecer al tenant.
- El lead debe estar convertido o tener un socio vinculado.
- Cancela y desvincula el socio generado desde el lead.
- Cambia el lead de nuevo a `OPEN`.
- Mueve el lead a una etapa comercial abierta.

### DELETE `/leads/:id`

Elimina un lead del tenant.

Requiere JWT.

Reglas:

- El lead debe pertenecer al tenant.
- Si el lead tiene un socio vinculado, primero debe revertirse la conversion.
- Elimina tareas, alertas y comunicaciones asociadas al lead.

## 9. Members

### GET `/members`

Lista socios del tenant.

Requiere JWT.

Incluye:

- lead de origen
- suscripciones
- plan asociado

### GET `/members/:id`

Devuelve ficha completa del socio.

Requiere JWT.

Incluye:

- lead de origen
- suscripciones
- pagos
- reservas
- check-ins
- tareas
- comunicaciones
- alertas

## 10. Membership plans

### GET `/membership-plans`

Lista planes de membresia del tenant.

Requiere JWT.

## 11. Subscriptions

### GET `/subscriptions`

Lista suscripciones del tenant.

Requiere JWT.

Incluye:

- socio
- plan
- pagos

### POST `/subscriptions`

Asigna un plan a un socio.

Requiere JWT.

Body:

```json
{
  "memberId": "member_id",
  "membershipPlanId": "plan_id",
  "status": "ACTIVE",
  "startDate": "2026-06-22T00:00:00.000Z"
}
```

Reglas:

- El socio debe pertenecer al tenant.
- El plan debe pertenecer al tenant y estar activo.
- No se permite otra suscripcion activa para el mismo socio.
- Si no se envia `endDate`, se calcula con `durationDays` del plan.

## 12. Payments

### GET `/payments`

Lista pagos del tenant.

Requiere JWT.

Incluye:

- socio
- suscripcion
- plan asociado

### POST `/payments`

Registra un pago manual.

Requiere JWT.

Body:

```json
{
  "memberId": "member_id",
  "subscriptionId": "subscription_id",
  "amount": "49.90",
  "currency": "EUR",
  "paymentMethod": "CARD",
  "status": "PAID",
  "paidAt": "2026-06-22T10:00:00.000Z",
  "notes": "Pago mensual"
}
```

Reglas:

- El socio debe pertenecer al tenant.
- La suscripcion, si se envia, debe pertenecer al mismo socio y tenant.
- `amount` debe ser mayor que cero.
- Si `status` es `PAID` y no se envia `paidAt`, se usa la fecha actual.

## 13. Class types

### GET `/class-types`

Lista tipos de clase del tenant.

Requiere JWT.

## 14. Class sessions

### GET `/class-sessions`

Lista sesiones de clase del tenant.

Requiere JWT.

Incluye:

- tipo de clase
- entrenador
- reservas

### POST `/class-sessions`

Crea una sesion de clase.

Requiere JWT.

Body:

```json
{
  "classTypeId": "class_type_id",
  "trainerUserId": "trainer_user_id",
  "startsAt": "2026-06-25T18:00:00.000Z",
  "endsAt": "2026-06-25T19:00:00.000Z",
  "capacity": 12,
  "status": "SCHEDULED"
}
```

Reglas:

- El tipo de clase debe pertenecer al tenant y estar activo.
- El entrenador, si se envia, debe pertenecer al tenant y tener rol `TRAINER`.
- `capacity` debe ser mayor que cero.
- `endsAt` debe ser posterior a `startsAt`.

## 15. Reservations

### GET `/reservations`

Lista reservas del tenant.

Requiere JWT.

Incluye:

- socio
- sesion
- tipo de clase
- entrenador

### POST `/reservations`

Crea una reserva.

Requiere JWT.

Body:

```json
{
  "memberId": "member_id",
  "classSessionId": "class_session_id"
}
```

Reglas:

- El socio debe pertenecer al tenant.
- La sesion debe pertenecer al tenant.
- La sesion debe estar `SCHEDULED`.
- No se permite duplicado activo del mismo socio en la misma sesion.
- No se permite superar el aforo.

## 16. Check-ins

### GET `/check-ins`

Lista check-ins del tenant.

Requiere JWT.

### POST `/check-ins`

Registra un check-in manual o QR.

Requiere JWT.

Body con reserva:

```json
{
  "memberId": "member_id",
  "reservationId": "reservation_id",
  "method": "QR"
}
```

Body sin reserva:

```json
{
  "memberId": "member_id",
  "method": "MANUAL"
}
```

Reglas:

- El socio debe pertenecer al tenant.
- La reserva, si se envia, debe pertenecer al mismo socio y tenant.
- La sesion, si se envia, debe pertenecer al tenant.
- No se permite check-in duplicado para una reserva.
- Si hay reserva, esta pasa a `ATTENDED`.

## 17. Tasks

### GET `/tasks`

Lista tareas del tenant.

Requiere JWT.

Incluye:

- usuario asignado
- lead asociado
- socio asociado

### POST `/tasks`

Crea una tarea.

Requiere JWT.

Body:

```json
{
  "assignedUserId": "user_id",
  "leadId": "lead_id",
  "title": "Llamar al lead",
  "description": "Confirmar interes y agendar prueba",
  "type": "SALES",
  "status": "PENDING",
  "dueAt": "2026-06-24T10:00:00.000Z"
}
```

Reglas:

- El usuario asignado debe pertenecer al tenant.
- El lead, si se envia, debe pertenecer al tenant.
- El socio, si se envia, debe pertenecer al tenant.

### PATCH `/tasks/:id`

Actualiza una tarea.

Requiere JWT.

Body ejemplo:

```json
{
  "status": "COMPLETED"
}
```

Si una tarea pasa a `COMPLETED` y no se envia `completedAt`, se asigna la fecha actual.

## 18. Risk alerts

### GET `/risk-alerts`

Lista alertas del tenant.

Requiere JWT.

Incluye:

- lead asociado
- socio asociado
- tarea asociada

### PATCH `/risk-alerts/:id`

Actualiza una alerta.

Requiere JWT.

Body:

```json
{
  "status": "RESOLVED"
}
```

Si una alerta pasa a `RESOLVED` y no se envia `resolvedAt`, se asigna la fecha actual.

## 19. Flujo recomendado de prueba manual

1. `GET /health`
2. `POST /auth/login`
3. Copiar `accessToken`.
4. `GET /dashboard`
5. `GET /pipeline-stages`
6. `POST /leads`
7. `PATCH /leads/:id`
8. `POST /leads/:id/convert`
9. `GET /members`
10. `GET /membership-plans`
11. `POST /subscriptions`
12. `POST /payments`
13. `GET /class-types`
14. `GET /class-sessions`
15. `POST /reservations`
16. `POST /check-ins`
17. `POST /tasks`
18. `GET /risk-alerts`
19. `GET /dashboard`

## 20. Enums principales

Roles:

```text
SUPERADMIN
GYM_ADMIN
SALES_RECEPTION
TRAINER
```

Estados relevantes:

```text
LeadStatus: OPEN, CONVERTED, LOST
MemberStatus: ACTIVE, INACTIVE, AT_RISK, CANCELLED, PAYMENT_PENDING
SubscriptionStatus: ACTIVE, PENDING, EXPIRED, CANCELLED
PaymentStatus: PAID, PENDING, OVERDUE
ReservationStatus: RESERVED, CANCELLED, ATTENDED, NO_SHOW
CheckInMethod: MANUAL, QR
TaskStatus: PENDING, COMPLETED, CANCELLED
RiskAlertStatus: OPEN, RESOLVED, DISMISSED
```

## 21. Notas pendientes

Pendientes razonables antes de una version final:

- Validacion con DTOs de clase usando `class-validator`.
- Filtros y paginacion en listados grandes.
- Tests automatizados de servicios principales.
- OpenAPI/Swagger si se quiere documentacion interactiva.
- Endpoints de administracion global para `SUPERADMIN`.
