# Stripe Billing SaaS - Membora CRM

Fecha de actualizacion: 17/07/2026.

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

## 3. Variables de entorno

En `apps/crm/.env`:

```env
PAYMENTS_MODE="stripe_test"
STRIPE_PUBLISHABLE_KEY="pk_test_PEGAR_AQUI"
STRIPE_SECRET_KEY="sk_test_PEGAR_AQUI"
STRIPE_WEBHOOK_SECRET="whsec_PEGAR_AQUI"
```

Donde pegar cada valor:

- `STRIPE_PUBLISHABLE_KEY`: Stripe Dashboard > Developers > API keys > Publishable key.
- `STRIPE_SECRET_KEY`: Stripe Dashboard > Developers > API keys > Secret key, siempre `sk_test_...` en esta fase.
- `STRIPE_WEBHOOK_SECRET`: Stripe Dashboard > Developers > Webhooks > endpoint de Membora > Signing secret.

## 4. Price IDs

Cada plan local de `Admin CRM > Planes` tiene dos campos:

- `Stripe Price mensual`
- `Stripe Price anual`

Crear en Stripe un producto/precio para cada plan y pegar los IDs `price_...` en esos campos. No se deben inventar IDs.

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

1. El administrador configura planes locales con Price IDs.
2. Para una prueba tecnica se invoca la accion interna de checkout de una empresa; el boton ya no se muestra en el modal de suscripcion.
3. Membora crea/reutiliza `stripe_customer_id`.
4. Se crea una Checkout Session con `mode=subscription`.
5. Stripe redirige al checkout alojado.
6. Al completar, Membora no activa el acceso por la redireccion de exito.
7. El acceso se activa solo cuando llega un webhook valido.
8. `invoice.paid` marca empresa al dia, actualiza `access_until`, registra pago y factura local.
9. `invoice.payment_failed` marca el pago como vencido/error y no amplia acceso.
10. `customer.subscription.updated/deleted` sincroniza estado, cancelacion al final del periodo y `current_period_end`.

### Estado de la interfaz

La integracion de backend se conserva, pero la interfaz entregable no muestra actualmente:

- El bloque `Stripe Billing` ni el webhook en la pantalla de facturas.
- El boton `Checkout Stripe` en la suscripcion de empresa.
- El enlace `Cancelar renovacion` conectado directamente a Stripe.

La gestion visible usa el bloque `Gestion de renovacion` y los estados locales. Esta decision evita mezclar controles tecnicos de prueba con la administracion diaria y no elimina `StripeBilling.php`, las acciones internas ni `/stripe/webhook`.

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

1. Configurar `PAYMENTS_MODE=stripe_test`.
2. Pegar `sk_test_...`, `pk_test_...` y `whsec_...`.
3. Crear Price mensual/anual en Stripe.
4. Pegar Price IDs en el plan local.
5. Asignar ese plan a una empresa.
6. Invocar la accion interna `create_empresa_stripe_checkout` desde una prueba tecnica controlada; no hay un boton visible en la interfaz entregable.
7. Completar pago con `4242 4242 4242 4242`.
8. Revisar las tablas de facturas/cobros y el registro tecnico `stripe_events`; la pantalla de Facturas no muestra un bloque de diagnostico Stripe.
9. Confirmar que el evento `invoice.paid` queda procesado en `stripe_events`.
10. Confirmar que la empresa queda `PAID`, con `access_until` actualizado.

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
