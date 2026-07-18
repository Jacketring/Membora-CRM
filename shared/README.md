# shared/

Recursos comunes a todas las partes del proyecto (web publica y aplicaciones).
Vive fuera de cualquier `public/`, por lo que **nunca es accesible directamente por web**.

## Contenido

- `config/`: configuracion global compartida entre aplicaciones (por ejemplo, un
  `.env` comun si en el futuro conviven varias apps PHP). Hoy cada app mantiene su
  propio `.env` en su raiz (`apps/crm/.env`); esta carpeta queda preparada para
  centralizar configuracion transversal.
- `storage/`: almacenamiento comun no publico (logs, exportaciones, cache o
  ficheros compartidos entre apps). Las fotos subidas de socios/usuarios siguen
  sirviendose como estaticos desde `apps/crm/public/uploads/` para no requerir un
  passthrough PHP.

## Por que aqui

Alinea el repositorio con las buenas practicas del master:

- El unico document root apunta a `httpdocs`; la plataforma se expone de forma controlada en `/app/` sin publicar `src`, `.env` ni el almacenamiento compartido.
- Los secretos (`.env`) y el almacenamiento quedan fuera del webroot.
- Lo comun a todo el proyecto se centraliza en la raiz por coherencia.
