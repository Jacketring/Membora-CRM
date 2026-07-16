# Plan de pruebas - Membora CRM

Fecha de actualización: 16/07/2026.

Este plan corresponde a la fase de verificación de la metodología incremental descrita en `docs/19-metodologia-desarrollo.md`.

## Automatización

La suite PHPUnit cubre permisos por rol, CSRF, normalización de entradas, reglas de membresía, auditoría segura, webhook, métricas del dashboard, estados históricos de reservas, provisionamiento de pruebas e inicialización de Sentry. La ejecución local del 16 de julio de 2026 completa **50 tests y 243 aserciones** sin errores.

El 11 de julio de 2026 se midió una cobertura del **93,50 % de líneas (604/646)** en la capa lógica configurada, por encima del umbral CI del 80 %. Esta cobertura es la última medición guardada y debe tratarse como evidencia histórica de esa capa, no como cobertura actual de todo el producto.

Playwright valida login correcto e incorrecto, bloqueo de rutas de plataforma para usuarios de gimnasio, creación y conversión de un lead, programación de una clase con reserva y, cuando existe `E2E_INVOICE_URL`, visualización e impresión de una factura. Node.js se usa únicamente en desarrollo y CI.

## Matriz resumida de trazabilidad

| Area | Requisitos | Historias | Evidencia principal |
|---|---|---|---|
| Autenticacion y permisos | RF-01 a RF-03, RF-21 | HU-01 a HU-03, HU-23 y HU-24 | PHPUnit de seguridad, PF-01, PF-08 y E2E login |
| Leads, socios y membresias | RF-04 a RF-08 | HU-04 a HU-10 | PF-02 a PF-04 y E2E lead |
| Pagos, clases y operacion | RF-09 a RF-15, RF-22 | HU-11 a HU-20, HU-32 | PF-04B a PF-06, PF-10, PF-11 y E2E clase |
| Perfil y novedades | RF-23 | HU-25 | PF-09 |
| Plataforma SaaS | RF-16, RF-17 y RF-19 | HU-26 a HU-28, HU-30 | PA-01 a PA-07 y PA-10 |
| Trial y planes publicos | RF-18 y RF-24 | HU-22 y HU-31 | PD-05 y PA-08 |
| Stripe Test | RF-20 | HU-29 | PA-09 y `docs/16-stripe-billing-saas.md` |
| Demo y despliegue | RNF-05, RNF-06 | HU-21 | PD-01 a PD-04 |

## 1. Objetivo

Este documento define las pruebas manuales recomendadas para validar la version PHP de Membora CRM antes de la entrega del TFM.

El objetivo es comprobar que:

- La aplicacion carga en Plesk desde el document root `httpdocs`; `httpdocs/app/index.php` puentea de forma segura hacia `apps/crm/public`.
- El login funciona con usuarios demo.
- Los datos se separan por `tenant_id`.
- Los modulos principales funcionan sin errores 500.
- El panel de administracion SaaS permite gestionar empresas cliente.
- La aplicacion no depende de Node.js en produccion.

## 2. Entorno de pruebas

URL objetivo:

```text
https://membora.es/app/
```

Stack:

```text
PHP 8.2+
MariaDB
PDO
Plesk
```

Credenciales de gimnasio:

```text
Email: admin@nexofit.demo
Password: definida de forma segura en el entorno de pruebas (no se versiona).
```

Credenciales de plataforma:

```text
Email: admin@membora.crm
Password: definida mediante `PLATFORM_ADMIN_PASSWORD` en `.env` durante el despliegue.
```

## 3. Pruebas de despliegue

### PD-01 Document root

Pasos:

1. Entrar en Plesk.
2. Revisar configuracion de hosting.
3. Confirmar que la raiz del documento apunta a `httpdocs` y que el CRM abre en `/app/`.

Resultado esperado:

- La URL abre la aplicacion PHP y no una pagina generica de hosting.

### PD-02 Conexion a base de datos

Pasos:

1. Revisar `apps/crm/.env`.
2. Abrir el login.
3. Intentar iniciar sesion.

Resultado esperado:

- No aparece el mensaje "No se pudo conectar con la base de datos".
- El login valida usuarios existentes.

### PD-03 Sin build Node

Pasos:

1. Hacer pull desde GitHub.
2. Abrir la URL.

Resultado esperado:

- No es necesario ejecutar `npm install`.
- No es necesario ejecutar `npm run build`.
- No es necesario reiniciar una aplicacion Node.

### PD-04 Demo temporal desde la web publica

Pasos:

