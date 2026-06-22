# Membora CRM

**Membora CRM** es una plataforma web SaaS responsive para gimnasios, centros fitness y estudios deportivos pequeños o medianos. El proyecto se desarrolla como Trabajo de Fin de Máster y tiene como objetivo crear un CRM vertical capaz de centralizar la captación de leads, la gestión de socios, membresías, reservas, check-ins, pagos registrados y acciones básicas de retención.

No es una app móvil fitness para usuarios finales. Es una plataforma web de gestión para propietarios, recepción, comerciales y entrenadores.

## 1. Estado actual

Estado del proyecto:

```text
Backend MVP funcional desplegado en Plesk.
Frontend pendiente de implementación.
Diseño frontend pendiente de incorporar.
```

Backend desplegado:

```text
https://crm.josehurtado.dev/api
```

Health check:

```text
https://crm.josehurtado.dev/api/health
```

Repositorio:

```text
https://github.com/Jacketring/Membora-CRM.git
```

## 2. Descripción general

Membora CRM nace como respuesta a una necesidad habitual en gimnasios independientes: la gestión dispersa de leads, socios, pagos, clases y asistencia mediante hojas de cálculo, agendas manuales, WhatsApp o herramientas no especializadas.

La aplicación propone una solución más específica que un CRM generalista, pero más simple y viable que una suite fitness completa. El foco del MVP está en cubrir el ciclo principal del cliente dentro de un centro fitness:

```text
lead -> prueba -> alta -> socio -> membresía -> reserva -> check-in -> pago -> retención
```

## 3. Stack tecnológico

### Frontend previsto

- React.
- Next.js.
- TypeScript.
- Diseño responsive.
- Tailwind CSS o alternativa equivalente.

### Backend implementado

- Node.js.
- NestJS.
- TypeScript.
- API REST.
- JWT.
- bcryptjs para contraseñas demo.
- Prisma ORM.

### Base de datos

- MariaDB gestionada desde Plesk.
- Modelo relacional.
- Separación lógica por `tenantId`.

## 4. Funcionalidades del MVP

### Implementado en backend

- Login JWT.
- Roles básicos.
- Multiempresa con `tenantId`.
- Seed demo de NexoFit Studio.
- Gestión de leads.
- Pipeline comercial.
- Conversión de lead a socio.
- Listado y detalle de socios.
- Planes de membresía.
- Suscripciones.
- Pagos manuales.
- Tipos de clase.
- Sesiones de clase.
- Reservas.
- Check-ins.
- Tareas.
- Alertas.
- Dashboard con KPIs.

### Pendiente de frontend

- Interfaz web responsive.
- Pantalla de login.
- Dashboard visual.
- Pantallas de leads y pipeline.
- Pantallas de socios.
- Pantallas de membresías y pagos.
- Pantallas de clases, reservas y check-ins.
- Pantallas de tareas y alertas.

## 5. Funcionalidades fuera del MVP

Para mantener un alcance viable, el MVP no incluye:

- App móvil nativa.
- Rutinas de entrenamiento.
- Nutrición.
- Wearables.
- Seguimiento deportivo avanzado.
- Pasarela de pagos real.
- Integración bancaria.
- SEPA completo.
- Facturación legal avanzada.
- Verifactu o TicketBAI completos.
- Control de acceso con hardware.
- RFID, Bluetooth o tornos.
- Inteligencia artificial predictiva real.
- POS.
- Inventario.
- Nóminas.
- Multi-sede avanzada.
- Marketplace de profesionales.

## 6. Estructura del proyecto

```bash
membora-crm/
|-- backend/
|   |-- prisma/
|   |   |-- schema.prisma
|   |   |-- seed.js
|   |-- src/
|   |   |-- auth/
|   |   |-- check-ins/
|   |   |-- class-sessions/
|   |   |-- class-types/
|   |   |-- dashboard/
|   |   |-- leads/
|   |   |-- membership-plans/
|   |   |-- members/
|   |   |-- payments/
|   |   |-- pipeline-stages/
|   |   |-- prisma/
|   |   |-- reservations/
|   |   |-- risk-alerts/
|   |   |-- subscriptions/
|   |   |-- tasks/
|   |-- .env.example
|   |-- package.json
|
|-- docs/
|   |-- 01-alcance-mvp.md
|   |-- 04-modelo-datos.md
|   |-- 05-pruebas.md
|   |-- 06-api-backend.md
|
|-- README.md
|-- .gitignore
```

## 7. Instalación backend local

### 7.1 Requisitos

- Node.js 20 o superior.
- npm.
- MariaDB o MySQL compatible.
- Git.

### 7.2 Clonar repositorio

```bash
git clone https://github.com/Jacketring/Membora-CRM.git
cd Membora-CRM
```

### 7.3 Configurar backend

```bash
cd backend
npm install
cp .env.example .env
```

