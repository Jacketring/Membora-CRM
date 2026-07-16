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
16. Panel `Admin CRM`, leads web, empresas, pagos, logs, web comercial y acceso de soporte.
17. Web publica: formulario, opiniones y enlaces legales.

## 5. Slides

Estado:

- [x] Crear presentacion del proyecto.
- [x] Adjuntar el documento al codigo.
- [x] Anadir referencia de slides al README.
- [ ] Publicar URL de acceso publico si la entrega lo exige.

Archivo:

```text
docs/entrega/membora-crm-tfm-presentacion.pptx
```

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
- [x] `docs/07-estado-actual-php.md`
- [x] `docs/08-auditoria-testing-2026-06-29.md`
- [x] `docs/09-seguridad-y-captacion-web.md`
- [x] `docs/10-incidencias-y-soluciones.md`
- [x] `docs/19-metodologia-desarrollo.md`

## 8. Riesgos pendientes

- Hay que validar credenciales y URL final antes de grabar el video.
- Hay que revisar que el README final incluya slides, video, nombre y email del alumno.
