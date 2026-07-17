# Checklist de entrega del TFM - Membora CRM

Fecha oficial de entrega indicada en la guia: **20/07/2026**.

## 1. Documentacion obligatoria

Estado actual:

- [x] Descripcion general del proyecto.
- [x] Stack tecnologico utilizado.
- [x] Informacion de instalacion y despliegue.
- [x] Estructura del proyecto.
- [x] Funcionalidades principales.
- [x] Credenciales de prueba.
- [x] URL del repositorio.
- [x] Estado actual de la version PHP documentado.
- [ ] URL publica definitiva validada en produccion.
- [ ] URL publica de slides.
- [ ] URL publica del video.
- [ ] Nombre completo del alumno.
- [ ] Email utilizado en la inscripcion del master.
- [ ] Licencia o nota academica final.

## 2. Codigo fuente

Repositorio:

```text
https://github.com/Jacketring/Membora-CRM.git
```

Estado:

- [x] Codigo fuente organizado.
- [x] Version PHP operativa.
- [x] Documentacion de despliegue en Plesk.
- [x] Separacion de archivos sensibles mediante `.env`.
- [ ] Confirmar si el repositorio estara publico o privado en la entrega.

## 3. Despliegue

Despliegue objetivo:

```text
https://membora.es/app/
```

Estado:

- [x] Despliegue PHP en Plesk definido.
- [x] Document root unico: `httpdocs`; CRM disponible en `/app/`.
- [x] Base de datos MariaDB.
- [x] Produccion sin Node.js.
- [x] Produccion sin build frontend.
- [x] Configuracion mediante `apps/crm/.env`.
- [ ] Validar URL final justo antes de entregar.
- [ ] Validar login con usuarios demo justo antes de entregar.

## 4. Funcionalidades a mostrar en la demo

Recorrido recomendado:

1. Login con administrador del gimnasio.
2. Dashboard del gimnasio.
3. Leads: filtros, notas, cambio de etapa y conversion a socio.
4. Socios: ficha, foto y membresia.
5. Membresias: precio, duracion y caducidad.
6. Pagos: importe, metodo, estado y vencimiento.
7. Facturacion: configuracion, exportacion CSV, sincronizacion simulada y logs.
8. Check-ins: entrada manual y asociacion a reservas.
9. Alertas: pagos vencidos, tareas, membresias, leads, actividad y clases llenas.
10. Clases: calendario, creacion, edicion y reservas.
11. Tareas: usuario responsable, vencimiento, estados y acciones.
12. Auditoria: acciones registradas y filtros.
13. Usuarios internos y permisos por rol.
14. Perfil y configuracion visual.
15. Login como administrador de plataforma.
16. Panel `Admin CRM`: contactos, empresas, renovaciones, facturas, planes, auditoria y acceso de soporte. No abrir la herramienta oculta de correo salvo que sea necesario diagnosticar SMTP.
17. Web publica: formulario, opiniones y enlaces legales.
18. Mostrar el plan actual y `Mejorar el plan`: desde `TRIAL` se elige un plan y desde Basic, Pro o Business solo se permiten ascensos simulados; explicar que Stripe Live todavia no esta activo.

## 5. Slides

Los materiales antiguos de presentacion se retiraron del repositorio porque describian carpetas y pantallas anteriores. No existe actualmente un PPTX canonico dentro del proyecto.

Pendiente fuera del repositorio:

- [ ] Preparar una presentacion nueva a partir de la arquitectura y funcionalidades vigentes.
- [ ] Revisar que la demo incluya planes canonicos, checkout simulado y mejora sin downgrade.
- [ ] Publicar la URL de las slides si la entrega lo exige.

Contenido recomendado:

- Problema detectado.
- Objetivo del proyecto.
- Publico objetivo.
- Alcance del MVP.
- Decision de migracion a PHP.
- Arquitectura tecnica.
- Modelo de datos multiempresa.
- Demo funcional.
- Pruebas realizadas.
- Despliegue en Plesk.
- Conclusiones y lineas futuras.

## 6. Video explicativo

Pendiente:

- [ ] Grabar video con explicacion propia.
- [ ] Capturar pantalla durante la explicacion.
- [ ] Publicar URL de acceso publico.
- [ ] Anadir URL del video al README.

Guion recomendado:

1. Presentacion breve del proyecto.
2. Problema que resuelve.
3. Stack final y motivos de PHP/Plesk.
4. Login con usuario demo.
5. Recorrido funcional por los modulos implementados.
6. Explicacion del modelo multiempresa con `tenant_id`.
7. Panel `Admin CRM` para empresas cliente.
8. Pruebas y despliegue.
9. Conclusiones y mejoras futuras.

## 7. Documentos internos del repositorio

- [x] `docs/00-checklist-entrega-tfm.md`
- [x] `docs/01-alcance-mvp.md`
- [x] `docs/02-requisitos.md`
- [x] `docs/03-historias-usuario.md`
- [x] `docs/04-modelo-datos.md`
- [x] `docs/05-pruebas.md`
- [x] `docs/06-api-backend.md`
- [x] `docs/07-estado-actual-php.md`
- [x] `docs/08-auditoria-testing-2026-06-29.md`
- [x] `docs/09-seguridad-y-captacion-web.md`
- [x] `docs/10-incidencias-y-soluciones.md`
- [x] `docs/11-web-publica.md`
- [x] `docs/13-historial-cambios-recientes.md`
- [x] `docs/16-stripe-billing-saas.md`
- [x] `docs/18-arquitectura-y-flujos.md`
- [x] `docs/19-metodologia-desarrollo.md`

## 8. Riesgos pendientes

- Hay que validar credenciales y URL final antes de grabar el video.
- Hay que revisar que el README final incluya video, nombre y email del alumno y, si se prepara una nueva presentacion externa, su enlace.
- Hay que hacer una solicitud real de prueba con un correo controlado antes de grabar si ese flujo va a mostrarse; no revelar la contrasena recibida en la grabacion.
- La ruta interna `platform-web` y los detalles SMTP/Stripe deben permanecer fuera del video salvo una explicacion tecnica sin secretos.