1. Abrir `https://membora.es/`.
2. Pulsar un enlace de demo.
3. Confirmar que se inicia sesion automaticamente en el CRM con datos de prueba.
4. Verificar que aparece un contador de 20 minutos en la parte superior.
5. Pulsar `Salir de la demo` junto al contador y comprobar que vuelve a Membora.
6. Forzar o esperar el fin de la sesion temporal.
7. Repetir cerrando sesion y cerrando la pestana de la demo.

Resultado esperado:

- La demo no abre una version estatica separada.
- El CRM muestra una sesion funcional con datos demo.
- Cada acceso utiliza un usuario temporal diferente.
- El boton de salida cierra la sesion, elimina el usuario temporal y vuelve a la web publica.
- El usuario temporal se elimina al cerrar sesion, cerrar la pestana o superar los 20 minutos.
- Al finalizar el contador, se cierra la sesion y se vuelve a la web publica.

### PD-05 Prueba de 14 dias con verificacion por correo

Pasos:

1. Abrir `https://membora.es/#prueba-gratis`.
2. Completar el formulario y aceptar la politica de privacidad.
3. Abrir el enlace de verificacion recibido por correo.
4. Definir la contrasena desde la pantalla segura de recuperacion.
5. Iniciar sesion con la nueva cuenta.

Resultado esperado:

- El formulario no revela si un correo ya estaba registrado.
- Sin verificar el correo no se crea ninguna empresa ni usuario del CRM.
- El enlace solo se puede usar una vez y caduca al cabo de una hora.
- Tras verificarlo aparece un contacto `Cliente CRM` con empresa vinculada, tenant aislado y 14 dias de prueba.
- La contrasena nunca se envia por correo y la establece el propio usuario.

## 4. Pruebas funcionales de gimnasio

### PF-01 Login administrador

Pasos:

1. Entrar con `admin@nexofit.demo`.
2. Acceder al panel.

Resultado esperado:

- Se muestra el dashboard del gimnasio.
- La barra lateral muestra modulos de gimnasio, no `Admin CRM`.

### PF-02 Leads

Pasos:

1. Abrir Leads.
2. Crear un lead.
3. Cambiar etapa.
4. Anadir una nota.
5. Editar la nota.
6. Convertir el lead a socio.

Resultado esperado:

- El estado cambia segun la etapa.
- La nota se mantiene en el detalle.
- Al convertir, el socio aparece en Socios.

### PF-03 Socios

Pasos:

1. Abrir Socios.
2. Crear o editar un socio.
3. Subir una foto.
4. Asignar membresia.
5. Eliminar un socio convertido desde lead.

Resultado esperado:

- La foto se visualiza.
- La membresia aparece vinculada.
- Al eliminar un socio convertido, el lead vuelve al listado como perdido/reactivado segun la logica implementada.

### PF-04 Membresias

Pasos:

1. Crear una membresia mensual.
2. Asignarla a un socio.
3. Revisar la fecha de caducidad.

Resultado esperado:

- El precio se guarda.
- La caducidad se calcula automaticamente desde la fecha actual o la fecha de inicio.

### PF-04B Pagos

Pasos:

1. Abrir Pagos.
2. Crear un pago para un socio activo.
3. Asociarlo a una membresia si existe.
4. Marcarlo como pendiente, pagado, vencido o cancelado.
5. Editar importe, metodo, vencimiento y fecha de pago.
6. Filtrar por estado y fechas.

Resultado esperado:

- El pago aparece vinculado al socio correcto.
- Los importes se muestran en EUR.
- Los indicadores de cobrado este mes, pendiente y vencidos se actualizan.
- El dashboard cuenta pagos pendientes o vencidos.

### PF-05 Clases, calendario y reservas

Pasos:

1. Abrir Clases.
2. Crear tipo de clase.
3. Abrir calendario.
4. Crear clase desde un dia.
5. Editar clase.
6. Eliminar clase.
7. Cambiar de mes.
8. Crear una reserva para un socio activo.
9. Marcar asistencia, no-show y cancelacion.

Resultado esperado:

- La clase aparece en el calendario correcto.
- El calendario no se cierra al crear desde calendario.
- Al cambiar de mes no desaparecen clases activas fuera del rango incorrectamente.
- No se permite superar el aforo.
- No se permite duplicar una reserva activa para el mismo socio y sesion.
- El historial de reservas aparece en la ficha del socio.

### PF-05B Check-ins

Pasos:

1. Abrir Check-ins.
2. Crear un check-in para un socio activo.
3. Buscar el socio desde el selector.
4. Asociarlo a una reserva si existe.
5. Confirmar que la reserva queda marcada como asistida.
6. Filtrar check-ins por texto y fechas.

