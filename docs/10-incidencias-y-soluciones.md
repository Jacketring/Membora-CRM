# Incidencias tecnicas y soluciones aplicadas - Membora CRM

Fecha de actualizacion: 16/07/2026.

## 1. Objetivo del documento

Este documento recoge las incidencias mas relevantes encontradas durante el desarrollo del TFM y explica como se resolvieron. No pretende ser un historico exhaustivo de cada cambio menor, sino una memoria tecnica de problemas reales, decisiones tomadas y medidas preventivas incorporadas al proyecto.

El enfoque seguido fue pragmatico: priorizar estabilidad en Plesk, reducir dependencias de produccion, mantener trazabilidad y cerrar cada problema con una solucion verificable.

## 2. Resumen ejecutivo

Las incidencias principales se agrupan en:

- Despliegue y arquitectura.
- Configuracion de entorno.
- Base de datos y migraciones incrementales.
- Formularios y errores 500.
- Captacion web, webhook y correo.
- Interfaz y experiencia de uso.
- Seguridad, permisos y auditoria.
- Trazabilidad de pruebas y entrega.

La solucion transversal mas importante fue simplificar el sistema a una aplicacion PHP monolitica desplegable en Plesk, con MariaDB, PDO, vistas PHP, CSS propio y JavaScript de navegador. Esto elimino la dependencia de procesos Node.js, builds frontend y migraciones Prisma en produccion.

## 3. Incidencias y resoluciones

### I-01 Complejidad de despliegue con Node.js, Next.js, NestJS y Prisma

Problema:

El planteamiento inicial separaba frontend/backend y dependia de Node.js, builds, Prisma y procesos persistentes. Para un hosting Plesk, esto aumentaba la complejidad operativa y el riesgo de fallo en entrega.

Causa raiz:

El stack inicial era correcto para una arquitectura moderna separada, pero no era el mas adecuado para un despliegue academico y mantenible en hosting compartido.

Solucion aplicada:

- Migracion a aplicacion PHP monolitica.
- Entrada unica en `apps/crm/public/index.php`.
- Renderizado HTML desde PHP.
- Persistencia mediante PDO y MariaDB.
- Eliminacion de `npm install`, `npm run build`, Prisma y procesos Node en produccion.

Resultado:

El despliegue queda reducido a configurar el document root en `httpdocs`, subir `.env` y abrir `/app/` para acceder al CRM.

Prevencion:

La documentacion repite explicitamente que en produccion no se ejecutan comandos Node ni Prisma.

Verificacion:

- `README.md`.
- `docs/01-alcance-mvp.md`.
- `docs/07-estado-actual-php.md`.

### I-02 Configuracion sensible en Plesk y contrasenas con caracteres especiales

Problema:

Las credenciales de base de datos y SMTP podian fallar si se escribian de forma ambigua o si la contrasena contenia caracteres especiales.

Causa raiz:

Las variables de entorno sin comillas pueden interpretarse mal segun el parser o el entorno.

Solucion aplicada:

- Documentar `.env` con valores entre comillas.
- Mantener compatibilidad con `DATABASE_URL`, pero recomendar variables separadas.
- Separar credenciales reales del repositorio mediante `apps/crm/.env`.

Resultado:

La configuracion queda mas robusta y facil de revisar en Plesk.

Prevencion:

`README.md` incluye una configuracion recomendada con comillas y variables separadas.

### I-03 Falta de migraciones formales tras abandonar Prisma

Problema:

Al eliminar Prisma, habia que poder crear nuevas tablas y columnas sin ejecutar migraciones Node.

Causa raiz:

La version PHP necesitaba evolucionar el modelo de datos en Plesk mediante despliegues incrementales.

Solucion aplicada:

- Repositorios PHP con metodos `ensureTable()` y `ensureColumn()`.
- Creacion automatica de tablas auxiliares al cargar modulos.
- Columnas incrementales para imagenes, membresias, clases, pagos, check-ins, alertas, auditoria y facturacion.

Resultado:

Un `git pull` puede desplegar cambios funcionales sin pasos manuales de migracion.

Prevencion:

Se documenta que el usuario MariaDB debe tener permisos `CREATE TABLE` y `ALTER TABLE` durante actualizaciones.

Riesgo residual:

En una produccion mas madura convendria sustituir este enfoque por migraciones versionadas.

### I-04 Errores 500 potenciales en formularios principales

Problema:

Los formularios de creacion/edicion podian fallar si faltaban columnas, tablas auxiliares o validaciones de datos relacionados.

Causa raiz:

El CRM fue creciendo por modulos y algunas relaciones no existian en la base original.

Solucion aplicada:

