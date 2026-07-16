# Membora CRM — presentación y guion de defensa

Defensa objetivo: **10 minutos y 30 segundos**, incluida una demostración breve de unos 3 minutos.

## Criterio narrativo y visual

La presentación debe contar una historia: un gimnasio recibe un contacto, lo convierte en socio y gestiona su relación desde el mismo sistema. La arquitectura, la seguridad y las pruebas aparecen después como la explicación de por qué ese recorrido es fiable.

Estilo recomendado:

- Formato panorámico 16:9.
- Fondo blanco o azul marino, con azul eléctrico como color de acción.
- Logotipo SVG oficial de Membora, sin redibujarlo.
- Tipografía sans serif limpia: Aptos, Inter o equivalente.
- Una idea principal y un elemento de prueba por diapositiva.
- Capturas reales a pantalla completa, recortadas para eliminar pestañas, favoritos y datos personales.
- Máximo aproximado de 25–35 palabras visibles por diapositiva, salvo pequeños rótulos de diagramas.
- No utilizar puntuaciones Lighthouse, usuarios, ingresos o conversiones que no se hayan medido.

## 1. Estructura de 11 diapositivas

### Diapositiva 1 — Membora conecta la captación con la gestión diaria

- **Objetivo:** presentar el proyecto y su propuesta en una frase.
- **Texto en pantalla:**
  - `Membora CRM`
  - `Software SaaS de gestión interna para gimnasios`
  - `Del primer contacto al socio activo`
- **Visual recomendado:** logotipo oficial a la izquierda y una composición con una captura real del dashboard o del listado de socios a la derecha.
- **Captura real:** dashboard del gimnasio con indicadores visibles y sin datos personales.
- **Explicación oral:** definir Membora como herramienta interna para propietarios, recepción, comerciales, entrenadores y administración de plataforma.
- **Duración:** 35 segundos.

### Diapositiva 2 — La información dispersa rompe el seguimiento

- **Objetivo:** explicar el problema antes de enseñar funcionalidades.
- **Texto en pantalla:**
  - `Leads sin continuidad`
  - `Reservas y cuotas desconectadas`
  - `Poca trazabilidad`
  - Frase central: `El problema no es guardar datos; es mantener conectado todo el recorrido.`
- **Visual recomendado:** una línea fragmentada con tres puntos separados —captación, operación y control— que converge después en Membora.
- **Captura real:** no es necesaria; utilizar un diagrama editable sencillo.
- **Explicación oral:** describir cómo Excel, mensajes, llamadas y herramientas aisladas producen duplicidades, olvidos y falta de contexto.
- **Duración:** 45 segundos.

### Diapositiva 3 — Un único flujo convierte el interés en relación comercial

- **Objetivo:** enseñar el recorrido principal del producto y su relación con el negocio.
- **Texto en pantalla:**
  - `Formulario web → Lead → Socio → Membresía → Pago → Retención`
  - `Un solo dato, varias áreas de trabajo`
- **Visual recomendado:** flujo horizontal continuo con seis etapas y conectores claros.
- **Captura real:** [lead convertido en socio](../../e2e/evidence/lead-convertido-socio.png), recortada al listado y al resultado de conversión.
- **Explicación oral:** destacar que no son módulos aislados; cada acción alimenta la siguiente y mantiene el historial.
- **Duración:** 45 segundos.

### Diapositiva 4 — El producto cubre venta, operación y control

- **Objetivo:** resumir el alcance funcional sin hacer una lista interminable.
- **Texto en pantalla:**
  - `Venta: leads, notas y conversión`
  - `Operación: socios, clases, reservas y check-ins`
  - `Control: membresías, pagos, tareas, alertas y auditoría`
  - `Plataforma: empresas, planes, facturación y soporte`
- **Visual recomendado:** tres carriles conectados y una franja superior de administración SaaS. Cada carril representa una responsabilidad real, no una tarjeta decorativa.
- **Captura real:** pantalla de socios para operación y una captura del Admin CRM para la franja SaaS.
- **Explicación oral:** explicar que cada rol ve el trabajo que necesita y que el panel SaaS está separado del CRM de cada gimnasio.
- **Duración:** 45 segundos.

### Diapositiva 5 — Una arquitectura sencilla reduce el riesgo de despliegue

- **Objetivo:** justificar la elección técnica.
- **Texto en pantalla:**
  - `Web pública y CRM bajo membora.es`
  - `PHP 8.2 · PDO · MariaDB`
  - `Despliegue directo en Plesk`
  - `Node.js solo para pruebas E2E, no en producción`
