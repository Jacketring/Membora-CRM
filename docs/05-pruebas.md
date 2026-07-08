# Plan de pruebas - Membora CRM

## 1. Objetivo

Este documento define las pruebas manuales recomendadas para validar la version PHP de Membora CRM antes de la entrega del TFM.

El objetivo es comprobar que:

- La aplicacion carga en Plesk desde `php-app/public`.
- El login funciona con usuarios demo.
- Los datos se separan por `tenant_id`.
- Los modulos principales funcionan sin errores 500.
- El panel de administracion SaaS permite gestionar empresas cliente.
- La aplicacion no depende de Node.js en produccion.

## 2. Entorno de pruebas

URL objetivo:

```text
https://app.crm.josehurtado.dev
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
Password: MemboraDemo2026!
```

Credenciales de plataforma:

```text
Email: admin@membora.crm
Password: MemboraAdmin2026!
```

## 3. Pruebas de despliegue

### PD-01 Document root

Pasos:

1. Entrar en Plesk.
2. Revisar configuracion de hosting.
3. Confirmar que la raiz del documento apunta a `php-app/public`.

Resultado esperado:

- La URL abre la aplicacion PHP y no una pagina generica de hosting.

### PD-02 Conexion a base de datos

Pasos:

1. Revisar `php-app/.env`.
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

1. Abrir `https://app.web.josehurtado.dev`.
2. Pulsar un enlace de demo.
3. Confirmar que se inicia sesion automaticamente en el CRM con datos de prueba.
4. Verificar que aparece un contador de 20 minutos en la parte superior.
5. Forzar o esperar el fin de la sesion temporal.

Resultado esperado:

- La demo no abre una version estatica separada.
- El CRM muestra una sesion funcional con datos demo.
- Al finalizar el contador, se cierra la sesion y se vuelve a la web publica.

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

## 6. Pruebas tecnicas locales

Comandos usados antes de subir cambios:

```bash
php -l archivo.php
node --check php-app/public/assets/app.js
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