- Validaciones antes de insertar o actualizar.
- Consultas preparadas con PDO.
- Creacion de tablas/columnas antes de operar.
- Mensajes `flash()` para errores de usuario.
- Validaciones de pertenencia a `tenant_id`.

Ejemplos:

- Pago: valida socio existente y que la membresia pertenezca al socio.
- Reserva: valida socio activo, aforo y duplicados.
- Check-in: valida socio/reserva y actualiza asistencia.

Resultado:

Los formularios principales crean y editan datos con errores controlados en lugar de fallos abruptos.

### I-05 Captacion web y problemas de CORS/origen

Problema:

La web comercial vive en otro subdominio y envia leads al CRM. Esto puede fallar si el origen no coincide exactamente.

Causa raiz:

El navegador envia `Origin` y el CRM debe validar `WEB_APP_URL` para aceptar solicitudes entre subdominios.

Solucion aplicada:

- Endpoint publico `POST /webhook/lead`.
- Validacion de origen contra `WEB_APP_URL`.
- Soporte de `OPTIONS`.
- Respuestas JSON.
- Logs tecnicos en `webhook_logs`.
- Diagnostico visible en `Admin CRM > Web`.

Resultado:

El formulario publico queda conectado al CRM sin exponer credenciales de base de datos en la web.

Prevencion:

Checklist de seguridad y despliegue exige validar `APP_URL`, `WEB_APP_URL`, HTTPS y formulario real.

### I-06 Correo SMTP de confirmacion con detalles incorrectos

Problema:

Durante las pruebas de correo aparecieron problemas de presentacion, como logo roto o referencias internas visibles.

Causa raiz:

El email HTML inicial reutilizaba datos tecnicos utiles para depuracion, pero no adecuados para el visitante final.

Solucion aplicada:

- Plantilla HTML de confirmacion mas limpia.
- Eliminacion de referencias internas tipo `php_...` del correo visible.
- Diagnostico SMTP en `Admin CRM > Web`.
- El lead se crea aunque falle el envio del email.

Resultado:

El formulario no pierde solicitudes por fallos de correo y el administrador puede diagnosticar SMTP desde el panel.

Prevencion:

Los fallos quedan registrados en logs y la prueba de correo permite validar la configuracion antes de la demo.

### I-07 Subidas de imagen y permisos de escritura

Problema:

Las fotos de perfil y socio dependen de que Plesk permita escribir en `apps/crm/public/uploads`.

Causa raiz:

En hosting compartido los permisos de carpetas pueden variar segun usuario, grupo y modo PHP.

Solucion aplicada:

- Validacion de imagen mediante `getimagesize`.
- Limite de 2 MB.
- Tipos permitidos: JPG, PNG, WEBP.
- Rutas separadas para usuarios y socios.
- Checklist de validacion manual en Plesk.

Resultado:

La subida queda controlada y el problema de permisos queda identificado como prueba obligatoria de produccion.

### I-08 Tablas vacias, modo oscuro y consistencia visual

Problema:

Se detectaron casos de celdas o estados visuales poco legibles en claro/oscuro.

Causa raiz:

El crecimiento de modulos introdujo nuevos estados y tablas que no siempre tenian estilos equivalentes.

Solucion aplicada:

- Normalizacion de badges de estado.
- Estilos para estados nuevos como `EXPORTED`, `SYNCED`, `SUCCESS`, `ERROR`.
- Ajustes en tablas vacias y detalles desplegables.
- Reutilizacion de componentes visuales existentes.

Resultado:

La interfaz mantiene consistencia visual entre modulos nuevos y antiguos.

Prevencion:

Checklist de barrido visual en modo claro/oscuro y responsive.

### I-09 Codigos internos visibles para el usuario

Problema:

En algunos listados aparecian codigos de base de datos como `PAYMENT_PENDING`, `SEED_DEMO_TENANT` o nombres internos de alertas.

Causa raiz:

La aplicacion guardaba estados tecnicos en base de datos y faltaban mapeos de presentacion para valores antiguos o nuevos.

Solucion aplicada:

- Funciones de etiqueta en `Support.php`.
- Mapeos para estados de socios, pagos, alertas, auditoria, check-ins y facturacion.
- Fallback generico para convertir codigos tipo `CODIGO_INTERNO` en texto legible.

Resultado:

Las pantallas muestran etiquetas comprensibles, por ejemplo:

- `PAYMENT_PENDING` -> `Pago pendiente`.
- `SEED_DEMO_TENANT` -> `Carga de datos demo`.

Prevencion:

Cada nuevo estado debe pasar por una funcion de etiqueta antes de mostrarse.

### I-10 Buscadores y selects poco ergonomicos con muchos socios

Problema:

Al crear pagos o seleccionar socios, un desplegable plano no era suficiente si el gimnasio tenia muchos registros.