- **Visual recomendado:** diagrama concreto:
  - Navegador → `httpdocs/app/index.php`
  - Router PHP → Acciones / Vistas
  - Acciones → Repositorios → PDO → MariaDB
  - Web pública → API de captación → `platform_leads`
- **Captura real:** no es necesaria; mostrar el diagrama editable y, como detalle pequeño, la estructura real del repositorio.
- **Explicación oral:** justificar el monolito como una decisión consciente para un MVP desplegable, mantenible y adecuado al entorno Plesk.
- **Duración:** 55 segundos.

### Diapositiva 6 — `tenant_id` convierte una aplicación en una plataforma multiempresa

- **Objetivo:** explicar multitenancy, roles y administración SaaS de forma comprensible.
- **Texto en pantalla:**
  - `Una base de datos compartida`
  - `Datos separados por tenant_id`
  - `Permisos por ruta y por acción`
  - `Soporte controlado desde Admin CRM`
- **Visual recomendado:** tres gimnasios en carriles separados que llegan a las mismas tablas, cada uno filtrado por un `tenant_id` diferente. Un carril superior representa al superadministrador sin mezclarlo con los usuarios del gimnasio.
- **Captura real:** Admin CRM en la pantalla de empresas o modo soporte, sin información sensible.
- **Explicación oral:** utilizar la analogía de un edificio: comparten infraestructura, pero cada gimnasio tiene su espacio y sus llaves.
- **Duración:** 50 segundos.

### Diapositiva 7 — La seguridad se aplica en varias capas

- **Objetivo:** demostrar que la seguridad no depende de una única comprobación.
- **Texto en pantalla:**
  - `Sesiones y contraseñas seguras`
  - `CSRF + validación de origen`
  - `PDO y consultas preparadas`
  - `Roles y permisos`
  - `Auditoría sin secretos`
- **Visual recomendado:** cinco capas concéntricas o una secuencia vertical desde la petición hasta la auditoría. Evitar iconos genéricos de “escudo” como elemento principal.
- **Captura real:** pantalla de auditoría con acciones permitidas del gimnasio; no mostrar eventos internos de soporte o navegación sin valor operativo.
- **Explicación oral:** explicar qué riesgo resuelve cada capa y mencionar que contraseñas, tokens y secretos no se guardan en la auditoría ni en Git.
- **Duración:** 50 segundos.

### Diapositiva 8 — La calidad se comprueba antes de desplegar

- **Objetivo:** diferenciar el proyecto de una simple maqueta académica.
- **Texto en pantalla:**
  - `50 tests · 243 aserciones`
  - `PHPUnit · PHPStan · GitHub Actions · Playwright`
  - Frase central: `No solo he desarrollado una aplicación funcional; he construido, probado y desplegado un producto siguiendo una metodología profesional.`
- **Visual recomendado:** pipeline horizontal: especificación → tests → implementación → CI → Plesk. Incorporar una captura real de una ejecución correcta de GitHub Actions cuando esté disponible.
- **Captura real:** página Actions del repositorio mostrando los jobs de PHP y E2E; no mostrar secretos ni URLs privadas.
- **Explicación oral:** indicar que CI revisa sintaxis, PHPUnit, cobertura mínima, PHPStan y E2E cuando existe un staging configurado.
- **Duración:** 50 segundos.

### Diapositiva 9 — La web también forma parte del producto

- **Objetivo:** explicar captación, SEO, accesibilidad y experiencia de usuario.
- **Texto en pantalla:**
  - `“Software para gimnasios”: intención de búsqueda clara`
  - `SEO on-page · sitemap · robots.txt · datos estructurados`
  - `Foco visible · teclado · responsive`
  - `Demo 20 min + prueba propia 14 días`
- **Visual recomendado:** captura real de la portada en ordenador y una vista móvil real. Señalar discretamente el CTA, no encerrar toda la captura en varias tarjetas.
- **Captura real:** portada de `membora.es` y formulario de prueba de 14 días, después de comprobar que el despliegue funciona.
- **Explicación oral:** diferenciar claramente “Entrar”, la demo temporal y la prueba self-service. No citar puntuaciones Lighthouse si no se han medido en producción.
- **Duración:** 45 segundos.

### Diapositiva 10 — Demostración: del formulario al trabajo diario