Variables mínimas:

```env
DATABASE_URL="mysql://usuario:password@localhost:3306/membora_crm"
JWT_SECRET="cambiar_este_valor_por_un_secreto_largo"
JWT_EXPIRES_IN="1d"
PORT=3001
```

Sincronizar base de datos:

```bash
npm exec prisma db push -- --schema prisma/schema.prisma
```

Generar datos demo:

```bash
npm run prisma:seed
```

Arrancar backend en desarrollo:

```bash
npm run start:dev
```

Backend local:

```text
http://localhost:3001/api
```

## 8. Despliegue actual

El backend está desplegado en Plesk bajo el subdominio:

```text
crm.josehurtado.dev
```

Configuración Plesk:

- Aplicación Node.js.
- Raíz de aplicación: `backend`.
- Archivo de inicio: `dist/main.js`.
- Base de datos: MariaDB.
- Prisma conectado mediante `DATABASE_URL`.

Flujo de despliegue usado:

```bash
npm install --include=dev
npm run build
npm run prisma:seed
```

Después de cada pull/build en Plesk se debe reiniciar la app Node.js.

## 9. Datos demo

Tenant demo:

```text
NexoFit Studio
Slug: nexofit-studio
```

El seed incluye:

- Roles.
- Usuarios internos.
- Leads en distintas fases.
- Socios activos, en riesgo y con pagos pendientes.
- Planes de membresía.
- Suscripciones.
- Pagos pagados, pendientes y vencidos.
- Tipos de clase.
- Sesiones.
- Reservas.
- Check-ins.
- Tareas.
- Alertas.

## 10. Credenciales de prueba

```text
Administrador
Email: admin@nexofit.demo
Password: MemboraDemo2026!

Recepción / Comercial
Email: recepcion@nexofit.demo
Password: MemboraDemo2026!

Entrenador
Email: entrenador@nexofit.demo
Password: MemboraDemo2026!

Superadmin
Email: superadmin@membora.demo
Password: MemboraDemo2026!
```

## 11. Endpoints principales

Documentación completa:

```text
docs/06-api-backend.md
```

Rutas principales:

- `GET /api/health`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `GET /api/dashboard`
- `GET /api/pipeline-stages`
- `GET /api/leads`
- `POST /api/leads`
- `PATCH /api/leads/:id`
- `POST /api/leads/:id/convert`
- `POST /api/leads/:id/revert-conversion`
- `DELETE /api/leads/:id`
- `GET /api/members`
- `GET /api/membership-plans`
- `GET /api/subscriptions`
- `POST /api/subscriptions`
- `GET /api/payments`
- `POST /api/payments`
- `GET /api/class-types`
- `GET /api/class-sessions`
- `POST /api/class-sessions`
- `GET /api/reservations`
- `POST /api/reservations`
- `GET /api/check-ins`
- `POST /api/check-ins`
- `GET /api/tasks`
- `POST /api/tasks`
- `PATCH /api/tasks/:id`
- `GET /api/risk-alerts`
- `PATCH /api/risk-alerts/:id`

## 12. Scripts backend

```bash
npm run build
npm run start:dev
npm run start:prod
npm run prisma:generate
npm run prisma:seed
npm run prisma:studio
```

## 13. Documentación

- `docs/01-alcance-mvp.md`: alcance funcional del MVP.
- `docs/04-modelo-datos.md`: modelo relacional inicial y decisiones de datos.
- `docs/05-pruebas.md`: pruebas manuales backend y plan de pruebas.
- `docs/06-api-backend.md`: documentación de endpoints backend.

## 14. Seguridad básica

Medidas aplicadas o previstas:

- Contraseñas cifradas con bcryptjs en datos demo.
- Autenticación con JWT.
- Rutas protegidas mediante guard.
- Separación de datos por `tenantId`.
- Validación de relaciones dentro del mismo tenant.
- Variables sensibles fuera del repositorio.
- `.env` ignorado por Git.

## 15. Próximos pasos

Prioridad inmediata:

1. Cerrar revisión backend.
2. Crear frontend con Next.js.
3. Integrar diseño visual cuando esté disponible.
4. Conectar login real.
5. Construir dashboard.
6. Construir pantallas principales del MVP.
7. Pulir responsive.
8. Preparar slides y vídeo final.

## 16. Presentación

URL de slides:

```text
Pendiente de definir.
```

## 17. Vídeo explicativo

URL del vídeo:

```text
Pendiente de definir.
```

## 18. Autor

Proyecto desarrollado como Trabajo de Fin de Máster.

```text
Nombre: <NOMBRE_COMPLETO>
Email: <EMAIL_DEL_MASTER>
Portfolio: https://josehurtado.dev/
```

## 19. Licencia

Este proyecto se desarrolla con finalidad académica.

La licencia definitiva se definirá antes de publicar el repositorio.