Resultado esperado:

- El check-in aparece en el historial.
- El selector de reservas se filtra por el socio elegido.
- Las metricas de hoy, ultimos 7 dias, manuales y con clase se actualizan.

### PF-05C Alertas

Pasos:

1. Abrir Alertas.
2. Revisar alertas abiertas.
3. Filtrar por tipo y estado.
4. Resolver una alerta.
5. Descartar una alerta.
6. Volver al dashboard y revisar el contador de alertas abiertas.

Resultado esperado:

- La pantalla genera alertas desde pagos, tareas, membresias, leads, check-ins y clases.
- Las alertas resueltas dejan de aparecer como abiertas.
- Las metricas se actualizan tras resolver o descartar.

### PF-06 Tareas

Pasos:

1. Crear una tarea.
2. Asignar un usuario responsable.
3. Editar la tarea.
4. Cambiar estado.
5. Eliminar con confirmacion visual.

Resultado esperado:

- Se crea una tarea interna asignada al usuario responsable.
- No se duplican tareas.
- Las acciones se muestran con iconos.

### PF-07 Usuarios internos

Pasos:

1. Abrir Usuarios.
2. Crear usuario interno.
3. Asignar rol.
4. Editar usuario.

Resultado esperado:

- Los roles se muestran en espanol.
- Los socios/clientes no aparecen como usuarios internos.

### PF-08 Recuperacion y recuerdo de sesion

Pasos:

1. Solicitar recuperacion para un email existente y otro inexistente.
2. Abrir el enlace recibido y establecer una contrasena valida.
3. Intentar reutilizar el mismo enlace.
4. Iniciar sesion marcando `Recordarme`, cerrar sesion y revisar la cookie.

Resultado esperado:

- Las respuestas de solicitud no revelan si el email existe.
- El token valido permite un unico cambio y despues queda revocado.
- La cookie de recuerdo restaura y rota la sesion mientras sea valida.
- Cerrar sesion elimina cookie y token.

### PF-09 Perfil, configuracion y novedades

Pasos:

1. Editar nombre, telefono e imagen del perfil.
2. Cambiar color y tema visual.
3. Abrir Novedades.

Resultado esperado:

- Los cambios se conservan para el usuario o tenant correcto.
- Un archivo no permitido se rechaza.
- Novedades muestra la version y el historial configurados.

### PF-10 Facturacion externa

Pasos:

1. Guardar endpoint, proveedor, formato y una clave API de prueba.
2. Crear pagos pagados pendientes de envio.
3. Exportar CSV y ejecutar la sincronizacion simulada.
4. Revisar los logs tecnicos.

Resultado esperado:

- La clave se muestra enmascarada y no aparece en auditoria.
- El CSV contiene solo pagos elegibles del tenant actual.
- Estado, numero de pagos, importe y resultado quedan registrados.

### PF-11 Auditoria

Pasos:

1. Crear, editar y eliminar una entidad de prueba.
2. Abrir Auditoria y filtrar por accion, usuario y fecha.
3. Revisar los metadatos de una accion con campos sensibles.

Resultado esperado:

- La actividad pertenece al tenant actual.
- Contrasenas, CSRF, tokens, cookies y claves se sustituyen por valores sanitizados.
- Un superadministrador puede consultar la actividad de plataforma o del cliente solicitado sin mezclar contextos.

## 5. Pruebas de administracion SaaS

### PA-01 Login superadmin

Pasos:

1. Entrar con `admin@membora.crm`.

Resultado esperado:

- Se abre `Admin CRM`.
- La barra lateral no muestra modulos de gimnasio.

### PA-02 Contactos

Pasos:

1. Abrir `Admin CRM > Contactos`.
2. Comprobar que aparecen solicitudes web y clientes comerciales en una misma tabla.
3. Filtrar por tipo `Lead web`.
4. Filtrar por tipo `Cliente CRM`.
5. Convertir un lead web en cliente.
6. Crear un contacto manual.
7. Editar el estado de un contacto.

Resultado esperado:

- La seccion se llama `Contactos`.
- No existen pantallas separadas visibles de `Leads` y `Clientes` en el menu de administracion.
- Los leads web y clientes comerciales se gestionan desde la misma tabla.
- Al convertir un lead, el contacto pasa a cliente comercial.

### PA-03 Empresas cliente

Pasos:

1. Crear empresa.
2. Editar plan.
3. Cambiar estado CRM.
4. Cambiar estado de pago.
5. Revisar MRR.

Resultado esperado:

- La empresa queda en tabla `empresas`.
- Los indicadores se actualizan.

### PA-04 Acceso de soporte

Pasos:

1. En `Admin CRM`, pulsar `Entrar` en una empresa con tenant conectado.
2. Revisar el CRM del gimnasio.
3. Pulsar `Volver a Admin CRM`.

Resultado esperado:

- Se muestra banner de modo soporte.
- El superadmin puede ver datos del cliente para soporte.
- Se puede volver al panel SaaS.

### PA-05 Usuarios de plataforma

Pasos:

1. Abrir `Admin CRM > Usuarios`.
2. Crear y editar un usuario de plataforma.
3. Comprobar desde un administrador de gimnasio que la ruta y las acciones se bloquean.
4. Eliminar el usuario de prueba.

Resultado esperado:

- Solo los roles de plataforma autorizados pueden gestionarlos.
- Los roles globales no aparecen como asignables desde un gimnasio.

### PA-06 Cobros SaaS

Pasos:

1. Crear un cobro para una empresa.
2. Editar concepto, importe, vencimiento y estado.
3. Abrir su justificante o factura asociada cuando exista.

Resultado esperado:

- El cobro queda vinculado a la empresa correcta.
- Los indicadores mensuales y pendientes se actualizan.

### PA-07 Facturas SaaS y de cliente

Pasos:

1. Crear una factura en borrador con varias lineas e impuestos.
2. Emitirla y comprobar serie, numero y totales.
3. Registrar un pago parcial y despues el saldo restante.
4. Abrir la vista imprimible.
5. Repetir el flujo para un cliente comercial sin empresa cuando proceda.

Resultado esperado:

- Los totales y el saldo se recalculan correctamente.
- El estado cambia de pendiente a parcial y pagado.
- Los cobros no duplican ni eliminan historial.
- La impresion no produce errores JavaScript.

### PA-08 Planes y API publica

Pasos:

1. Crear o editar un plan activo.
2. Consultar `/app/api/plans` y el proxy `/api/plans.php`.
3. Desactivar el plan y repetir la consulta.

Resultado esperado:

- Solo se publican planes activos y datos comerciales permitidos.
- La moneda y los importes coinciden con el panel.

### PA-09 Stripe Billing de prueba

Precondicion: `PAYMENTS_MODE=stripe_test`, claves de prueba y Price IDs validos.

Pasos:

1. Iniciar checkout para una empresa y completar un pago con tarjeta de prueba.
2. Confirmar que el retorno por si solo no activa el acceso.
3. Enviar o esperar el webhook firmado.
4. Repetir el mismo evento.
5. Solicitar la cancelacion de Stripe al final del periodo y recibir su actualizacion por webhook.

Resultado esperado:

- El webhook sincroniza empresa, suscripcion, factura y cobro.
- El mismo `stripe_event_id` no se procesa dos veces.
- La cancelacion conserva el acceso hasta el final del periodo y queda sincronizada por webhook.
- Ninguna prueba usa claves Live ni dinero real.

### PA-10 Web, correo y captacion

Pasos:

1. Enviar una solicitud desde la web publica sin token manual.
2. Comprobar su aparicion en Contactos y el log de confirmacion.
3. Ejecutar la prueba de correo desde `Admin CRM > Web`.
4. Enviar una integracion de tenant con token valido e invalido.

Resultado esperado:

- La captacion publica exige origen permitido, honeypot vacio y rate limit.
- La integracion con token solo escribe en su tenant activo.
- Los errores SMTP quedan registrados sin impedir la creacion del contacto.

## 6. Pruebas tecnicas locales

Comandos usados antes de subir cambios:

```bash
cd apps/crm
composer test
composer analyse
php -l public/index.php
php -l src/Actions.php
php -l src/StripeBilling.php
node --check public/assets/app.js
node --check ../../httpdocs/assets/site.js
git diff --check
```

Resultado esperado:

- Sin errores de sintaxis PHP.
- Sin errores de sintaxis JavaScript.
- Sin errores de espacios finales en Git.

## 7. Riesgos pendientes

- Las reservas estan implementadas dentro del modulo de clases, pero conviene validarlas en produccion con datos reales.
- La aplicacion crea tablas auxiliares automaticamente; conviene validar permisos de usuario MariaDB en Plesk.
- Hay que validar el flujo completo en produccion justo antes de grabar el video final.
- Stripe esta implementado solo en modo de prueba; activar Live exige cuenta, claves, webhook, precios, banco y validacion fiscal/comercial.
- La cobertura del 93,50 % es historica y corresponde a la capa configurada, no a todas las rutas, repositorios ni vistas.