- **Objetivo:** demostrar en directo el recorrido completo.
- **Texto en pantalla:**
  - `1. Captar`
  - `2. Convertir`
  - `3. Operar`
  - `4. Controlar`
- **Visual recomendado:** una barra de progreso simple que permanezca como referencia mientras se entra en la aplicación.
- **Captura real:** no utilizar una captura fija como prueba principal; cambiar a la web y al CRM en directo. Como respaldo, tener preparadas [lead convertido en socio](../../e2e/evidence/lead-convertido-socio.png) y [clase con reserva](../../e2e/evidence/clase-reserva.png).
- **Explicación oral:** seguir el recorrido detallado de la sección de demostración práctica.
- **Duración:** 3 minutos.

### Diapositiva 11 — El MVP ya es un producto desplegado y tiene un siguiente paso claro

- **Objetivo:** cerrar con resultados, aprendizaje y futuro realista.
- **Texto en pantalla:**
  - `Producto real: web + CRM + plataforma SaaS`
  - `Recorrido completo y trazable`
  - `Despliegue real en Plesk`
  - `Siguiente etapa: cobro real, móvil, integraciones y personalización`
- **Visual recomendado:** tres hitos realizados y una flecha hacia cuatro líneas futuras. No presentar el registro de prueba como futuro: ya está implementado.
- **Captura real:** una composición final con web, CRM y Admin CRM, siempre con pantallas reales.
- **Explicación oral:** resumir qué conocimientos se han aplicado, qué se ha aprendido y cuáles son las mejoras que requieren una fase posterior.
- **Duración:** 40 segundos.

## 2. Guion completo de la defensa

### Diapositiva 1

> Buenos días. Mi Trabajo de Fin de Máster es Membora CRM, un software SaaS de gestión interna para gimnasios y centros deportivos. Lo he diseñado para conectar todo el recorrido: desde que una persona solicita información hasta que se convierte en socio y el equipo gestiona su membresía, pagos, clases y seguimiento. No es una aplicación para los clientes finales del gimnasio, sino una herramienta de trabajo para propietarios, recepción, comerciales, entrenadores y administradores.

**[Cambiar a la diapositiva 2]**

### Diapositiva 2

> El problema que observé es que muchas tareas se gestionan con herramientas separadas: hojas de cálculo para socios, mensajes para consultas, calendarios para clases y anotaciones para pagos o llamadas. El dato existe, pero pierde continuidad. Esto provoca duplicidades, seguimientos olvidados y poca trazabilidad. Por eso el objetivo no fue crear otra pantalla donde guardar nombres, sino conectar captación, operación y control en un mismo flujo.

**[Cambiar a la diapositiva 3 y señalar el flujo de izquierda a derecha]**

### Diapositiva 3

> El núcleo de Membora es este recorrido. Una solicitud de la web entra automáticamente como lead. El equipo puede añadir notas, cambiar su etapa y convertirlo en socio. Desde ahí se le asigna una membresía, se registran pagos y se relaciona con reservas, check-ins, tareas y alertas. La ventaja es que cada paso conserva el contexto anterior. Así, el sistema une la actividad comercial con la operación y con la trazabilidad económica del gimnasio.

**[Enseñar la captura real de conversión y cambiar a la diapositiva 4]**

### Diapositiva 4

> Para mantener el producto comprensible, agrupé el alcance por trabajo real. En venta están los leads, notas y conversiones. En operación están socios, membresías, clases, reservas y check-ins. En control aparecen pagos, tareas, alertas y auditoría. Por encima existe un Admin CRM separado, desde el que Membora gestiona empresas cliente, planes, facturación y soporte. De esta forma, un trabajador del gimnasio no ve herramientas internas de la plataforma que no le corresponden.

**[Cambiar a la diapositiva 5 y enseñar el diagrama]**

### Diapositiva 5

> Técnicamente elegí una arquitectura monolítica en PHP 8.2 con MariaDB y PDO. La web pública y el CRM se sirven bajo el mismo dominio, mientras el código sensible permanece fuera del directorio público. La petición entra por un punto controlado, pasa por las acciones, los repositorios y finalmente llega a MariaDB mediante consultas preparadas. Esta arquitectura reduce procesos, dependencias y problemas de despliegue en Plesk. Node.js se utiliza únicamente para las pruebas end-to-end, no para ejecutar producción.

**[Destacar la decisión técnica: simplicidad operativa antes que complejidad innecesaria. Cambiar a la diapositiva 6]**

### Diapositiva 6