Causa raiz:

Los formularios iniciales asumian pocos datos de prueba.

Solucion aplicada:

- Selects personalizados con busqueda en navegador.
- Filtrado mientras se escribe.
- En pagos, la membresia se filtra segun el socio seleccionado.

Resultado:

Crear pagos es mas usable y reduce errores al asociar socio/membresia.

Prevencion:

Los campos con entidades grandes deben usar busqueda o filtro incremental.

### I-11 Pagos incompletos como modulo operativo

Problema:

El alcance inicial dejaba pagos de gimnasio como mejora futura.

Causa raiz:

La primera version priorizaba leads, socios, membresias y clases.

Solucion aplicada:

- Modulo `Pagos`.
- Tabla `payments`.
- Estados `PAID`, `PENDING`, `OVERDUE`, `CANCELLED`.
- Asociacion opcional a suscripcion.
- KPIs de cobrado, pendiente y vencido.
- Alertas de riesgo por pagos vencidos.

Resultado:

Pagos queda cerrado como modulo manual del MVP.

Limite consciente:

La pasarela de cobro automatico de cuotas de socios queda fuera del MVP. Stripe Test cubre por separado los cobros SaaS de Membora a gimnasios, como se documenta en `docs/16-stripe-billing-saas.md`.

### I-12 Check-ins pendientes como modulo independiente

Problema:

La asistencia existia ligada a reservas, pero faltaba un registro de entradas independientes.

Causa raiz:

Un gimnasio puede necesitar registrar entrada libre, no solo asistencia a clase.

Solucion aplicada:

- Modulo `Check-ins`.
- Tabla `checkins`.
- Asociacion opcional a reserva.
- Si el check-in se vincula a reserva, la reserva pasa a `attended`.
- KPIs y filtros por fecha/texto.

Resultado:

El CRM cubre asistencia a clase y entrada general de socios.

### I-13 Alertas de riesgo inexistentes o dispersas

Problema:

El sistema tenia datos suficientes para detectar riesgos, pero no habia un modulo central.

Causa raiz:

Los riesgos estaban implícitos en pagos, tareas, membresias, leads, check-ins y clases.

Solucion aplicada:

- Modulo `Alertas`.
- Tabla `risk_alerts`.
- Generacion automatica al abrir dashboard o alertas.
- Alertas por pagos vencidos, tareas vencidas, membresias caducadas o proximas a renovar, socios sin actividad, leads sin seguimiento y clases llenas.
- Estados `OPEN`, `RESOLVED`, `DISMISSED`.

Resultado:

El administrador tiene una bandeja unica de prioridades operativas.

### I-14 Falta de auditoria de acciones

Problema:

No habia trazabilidad clara de quien habia hecho cada cambio.

Causa raiz:

Los formularios ejecutaban acciones POST, pero no dejaban registro centralizado.

Solucion aplicada:

- Tabla `audit_logs`.
- Registro automatico de acciones POST internas.
- Guarda usuario, tenant, accion, entidad, ruta, IP, navegador y metadatos.
- Sanitiza contrasenas y tokens antes de persistir.
- Vista `Auditoria` con filtros.

Resultado:

El TFM puede demostrar trazabilidad operativa sin exponer secretos.

Prevencion:

El registro se hace desde el despachador central de acciones, no manualmente en cada formulario.

### I-15 Permisos por rol solo documentados, no aplicados

Problema:

Los roles existian, pero faltaba una validacion centralizada de rutas y acciones.

Causa raiz:

La primera etapa priorizo funcionalidad y login, pero no bloqueo granular.

Solucion aplicada:

- Matriz central de permisos por rol.
- `can_access_route()` para rutas.
- `can_perform_action()` para acciones POST.
- Menu lateral adaptado al rol.
- Compatibilidad con superadmin y modo soporte.

Resultado:

El backend bloquea accesos no permitidos aunque se intente enviar una URL o formulario manualmente.

### I-16 Modo soporte de superadmin

Problema:

El superadmin necesitaba entrar al CRM de una empresa conectada sin perder la posibilidad de volver al panel SaaS.

Causa raiz:

El cambio temporal de tenant puede confundirse con un login normal de gimnasio.

Solucion aplicada:

- Sesion `platform_admin_user` para conservar el usuario original.
- `tenant_context` para indicar modo soporte.
- Banner de soporte.
- Accion `exit_empresa_crm` permitida incluso dentro del contexto de tenant.

Resultado:

El soporte puede revisar datos del cliente y volver a `Admin CRM` de forma controlada.

### I-17 Creacion de empresa desde cliente comercial

Problema:

Se habia detectado que crear empresa podia acabar editando la primera empresa si el flujo no separaba bien alta y edicion.

