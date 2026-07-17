# Historial de cambios recientes - Membora CRM

Fecha de actualizacion: 17/07/2026.

## 1. Objetivo

Este documento resume los cambios funcionales y tecnicos incorporados durante los ultimos dias de desarrollo. Sirve como memoria rapida para explicar que se ha construido, donde encaja dentro del MVP y que limites quedan pendientes.

No sustituye a los documentos principales de alcance, modelo de datos, pruebas o estado PHP. Los complementa con un historico ordenado de decisiones recientes.

## 2. Cambios de administracion SaaS

### Suscripciones de empresas cliente

Se ha ampliado la gestion de empresas para controlar mejor el ciclo de vida comercial del SaaS:

- Fecha de alta de la suscripcion.
- Fecha desde la que el cliente empieza a pagar.
- Fecha de acceso hasta la que puede usar el CRM.
- Periodicidad mensual o anual.
- Estado de renovacion.
- Cancelacion al final del periodo contratado.
- Reactivacion de empresas cuando procede.

El sistema usa esta informacion para distinguir entre prueba, cliente activo, acceso suspendido y cancelacion. Tambien permite bloquear visualmente el CRM del cliente cuando la demo o el periodo contratado han caducado.

### Acceso y bloqueo por suscripcion

Se ha incorporado control de acceso SaaS para empresas conectadas. Si una empresa no tiene acceso vigente, el CRM cliente muestra un bloqueo visual con un modal orientado a elegir plan o continuar el proceso de contratacion.

Este bloqueo no sustituye a una pasarela de pago real. Es una base funcional para enlazar posteriormente Stripe u otro proveedor.

### Planes SaaS

Los planes comerciales se gestionan desde `Admin CRM > Planes` y se sincronizan con la web publica. El catalogo permite mantener precios, limites y prestaciones desde el panel de administracion sin editar la web estatica manualmente.

## 3. Facturacion SaaS de Membora

### Modulo de facturas

Se ha creado la zona de facturacion SaaS para las facturas emitidas por Membora CRM a sus empresas cliente. Esta facturacion pertenece al administrador de plataforma y es distinta de la facturacion interna de cada gimnasio con sus socios.

Funcionalidades incorporadas:

- Listado de facturas SaaS.
- Creacion y edicion de borradores.
- Emision de facturas.
- Serie y numero correlativo.
- Datos historicos de emisor y cliente.
- Lineas de factura.
- Descuentos.
- IVA desglosado.
- Totales calculados.
- Estado de cobro.
- Pagos parciales.
- Vista imprimible/PDF desde navegador.

### Flujo de trabajo de factura

El flujo actual permite preparar una factura en borrador, revisar lineas e importes, emitirla y registrar cobros. Una factura emitida representa una factura formal dentro del sistema, aunque no equivale a un sistema Verifactu certificado.

La interfaz se ha ajustado para que la seccion de facturacion sea el lugar principal de trabajo:

- La facturacion sustituye la version antigua de cliente.
- Los pagos de cliente quedan agrupados dentro de facturacion.
- La navegacion sigue el patron usado en la administracion SaaS.
- El modal de factura se amplio para trabajar mejor con lineas y datos fiscales.
- La eliminacion de lineas usa iconografia consistente.

### Migracion de datos

Existe una migracion SQL de apoyo en `docs/12-migracion-facturas-saas.sql` para preparar las tablas relacionadas con facturas SaaS cuando sea necesario aplicarla manualmente.

## 4. Pagos dentro de facturacion

Los pagos de empresas cliente se han integrado dentro del area de facturacion para evitar duplicidad entre una vista antigua de cliente y el nuevo flujo de facturas.

El objetivo funcional es que el administrador de plataforma trabaje desde un unico contexto:

- Empresa cliente.
- Suscripcion SaaS.
- Facturas emitidas.
- Pagos asociados.
- Estado de cobro.
- Proximo acceso o renovacion.

Los pagos de socios siguen siendo internos/manuales. La integracion posterior de Stripe Test descrita en la seccion siguiente afecta a los cobros SaaS de Membora a gimnasios, no a las cuotas de socios.

## 5. Stripe y checkout

La estrategia inicial de Stripe como proveedor de cobros SaaS se completo despues con la implementacion en modo `stripe_test` documentada en `docs/16-stripe-billing-saas.md`:

- Checkout alojado.
- Suscripciones recurrentes.
- Confirmacion por webhooks.
- Renovaciones.
- Fallos de pago.
- Sincronizacion de acceso contratado.

El estado actual incluye checkout, webhooks firmados, idempotencia, suscripciones, facturas y cobros de prueba. Stripe Live y los cargos reales siguen pendientes.

Para la entrega se retiraron de la interfaz el bloque diagnostico de Stripe, el boton de Checkout y la cancelacion directa de Stripe. El webhook y las acciones de backend siguen implementados para pruebas tecnicas; el modal visible concentra ahora la gestion local de renovacion con mas espacio y una jerarquia mas clara.

## 6. Alta self-service y limpieza de pruebas

La activacion por correo se completo como un flujo de dos mensajes:

- El primer enlace confirma la propiedad del correo y requiere una accion `POST` antes de crear datos.
- La activacion crea `Cliente CRM`, empresa `TRIAL`, tenant y usuario `GYM_ADMIN` vinculados.
- El segundo enlace revela una contrasena inicial aleatoria cifrada, durante una hora y una sola vez.
- Si falla una parte del aprovisionamiento o del segundo correo, se revierte el alta parcial y la solicitud vuelve a un estado reintentable.
- El rate limit especifico de la prueba esta desactivado por defecto durante la depuracion y puede reactivarse con `TRIAL_RATE_LIMIT_ENABLED=true`.

Tambien se incorporo la eliminacion controlada de empresas de prueba, la eliminacion directa de leads y clientes comerciales y la reparacion automatica de contactos que falten para empresas ya existentes. La herramienta `platform-web` se conserva oculta como apoyo interno para diagnosticar correos.

## 7. Verifactu

El proyecto no esta conectado actualmente a Verifactu.

La facturacion SaaS permite guardar facturas internas y estados de cobro, pero no debe presentarse como sistema Verifactu certificado. Quedan fuera de esta fase:

- Envio fiscal real a AEAT.
- XML Verifactu oficial.
- Firma o certificado digital.
- Huella/hash fiscal encadenada.
- QR fiscal obligatorio.
- Registro inalterable con garantias normativas.
- Recepcion y gestion de respuestas de AEAT.

La estrategia documentada es integrar mas adelante un proveedor especializado o desarrollar un conector completo cuando se cierre el criterio legal y tecnico definitivo.

## 8. Web publica y captacion

Se han aplicado cambios para que la web publica funcione mejor como entrada comercial:

- Carga de planes desde el CRM.
- Proxy de planes publicos a traves del dominio web.
- Proxy del formulario publico de leads.
- Reenvio del origen permitido para evitar problemas de CORS.
- Compatibilidad para que `membora.es` pueda cargar planes del CRM.
- Actualizacion de assets de marca.

La web publica sigue separada del CRM y vive en `httpdocs`.

## 9. Despliegue en Plesk

Se ha reforzado la estructura del repositorio para el despliegue en Plesk:

- CRM PHP en `apps/crm`.
- Entrada publica en `apps/crm/public`.
- Web comercial estatica en `httpdocs`.
- Recuperacion del entrypoint publico legado para compatibilidad.
- Carga del `.env` legado del CRM cuando aplica.

El criterio operativo sigue siendo no usar Node.js, Prisma ni builds frontend en produccion.

## 10. Pagos y membresias de gimnasio

Se ha ampliado la parte operativa de gimnasio con facturacion recurrente de socios:

- Membresias de socios.
- Pagos recurrentes manuales.
- Fechas de vencimiento y cobro.
- Estados de pago.
- Relacion entre socio, suscripcion y pago.

Esta parte pertenece al gimnasio. No debe confundirse con las facturas SaaS que Membora emite a sus empresas cliente.

## 11. Commits relacionados

Referencias recientes en Git:

- `e917f34` - Reordenar la gestion visible de renovacion y retirar controles Stripe del modal.
- `f98263e` - Diferenciar cancelacion de renovacion y retirar diagnostico Stripe de Facturas.
- `aa3d490` - Reparar contactos ausentes a partir de empresas existentes.
- `2a5f6ac` - Hacer configurable el limite de solicitudes de prueba.
- `58b02a9` - Garantizar el usuario administrador al activar una prueba.
- `653d531` - Entregar la credencial inicial mediante un enlace cifrado de un solo uso.
- `f3bc90d` - Permitir eliminar leads comerciales directamente.
- `c79d52e` - Incorporar limpieza de empresas de prueba y ocultar la herramienta web/correo.
- `fcf670f` - Agrupar pagos de cliente dentro de facturacion.
- `e2f889c` - Sustituir facturacion antigua de cliente por flujo de facturas.
- `b8ce879` - Aplicar control de acceso por suscripcion SaaS.
- `87c8904` - Anadir facturacion recurrente de socios de gimnasio.
- `68a2e89` - Usar icono de papelera para borrar lineas de factura.
- `0b72668` - Ampliar modal de factura.
- `1bb48bb` - Mejorar navegacion y layout de facturacion.
- `510ce93` - Ampliar flujo de facturacion SaaS.
- `e5e1a1e` - Crear modulo de facturacion SaaS.
- `4fbcbb0` - Documentar Stripe y alcance de facturacion SaaS.
- `43eb306` - Sincronizar fecha de acceso con proximo pago.
- `80ff2e5` - Mover suscripciones al modal de cliente.
- `3a515aa` - Anadir controles de ciclo de vida de suscripcion SaaS.
- `c817fcd` - Reenviar origen permitido desde proxy de leads.
- `c771134` - Proxy del formulario publico de leads por dominio web.
- `79bc668` - Proxy de planes publicos por dominio web.
- `4520bdd` - Permitir que `membora.es` cargue planes del CRM.
- `c035331` - Asegurar sincronizacion de planes publicos desde CRM.
- `d51853c` - Actualizar assets de marca de Membora.
- `013813c` - Reestructurar repo para despliegue en Plesk.

## 12. Pendientes declarados

Quedan pendientes para siguientes fases:

- Activacion de Stripe Live con cuenta, claves, banco y precios de produccion.
- Webhooks de pago en produccion.
- Validacion fiscal y comercial de los cobros reales.
- Verifactu certificado o integracion con proveedor especializado.
- Migraciones versionadas formales si el proyecto deja de depender de `ensureTable()` y `ensureColumn()`.
- Pruebas finales en Plesk con datos reales, SMTP, uploads y dominios definitivos.