> Membora es multiempresa. Puede entenderse como un edificio compartido: existe una infraestructura común, pero cada gimnasio tiene su espacio y sus llaves. Ese aislamiento se realiza mediante tenant_id, obtenido desde la sesión y utilizado para filtrar los datos operativos. Además, los permisos se comprueban tanto al abrir una ruta como al ejecutar una acción. El superadministrador está separado y puede entrar en modo soporte de manera controlada sin convertir a los usuarios del gimnasio en administradores de plataforma.

**[Enseñar el diagrama de tenants y cambiar a la diapositiva 7]**

### Diapositiva 7

> La seguridad se ha planteado por capas. Las contraseñas se almacenan con hash y las sesiones utilizan cookies seguras. Los formularios internos se protegen contra CSRF y validan el origen. PDO reduce el riesgo de inyección SQL. La matriz de permisos evita que un rol ejecute acciones que no le corresponden. Finalmente, la auditoría registra cambios útiles, pero elimina contraseñas, tokens y secretos de sus metadatos. Ninguna barrera es suficiente por sí sola; juntas reducen el riesgo.

**[Cambiar a la diapositiva 8 y mostrar GitHub Actions]**

### Diapositiva 8

> La calidad también forma parte del producto. Actualmente la suite automatizada ejecuta 50 tests con 243 aserciones. He seguido una metodología incremental adaptada a un proyecto individual: alcance, requisitos, historias, especificación y criterios de aceptación, pruebas, implementación, integración continua y validación del despliegue. GitHub Actions valida la sintaxis PHP, PHPUnit, el umbral de cobertura configurado y el análisis estático con PHPStan. Los flujos principales también tienen pruebas Playwright cuando se configura un entorno E2E seguro. No lo presento como Scrum o TDD estricto, sino como un proceso trazable y apoyado por pruebas. Por eso no solo he desarrollado una aplicación funcional; he construido, probado y desplegado un producto siguiendo una metodología profesional.

**[Cambiar a la diapositiva 9 y enseñar la captura de la web]**

### Diapositiva 9

> La web comercial no es un elemento aislado. Utiliza la expresión “software para gimnasios” porque explica de forma directa qué ofrece el producto y responde mejor a la intención de búsqueda. Se han trabajado title, description, canonical, Open Graph, datos estructurados, sitemap y robots. En accesibilidad se han añadido foco visible, navegación por teclado, áreas táctiles y reducción de movimiento. También se diferencia el login real, la demo temporal de 20 minutos y la prueba propia de 14 días con verificación de correo.

**[Cambiar a la diapositiva 10 y entrar en la aplicación]**

### Diapositiva 10 — narración durante la demo

> Voy a enseñar ahora el recorrido completo con un ejemplo. Empiezo en la web pública y envío una solicitud con datos preparados para esta defensa. Al entrar en Admin CRM, esa solicitud aparece como un nuevo contacto; no he copiado el dato manualmente. Lo abro, añado una nota y avanzo su estado. A continuación lo convierto en socio del gimnasio. En su ficha puedo asociar una membresía y consultar pagos e historial. Después muestro una clase con aforo, creo o enseño una reserva y compruebo su relación con el check-in. Para terminar, vuelvo brevemente al panel de plataforma para mostrar que un mismo producto separa el trabajo del gimnasio de la administración SaaS.

**[Si alguna petición tarda más de cinco segundos, pasar a las capturas de respaldo y continuar sin disculpas largas. Volver a la presentación y cambiar a la diapositiva 11]**

### Diapositiva 11

> Como resultado, Membora reúne una web comercial, un CRM operativo y una capa de administración SaaS desplegadas en un entorno real. He aplicado arquitectura de software, modelado de datos, seguridad, pruebas, integración continua, diseño responsive, SEO y despliegue. El principal aprendizaje ha sido que un producto no termina cuando una pantalla funciona: también necesita trazabilidad, mantenimiento y una forma segura de llegar a producción. Como siguientes pasos planteo activar y validar el cobro real en producción, una aplicación móvil, más integraciones externas y mayor personalización por gimnasio. Muchas gracias.

## 3. Demostración práctica de 3–4 minutos

### Preparación previa de datos

Preparar antes de grabar:

