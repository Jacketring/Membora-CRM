# Membora CRM PHP

Aplicacion PHP monolitica para ejecutar Membora CRM en un unico subdominio, sin Next.js, NestJS ni procesos Node en produccion.

## Requisitos

- PHP 8.2 o superior.
- Extension PDO MySQL activada.
- MariaDB/MySQL existente.
- Apache con `mod_rewrite` activado.
- Document root apuntando a `php-app/public`.

## Configuracion

Crear `php-app/.env` a partir de `.env.example`.

Configuracion recomendada:

```env
APP_NAME="Membora CRM"
APP_ENV="production"
DB_HOST="localhost"
DB_PORT="3306"
DB_DATABASE="membora_crm"
DB_USERNAME="usuario"
DB_PASSWORD="password"
```

Tambien se admite:

```env
DATABASE_URL="mysql://usuario:password@localhost:3306/membora_crm"
```

## Despliegue en Plesk

1. Subir o actualizar el repositorio desde GitHub.
2. Configurar el subdominio para que el document root apunte a:

```text
php-app/public
```

3. Crear `php-app/.env` en el servidor con la conexion real a MariaDB.
4. Verificar que PHP usa una version 8.2 o superior.
5. Abrir el subdominio.

No hace falta ejecutar `npm install`, `npm run build`, `prisma generate` ni reiniciar una app Node para esta version PHP.

## Pantallas incluidas

- Login.
- Dashboard del gimnasio.
- Leads.
- Socios.
- Membresias.
- Clases y calendario.
- Tareas.
- Usuarios internos.
- Perfil.
- Configuracion visual.
- Panel de administracion SaaS con resumen, empresas, pagos y planes.

## Administracion SaaS

La app crea y usa tablas de administracion SaaS para controlar clientes, cobros y catalogo comercial:

- Empresa cliente.
- Plan.
- Estado del CRM.
- Estado de pago.
- Precio mensual.
- Proximo pago.
- Notas internas.
- Acceso de soporte al CRM de la empresa si tiene `tenant_id`.
- Pagos SaaS por empresa: concepto, importe, vencimiento, fecha de pago y estado.
- Planes SaaS: codigo, precio mensual, coste de alta, limites y prestaciones.

Usuario de administracion de plataforma:

```text
Email: admin@membora.crm
Password: MemboraAdmin2026!
```

## Automatismos de base de datos

La aplicacion crea algunas tablas o columnas auxiliares si no existen, por ejemplo:

- `empresas`.
- `empresa_payments`.
- `saas_plans`.
- `lead_notes`.
- `task_members`.
- `membership_plans`.
- `subscriptions`.
- `class_types`.
- `class_sessions`.
- columnas de imagen para usuarios/socios.

Esto permite desplegar cambios incrementales en Plesk sin ejecutar migraciones Node.
