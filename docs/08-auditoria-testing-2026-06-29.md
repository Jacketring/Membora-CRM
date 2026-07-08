# Auditoria de testing - 29/06/2026

## Objetivo

Revisar el estado funcional de Membora CRM despues de los ultimos cambios en:

- App PHP del CRM.
- Panel de administracion SaaS.
- Web comercial publica.
- Formulario web conectado a leads comerciales.
- Correos de confirmacion.

Esta auditoria combina comprobaciones automaticas locales y una checklist manual para Plesk.

## Entorno revisado

```text
Workspace local: C:\Users\Jose.Versens\Documents\membora-crm
PHP CLI local: 8.2.12
App CRM local: http://127.0.0.1:8092
Web publica local: http://127.0.0.1:8091
Produccion CRM: https://app.crm.josehurtado.dev
Produccion web: https://app.web.josehurtado.dev
```

Limitacion de la prueba local:

```text
No existe php-app/.env local, por lo que no se han ejecutado pruebas con MariaDB local.
Los flujos que dependen de base de datos deben validarse en Plesk.
```

## Comprobaciones automaticas realizadas

### PHP

Resultado:

```text
OK
```

Se ejecuto `php -l` sobre todos los archivos PHP de `php-app`.

Resultado:

- Sin errores de sintaxis en `config`, `public`, `src`, `Views` ni `partials`.

### Carga HTTP local

Resultado:

```text
OK
```

Rutas verificadas:

- `web-app/public/index.html`: HTTP 200.
- `web-app/public/demo.html`: HTTP 200.
- `php-app/public/index.php?route=login`: HTTP 200.

Assets verificados:

- `web-app/public/assets/site.css`: HTTP 200.
- `web-app/public/assets/site.js`: HTTP 200.
- Demo publica: redireccion/control de entrada hacia demo funcional del CRM.
- `php-app/public/assets/app.css`: HTTP 200.
- `php-app/public/assets/app.js`: HTTP 200.
- `php-app/public/assets/favicon.svg`: HTTP 200.

### Formularios y acciones

Resultado:

```text
OK con revision manual pendiente
```

Se revisaron acciones POST declaradas en vistas y `Actions.php`.

Acciones principales conectadas:

- Login y logout.
- Perfil.
- Leads de gimnasio.
- Notas de leads.
- Socios.
- Membresias.
- Clases.
- Reservas.
- Tareas.
- Usuarios internos.
- Contactos de plataforma: leads web y clientes comerciales en una tabla unificada.
- Empresas.
- Pagos SaaS.
- Planes SaaS.
- Correo de prueba.
- Entrada/salida en modo soporte.

## Hallazgos

### H-01 Testing local del formulario web depende de produccion

Severidad:

```text
Baja
```

Estado:

```text
Pendiente de decidir
```

Detalle:

`web-app/public/assets/site.js` apunta directamente a:

```text
https://app.crm.josehurtado.dev/webhook/lead
```

Esto es correcto para produccion, pero dificulta probar el formulario contra un CRM local o staging sin editar el archivo.

Recomendacion:

- Mantenerlo asi si solo se despliega una web publica.
- Si mas adelante hay staging, crear una pequena configuracion en HTML tipo `data-webhook-url`.

### H-02 Validar WEB_APP_URL exacto en Plesk

Severidad:

```text
Media
```

Estado:

```text
Pendiente de validar en Plesk
```

Detalle:

El webhook sin token valida el origen contra `WEB_APP_URL`.

Debe estar exactamente como:

```env
WEB_APP_URL="https://app.web.josehurtado.dev"
```

Si se usa otra variante, por ejemplo con `www`, otro subdominio, HTTP o barra final mal configurada, el formulario puede rechazarse como origen no permitido.

### H-03 Correo SMTP ya funciona, pero requiere prueba de entrega final

Severidad:

```text
Media
```

Estado:

```text
En seguimiento
```

Detalle:

El CRM ya detecta SMTP y el correo de confirmacion llega al usuario. Se corrigio:

- Logo roto en el email.
- Referencia interna `php_...` visible en el correo.

Prueba pendiente:

- Enviar formulario real desde la web.
- Confirmar que llega el email al contacto.
- Confirmar que aparece el lead en `Admin CRM > Contactos`.
- Confirmar si Plesk registra el envio en seguimiento de correo.

### H-04 Revisar modo oscuro despues de cambios visuales

Severidad:

```text
Baja
```

Estado:

```text
Pendiente de barrido visual
```

Detalle:

Se corrigio un problema de celdas vacias de tabla en modo oscuro/claro, pero conviene hacer un barrido visual de:

- Admin CRM.
- Web comercial.
- Clases/reservas.
- Modales y confirmaciones.
- Formularios con selects custom.

### H-05 Confirmar permisos de uploads en Plesk

Severidad:

```text
Media
```

Estado:

```text
Pendiente de prueba manual
```

Detalle:

El CRM sube imagenes de:

- Perfil de usuario.
- Foto de socio.

Necesita poder escribir en:

```text
php-app/public/uploads
```

Prueba necesaria:

- Subir foto de perfil.
- Quitar foto.
- Subir foto de socio.
- Quitar foto.

### H-06 Reservas necesita validacion funcional completa

Severidad:

```text
Media
```

Estado:

```text
Pendiente de prueba manual
```

Detalle:

El codigo contiene:

- Tabla `reservations`.
- Filtro por `tenant_id`.
- Validacion de socio activo.
- Validacion de aforo.
- Reutilizacion de reserva cancelada.
- Estados `reserved`, `cancelled`, `attended`, `no_show`.

Pruebas necesarias:

- Crear reserva desde clase.
- Cancelar reserva.
- Marcar asistencia.
- Marcar no-show.
- Intentar duplicar socio en la misma clase.
- Llenar aforo y comprobar bloqueo.
- Ver historial en ficha de socio.

### H-07 Empresas/cliente necesita prueba de regresion

Severidad:

```text
Media
```

Estado:

```text
Pendiente de prueba manual
```

Detalle:

Se corrigio anteriormente que crear empresa editaba la primera. Debe revalidarse:

- Crear cliente.
- Crear empresa desde cliente.
- Crear tenant y usuario jefe.
- Editar empresa existente.
- Entrar en CRM de esa empresa desde soporte.
- Volver a Admin CRM.

## Checklist manual recomendada en Plesk

### Despliegue

- [ ] `app.crm.josehurtado.dev` abre login.
- [ ] `app.web.josehurtado.dev` abre web publica.
- [ ] Document root CRM apunta a `php-app/public`.
- [ ] Document root web apunta a `web-app/public`.
- [ ] `php-app/.env` tiene DB real.
- [ ] `php-app/.env` tiene SMTP real.

### Login

- [ ] Login admin plataforma: `admin@membora.crm`.
- [ ] Login admin gimnasio: `admin@nexofit.demo`.
- [ ] Credenciales erroneas muestran error comprensible.
- [ ] Cerrar sesion funciona.

### Admin CRM

- [ ] Dashboard carga metricas.
- [ ] Contactos lista solicitudes web y clientes comerciales.
- [ ] Crear cliente.
- [ ] Convertir lead web a cliente.
- [ ] Eliminar lead web con confirmacion.
- [ ] Crear empresa nueva.
- [ ] Precio mensual se autocompleta al elegir plan.
- [ ] Registrar pago SaaS.
- [ ] Crear/editar plan.
- [ ] Enviar correo de prueba.
- [ ] Entrar al CRM de una empresa en modo soporte.
- [ ] Volver a Admin CRM.

### Web publica

- [ ] Navegacion por anclas funciona.
- [ ] Demo publica abre una sesion funcional con contador de 20 minutos.
- [ ] Formulario envia solicitud.
- [ ] Mensaje de exito aparece.
- [ ] Lead aparece en `Admin CRM > Contactos`.
- [ ] Email de confirmacion llega al contacto.
- [ ] Honeypot no es visible.

### CRM gimnasio

- [ ] Dashboard carga sin textos negros en modo oscuro.
- [ ] Buscador global muestra resultados.
- [ ] Leads: crear, editar, etapa, notas, convertir, eliminar.
- [ ] Socios: crear, foto, membresia, historial, eliminar.
- [ ] Membresias: crear plan, editar, calcular caducidad.
- [ ] Clases: crear tipo, crear clase, editar, calendario, eliminar.
- [ ] Reservas: crear, cancelar, asistencia, no-show, aforo.
- [ ] Tareas: crear con usuario responsable, editar, iconos, estados y eliminar.
- [ ] Usuarios: crear, editar rol en espanol, desactivar.
- [ ] Perfil: editar datos, foto, password.
- [ ] Configuracion: claro/oscuro, color, compactar.

## Resultado global de esta pasada

Estado tecnico:

```text
Estable a nivel de sintaxis, rutas publicas y assets.
```

Estado funcional:

```text
Necesita prueba manual con base de datos real en Plesk para cerrar reservas, empresas, uploads y correos.
```

Prioridad recomendada antes de darlo por cerrado:

1. Probar formulario web real completo.
2. Probar Admin CRM completo.
3. Probar reservas con aforo.
4. Probar uploads.
5. Barrido visual en modo oscuro y responsive.