- Un navegador limpio, sin barra de favoritos visible y con zoom al 100 %.
- Una cuenta de gimnasio de demostración con rol administrador, nunca credenciales reales mostradas en pantalla.
- Una cuenta separada de Admin CRM.
- Un gimnasio de prueba con un plan de membresía visible y una clase futura con al menos dos plazas libres.
- Un nombre único y fácil de localizar: `Ana Defensa Membora`.
- Un email de demostración controlado y un teléfono ficticio coherente.
- Una nota breve preparada: `Solicita información sobre clases de fuerza`.
- Una membresía preparada: `Plan Mensual Demo`.
- Un pago o factura de demostración ya creado como respaldo.
- Una clase preparada: `Full Body Demo`, con fecha y horario visibles.
- Las dos capturas E2E abiertas en otra pestaña como plan B.
- Notificaciones, gestor de contraseñas y correo personal ocultos.

### Recorrido cronometrado

#### 0:00–0:25 — Web comercial

1. Abrir `https://membora.es/`.
2. Señalar en una frase el posicionamiento, el CTA y la diferencia entre demo, prueba y acceso.
3. Ir al formulario de contacto.

Frase: “La captación comienza fuera del CRM, pero el dato entra directamente en el sistema”.

#### 0:25–0:50 — Crear la solicitud

1. Rellenar nombre, gimnasio, email, teléfono y mensaje preparados.
2. Aceptar privacidad.
3. Enviar y enseñar el mensaje de confirmación.

No improvisar datos ni escribir párrafos largos durante la grabación.

#### 0:50–1:20 — Comprobar el lead

1. Entrar en Admin CRM.
2. Abrir Contactos.
3. Buscar `Ana Defensa Membora`.
4. Mostrar el origen web y la fecha.
5. Añadir la nota preparada o avanzar el estado.

Frase: “La web y el CRM comparten un flujo real; no estoy duplicando el contacto manualmente”.

#### 1:20–1:55 — Convertir en socio

1. Convertir el lead o entrar en el CRM del gimnasio conectado.
2. Abrir Socios.
3. Localizar el nuevo socio.
4. Mostrar su ficha y asignar `Plan Mensual Demo` si el tiempo lo permite.

Si la conversión requiere varios modales, dejar previamente preparada la última confirmación.

#### 1:55–2:25 — Membresía y pago

1. Mostrar la membresía, precio, periodicidad y caducidad.
2. Abrir el historial de pagos o una factura ya preparada.
3. Aclarar que la facturación SaaS de Membora y los pagos internos del gimnasio son ámbitos diferentes.

#### 2:25–2:55 — Clase, reserva y check-in

1. Abrir `Full Body Demo`.
2. Mostrar aforo y reserva.
3. Señalar que un check-in puede asociarse a la reserva y marcar asistencia.
4. Utilizar la captura E2E de respaldo si el modal no responde inmediatamente.

#### 2:55–3:20 — Administración multiempresa

1. Volver a Admin CRM.
2. Enseñar empresas y planes durante pocos segundos.
3. Mostrar el acceso de soporte sin entrar a editar datos.

Frase final: “Este último cambio de contexto demuestra que el CRM del gimnasio y la gestión SaaS están separados dentro del mismo producto”.

### Plan de contingencia

- Si falla Internet, utilizar un vídeo local de la demo y narrarlo en directo.
- Si falla el formulario, mostrar la captura del contacto preparado previamente y explicar el mismo flujo.
- Si el login falla, no intentar contraseñas varias veces; pasar al vídeo o capturas.
- Si una operación tarda más de cinco segundos, continuar con el siguiente punto.
- Mantener una copia PDF de la presentación y las capturas E2E disponibles sin conexión.

## 4. Explicaciones técnicas sencillas

### Monolito PHP

Un monolito significa que routing, acciones, vistas y acceso a datos se despliegan juntos. En este proyecto reduce dependencias y simplifica Plesk. No significa que el código esté mezclado: existen responsabilidades separadas en acciones, repositorios, seguridad y vistas.

### MariaDB y PDO

MariaDB conserva los datos relacionales del negocio. PDO centraliza la conexión y permite consultas preparadas. Esto encaja con relaciones como socio–membresía, clase–reserva y empresa–tenant.

### Separación por `tenant_id`

Cada registro operativo pertenece a un gimnasio. El servidor obtiene el tenant desde la sesión y lo incorpora a las consultas; no confía en un identificador enviado libremente desde el navegador.

### Roles y permisos

Los permisos se comprueban al abrir pantallas y al modificar datos. Ocultar un botón no es una medida de seguridad suficiente; la acción del backend también debe rechazar al usuario no autorizado.

### CSRF, sesiones y contraseñas