Causa raiz:

Riesgo de mezclar formularios o identificadores al reutilizar vistas de gestion.

Solucion aplicada:

- Separacion de acciones `create_empresa` y `update_empresa`.
- Validacion de identificadores.
- Creacion de tenant y usuario administrador al crear CRM.
- Checklist de regresion para clientes/empresas.

Resultado:

El flujo queda separado entre cliente comercial, empresa SaaS y tenant conectado.

### I-18 Reservas y control de aforo

Problema:

Las reservas requieren controlar duplicados, cancelaciones, aforo y estados de asistencia.

Causa raiz:

No basta con insertar una fila; hay reglas de negocio asociadas a clase, socio y capacidad.

Solucion aplicada:

- Validacion de socio activo.
- Bloqueo por aforo.
- Reutilizacion controlada de reserva cancelada.
- Estados `reserved`, `cancelled`, `attended`, `no_show`.
- Historial de reservas en ficha de socio.

Resultado:

El modulo de clases cubre planificacion y asistencia basica.

### I-19 Web publica y alternativa directa por base de datos

Problema:

El webhook funciona, pero en Plesk podria interesar reducir dependencias entre subdominios.

Causa raiz:

La web y el CRM estan separados y el webhook depende de HTTP, CORS y disponibilidad del CRM.

Solucion aplicada:

- Mantener webhook como flujo activo.
- Documentar alternativa futura de insercion directa en base de datos desde la web PHP.
- Mantener la alternativa fuera de la implementacion para no duplicar logica prematuramente.

Resultado:

La solucion actual es segura y extensible; la alternativa queda razonada para futuras necesidades.

### I-20 Facturacion externa generica

Problema:

El alcance mencionaba integraciones de facturacion externa, pero una integracion real con terceros depende de proveedor, credenciales, normativa y pruebas fuera del alcance inmediato.

Causa raiz:

No habia todavia decision final sobre proveedor real de facturacion.

Solucion aplicada:

- Se implemento un modulo generico demostrable.
- Configuracion de proveedor, endpoint, clave enmascarada, estado y formato.
- Exportacion CSV.
- Sincronizacion simulada.
- Logs tecnicos.
- Marcado de pagos como pendientes, exportados o sincronizados.

Resultado:

El MVP demuestra el flujo tecnico sin acoplarse a un proveedor real.

Decision actual:

La parte de facturacion queda estable y sin ampliar hasta hablar con una persona experta/proveedor externo.

## 4. Patrones de solucion reutilizados

### Centralizar decisiones

Los problemas repetidos se resolvieron moviendo logica a puntos centrales:

- Acciones POST en `Actions.php`.
- Consultas y tablas en `Repositories.php`.
- Etiquetas, permisos y helpers en `Support.php`.
- Routing en `public/index.php`.

Esto evita duplicar validaciones y reduce regresiones.

### Fallar de forma controlada

En vez de permitir errores 500 por datos incompletos:

- Se validan entradas.
- Se usan mensajes `flash()`.
- Se comprueba pertenencia a `tenant_id`.
- Se crean tablas/columnas auxiliares si faltan.

### Separar datos tecnicos de interfaz

Los codigos internos se mantienen en base de datos, pero nunca deberian mostrarse directamente al usuario. La capa de presentacion convierte estados y acciones a etiquetas legibles.

### Documentar decisiones operativas

Cada cambio relevante queda reflejado en:

- `README.md`.
- `docs/01-alcance-mvp.md`.
- `docs/04-modelo-datos.md`.
- `docs/05-pruebas.md`.
- `docs/06-api-backend.md`.
- `docs/07-estado-actual-php.md`.

## 5. Verificacion actual

Validaciones usadas durante el desarrollo:

```bash
php -l archivo.php
git diff --check
```

Tambien se han realizado revisiones manuales de:

- Rutas.
- Formularios.
- Modales.
- Buscadores.
- Estados visibles.
- Documentacion.

Pendiente antes de la entrega final:

- Validar produccion en Plesk con datos reales.
- Probar login con credenciales demo.
- Probar formulario web real.
- Probar SMTP real.
- Probar uploads.
- Hacer barrido visual claro/oscuro y responsive.

## 6. Conclusion tecnica

El proyecto paso de una arquitectura mas compleja a una solucion PHP monolitica mas adecuada para el contexto de entrega. Las incidencias principales se resolvieron con tres principios:

- Reducir complejidad operativa.
- Centralizar validaciones y permisos.
- Mantener trazabilidad mediante logs, auditoria y documentacion.

El resultado es un MVP funcional, desplegable en Plesk, documentado y con un conjunto claro de pruebas finales antes de la entrega academica.
