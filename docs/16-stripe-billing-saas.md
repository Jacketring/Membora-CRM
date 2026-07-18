# Stripe Billing SaaS - Membora CRM

Fecha de actualizacion: 18/07/2026.

## 1. Alcance

Esta integracion cubre los cobros que realizan los gimnasios por usar Membora CRM. No afecta a los pagos internos de socios del gimnasio.

El modo inicial soportado es:

```env
PAYMENTS_MODE="stripe_test"
```

No se deben usar claves de produccion durante desarrollo.

## 2. Diagnostico inicial

Antes de integrar Stripe, Membora CRM ya tenia:

- Empresas SaaS en `empresas`.
- Clientes comerciales en `platform_clients`.
- Planes comerciales en `saas_plans`.
- Pagos administrativos en `empresa_payments`.
- Facturas administrativas en `platform_invoices`.
- Control de acceso por `access_until`, `payment_status`, `renewal_period` y `renewal_status`.

La integracion reutiliza esas tablas y solo anade campos Stripe. La unica tabla nueva es `stripe_events`, necesaria para idempotencia de webhooks mediante `stripe_event_id` unico.

### Catalogo comercial canonico

`saas_plans` es la fuente de ejecucion para el panel de administracion, `/app/api/plans`, la landing y el checkout. Los cuatro planes publicos, con precios mensuales sin IVA, son:

| Codigo | Nombre | Precio/mes | Usuarios | Socios |
| --- | --- | ---: | ---: | ---: |
| `BASIC` | Basic | 49 EUR | 3 | 300 |
| `PRO` | Pro | 89 EUR | 8 | 1.000 |
| `BUSINESS` | Business | 149 EUR | 20 | 3.000 |
| `ENTERPRISE` | Enterprise | 299 EUR | Sin limite | Sin limite |

La web consulta primero la API. Solo si fallan tanto el proxy publico como `/app/api/plans` utiliza un fallback con este mismo catalogo.

## 3. Variables de entorno

En `apps/crm/.env`:

```env
PAYMENTS_MODE="stripe_test"
CHECKOUT_PROVIDER="stripe"
APP_URL="https://membora.es/app"
STRIPE_PUBLISHABLE_KEY="pk_test_PEGAR_AQUI"
STRIPE_SECRET_KEY="sk_test_PEGAR_AQUI"
STRIPE_WEBHOOK_SECRET="whsec_PEGAR_AQUI"
```

Las URLs de retorno de Stripe usan el dominio real y permitido desde el que el administrador inicio
el checkout. De este modo, un `APP_URL` antiguo no puede enviar al usuario a otro dominio al terminar.

Antes de generar facturas de demostracion, completar tambien las variables `INVOICE_ISSUER_*` con los datos fiscales del emisor del entorno. El codigo no contiene datos fiscales reales; estas variables alimentan la instantanea del emisor que queda guardada en cada factura.

Donde pegar cada valor:

- `CHECKOUT_PROVIDER=simulated`: usa el checkout interno con tarjeta ficticia, sin contactar con Stripe ni bancos. Solo debe habilitarse expresamente para demostraciones aisladas.
- `CHECKOUT_PROVIDER=stripe`: recupera Stripe Checkout y exige todas las claves y el webhook configurados.

- `STRIPE_PUBLISHABLE_KEY`: Stripe Dashboard > Developers > API keys > Publishable key.
- `STRIPE_SECRET_KEY`: Stripe Dashboard > Developers > API keys > Secret key, siempre `sk_test_...` en esta fase.
- `STRIPE_WEBHOOK_SECRET`: Stripe Dashboard > Developers > Webhooks > endpoint de Membora > Signing secret.

## 4. Price IDs

Cada plan local de `Admin CRM > Planes` tiene dos campos:

- `Stripe Price o producto mensual`
- `Stripe Price o producto anual`

Crear en Stripe un producto con sus precios recurrentes para cada plan. Se puede pegar directamente el ID `price_...` de la tarifa o el ID `prod_...` del producto. Cuando recibe un `prod_...`, Membora consulta Stripe y selecciona la tarifa activa de intervalo mensual o anual segun el campo. No se deben inventar IDs ni mezclar objetos del modo test y live.

## 5. URL del webhook

Endpoint exacto:

```text
https://membora.es/app/stripe/webhook
```

Si `APP_URL` cambia, la URL sera:

```text
{APP_URL}/stripe/webhook
```

## 6. Eventos Stripe seleccionados

En Stripe Dashboard > Developers > Webhooks, seleccionar:

```text
checkout.session.completed
invoice.paid
invoice.payment_failed
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
```

El webhook verifica obligatoriamente la cabecera `Stripe-Signature`.

## 7. Flujo funcional

### Checkout interno de demostracion

Con `PAYMENTS_MODE=stripe_test` y `CHECKOUT_PROVIDER=simulated`, el administrador de una empresa `TRIAL`, Basic, Pro o Business puede completar el pago dentro de Membora con la tarjeta ficticia `4242 4242 4242 4242`, una caducidad futura y cualquier CVC ficticio de tres cifras. El flujo no llama a Stripe, no contacta con bancos y descarta los campos de tarjeta inmediatamente; tambien los censura en auditoria. Al confirmar, crea transaccionalmente un pago y un justificante con metodo `SIMULATED`, activa el plan y actualiza el acceso mensual o anual. Estos registros son exclusivamente demostrativos y no acreditan un cobro real.

La pantalla marca el plan contratado como `PLAN ACTUAL`. La jerarquia permitida es:

- `TRIAL` puede elegir cualquier plan de pago.
- Basic puede subir a Pro, Business o Enterprise.
- Pro puede subir a Business o Enterprise.
- Business solo puede subir a Enterprise.
- Enterprise no puede mejorar porque es el nivel maximo.

La vista, la accion que abre el checkout y el servicio que lo completa validan esta jerarquia. Stripe Checkout alojado permanece limitado al alta desde `TRIAL`: actualizar una suscripcion Stripe existente requiere un flujo especifico de cambio de Price y prorrateo, y no se simula creando una segunda suscripcion.

### Stripe Checkout

1. El administrador cambia `CHECKOUT_PROVIDER` a `stripe` y configura planes locales con Price IDs.
2. Una empresa `TRIAL` ve en la parte superior los dias restantes y pulsa `Mejorar el plan`.
3. Todos los roles pueden consultar los planes pagados, pero solo `GYM_ADMIN` puede iniciar el cobro.
4. El administrador elige plan y periodicidad; Membora obtiene la empresa desde el `tenant_id` de la sesion y guarda la eleccion como pendiente.
5. Stripe crea/reutiliza `stripe_customer_id` y abre Checkout alojado para recoger la forma de pago.
6. La redireccion de exito verifica la sesion directamente en Stripe y puede completar la activacion.
7. `invoice.paid` aplica el plan pendiente, marca la empresa al dia, actualiza `access_until` y crea pago y factura local.
8. La URL de retorno consulta tambien la sesion pagada en Stripe y aplica la misma sincronizacion idempotente, como respaldo si el webhook se retrasa.
9. `invoice.payment_failed` registra el intento vencido/error y no amplia acceso.
10. `customer.subscription.updated/deleted` sincroniza estado, cancelacion al final del periodo y `current_period_end`.

### Estado de la interfaz

La integracion de backend se conserva, pero la interfaz entregable no muestra actualmente:

- El bloque `Stripe Billing` ni el webhook en la pantalla de facturas.
- El boton `Checkout Stripe` en la suscripcion de empresa.
- El enlace `Cancelar renovacion` conectado directamente a Stripe.

La gestion visible usa el bloque `Gestion de renovacion` y los estados locales. Esta decision evita mezclar controles tecnicos de prueba con la administracion diaria y no elimina `StripeBilling.php`, las acciones internas ni `/stripe/webhook`.

Esta ocultacion se refiere al panel del superadministrador. La pantalla `Mejorar el plan` del tenant permite convertir una prueba en suscripcion pagada y, con el proveedor simulado, subir desde Basic, Pro o Business. No muestra IDs, secretos ni diagnosticos Stripe.

## 8. Migracion

Archivo opcional:

```text
docs/15-migracion-stripe-billing.sql
```

La app tambien crea campos incrementalmente desde PHP cuando carga la integracion.

## 9. Plesk

Pasos recomendados:

1. Subir cambios desde Git.
2. Entrar por SSH o terminal de Plesk.
3. Ir a:

```bash
cd apps/crm
composer install --no-dev --prefer-dist
```

4. Editar `apps/crm/.env` y pegar claves test.
5. Confirmar que `APP_URL` apunta al dominio real del CRM.
6. Configurar webhook en Stripe con `{APP_URL}/stripe/webhook`.
7. Configurar los Price IDs en `Admin CRM > Planes`.

Si Plesk no permite Composer, generar `apps/crm/vendor` en local con Composer y subir esa carpeta por SFTP, sin guardar claves en el repositorio.

## 10. Prueba completa

Tarjeta test:

```text
4242 4242 4242 4242
```

Datos:

- Fecha futura cualquiera.
- CVC cualquiera de 3 digitos.
- Codigo postal cualquiera.

Prueba:

1. Configurar `PAYMENTS_MODE=stripe_test` y `CHECKOUT_PROVIDER=stripe`.
2. Pegar `sk_test_...`, `pk_test_...` y `whsec_...`.
3. Crear Price mensual/anual en Stripe.
4. Pegar Price IDs en el plan local.
5. Entrar como administrador de una empresa `TRIAL` o de una empresa activada previamente sin suscripcion Stripe, y abrir `Mejorar el plan`.
6. Elegir el plan y pulsar `Pagar mensualmente` o `Pagar anualmente`.
7. Completar pago con `4242 4242 4242 4242`.
8. Revisar las tablas de facturas/cobros y el registro tecnico `stripe_events`; la pantalla de Facturas no muestra un bloque de diagnostico Stripe.
9. Confirmar que el evento `invoice.paid` queda procesado en `stripe_events`.
10. Confirmar que la empresa queda `PAID`, con `access_until` actualizado.

Una empresa activada previamente mediante el checkout simulado puede contratar un plan superior con
Stripe Checkout si todavia no tiene `stripe_subscription_id`. El sistema rechaza crear una segunda
suscripcion cuando ya existe una suscripcion Stripe vinculada.

Prueba de fallo:

```text
4000 0000 0000 9995
```

Debe generar `invoice.payment_failed`, no ampliar acceso y dejar error visible.

## 11. Paso posterior a produccion

Cuando se valide el flujo:

1. Crear productos y precios reales en Stripe Live.
2. Cambiar claves a `pk_live_...` y `sk_live_...`.
3. Crear webhook live y pegar su `whsec_...`.
4. Cambiar `PAYMENTS_MODE` a un modo de produccion que se habilite expresamente en codigo.
5. Revisar fiscalidad, IVA, facturas oficiales y Verifactu antes de emitir facturacion real certificada.
6. Hacer prueba con un pago real pequeno.
7. Activar monitorizacion de webhooks fallidos en Stripe.

Actualmente el codigo bloquea claves que no empiecen por `sk_test_` para evitar usar produccion por error.