CSRF impide que otra página envíe formularios en nombre de una sesión abierta. Membora combina token, origen y cookies seguras. Las contraseñas se comparan mediante hash y no se guardan ni se registran en auditoría.

### Integración continua y pruebas

Cada push puede ejecutar sintaxis, PHPUnit, cobertura y PHPStan. Playwright valida recorridos de interfaz en un entorno preparado. Esto reduce regresiones antes de llegar a Plesk.

### Auditoría

La auditoría responde a quién hizo qué, sobre qué entidad y cuándo. Se centra en operaciones del negocio y sanitiza tokens, contraseñas y otros secretos.

### Plesk

Plesk sirve un único dominio: la web en `/` y el CRM en `/app/`. Solo `httpdocs` es público; el código y la configuración sensible permanecen fuera del webroot.

## 5. Funcionalidades implementadas frente a mejoras futuras

### Implementado y demostrable

- Web comercial conectada con el CRM.
- Leads, conversión, socios, membresías, pagos, clases, reservas y check-ins.
- Tareas, alertas, usuarios, permisos y auditoría.
- Administración SaaS multiempresa.
- Demo temporal de 20 minutos.
- Alta self-service con verificación por email y prueba de 14 días.
- SEO técnico, accesibilidad básica y diseño responsive.
- PHPUnit, PHPStan, GitHub Actions y pruebas E2E configurables.
- Integración técnica de Stripe documentada y preparada en modo de prueba; su activación comercial real exige configuración y validación fiscal.

### Mejoras futuras

- Activación y validación completa de cobros reales en producción.
- Fiscalidad y facturación certificada, incluyendo la decisión sobre Verifactu.
- Aplicación móvil o experiencia PWA más profunda.
- Integraciones con tornos, proveedores de facturación, mensajería o calendarios.
- Personalización avanzada por gimnasio y soporte multi-sede.
- Analítica de uso y rendimiento con métricas reales de adopción.

## 6. Cierre de 30–45 segundos

> Membora no se queda en una colección de pantallas. Es un producto real y desplegado que conecta la captación de un lead con su gestión como socio, sus membresías, pagos y actividad dentro del gimnasio. Durante el proyecto he aplicado arquitectura, bases de datos, seguridad, pruebas, integración continua, experiencia de usuario y despliegue. Sobre todo, he aprendido a convertir requisitos funcionales en un sistema mantenible y demostrable. El siguiente paso es validar el producto con uso real y ampliar cobros, integraciones, movilidad y personalización sin perder la separación multiempresa ni la trazabilidad conseguida.

## 7. Lista final antes de grabar o defender

### Presentación

- [ ] 11 diapositivas en formato 16:9.
- [ ] Logotipo oficial y paleta coherente.
- [ ] Ningún párrafo largo en pantalla.
- [ ] Capturas recortadas, nítidas y sin datos personales.
- [ ] Diagrama de arquitectura legible desde el fondo del aula.
- [ ] Cifra de pruebas actualizada después del último cambio.
- [ ] Copia PPTX y PDF disponibles sin conexión.

### Aplicación

- [ ] Plesk contiene el commit que se va a defender.
- [ ] Login real comprobado en una ventana privada.
- [ ] Webhook del formulario comprobado sin crear duplicados.
- [ ] SMTP y mensaje de confirmación comprobados.
- [ ] Lead, socio, membresía, pago y clase de demostración preparados.
- [ ] Admin CRM y modo soporte accesibles.
- [ ] Ninguna contraseña o secreto aparece en pantalla.

### Grabación

- [ ] Notificaciones desactivadas.
- [ ] Escritorio, pestañas y favoritos limpiados.
- [ ] Zoom del navegador al 100 % y texto legible.
- [ ] Audio probado y micrófono colocado a distancia constante.
- [ ] Temporizador visible solo para la persona que presenta.
- [ ] Ensayo completo entre 9:30 y 10:30 minutos.
- [ ] Vídeo o capturas de respaldo abiertos localmente.
- [ ] Pausa breve después de cada idea importante.

### Veracidad

- [ ] No afirmar usuarios, ingresos, conversiones o rendimiento no medidos.
- [ ] No presentar Stripe en producción ni Verifactu como completados si no lo están.
- [ ] No presentar la prueba de 14 días como futura: ya está implementada.
- [ ] No citar Lighthouse o accesibilidad con una puntuación que no se haya guardado.
- [ ] Diferenciar pagos del gimnasio, facturación SaaS y cobro real de la suscripción.
