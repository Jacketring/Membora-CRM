# Metodología de desarrollo y validación

Fecha de actualización: 17/07/2026.

## 1. Enfoque

Membora CRM se ha desarrollado mediante una metodología **incremental, orientada a requisitos y apoyada por pruebas automatizadas**. El proyecto no aplica Scrum formal —no existe un equipo con todos sus roles ni ceremonias— y tampoco se presenta como TDD estricto. Se utiliza un ciclo adaptado a un proyecto individual de TFM:

```text
Alcance → requisitos → historias de usuario → especificación y criterios de aceptación
        → pruebas → implementación → revisión automática → despliegue → validación
```

Cada incremento aborda un recorrido funcional concreto, por ejemplo la conversión de un lead en socio, la reserva de una clase o el alta de una prueba de 14 días. El objetivo es que cada decisión pueda relacionarse con una necesidad, una implementación y una evidencia verificable.

## 2. Fases de trabajo

### 2.1 Definición del alcance

El MVP se delimita antes de implementar para separar lo demostrable de las mejoras futuras. El alcance vigente se conserva en `docs/01-alcance-mvp.md`.

### 2.2 Requisitos e historias de usuario

Los requisitos funcionales y no funcionales se documentan en `docs/02-requisitos.md`. Las necesidades de cada perfil se expresan mediante historias de usuario y criterios de aceptación en `docs/03-historias-usuario.md`.

### 2.3 Especificación del incremento

Los cambios que requieren reglas de negocio o decisiones de seguridad se concretan en `docs/specs/`. Una especificación define objetivo, entradas, salidas, reglas y criterios de aceptación antes de considerar terminada la funcionalidad.

### 2.4 Diseño técnico

La arquitectura, los flujos y el modelo de datos se documentan en `docs/04-modelo-datos.md` y `docs/18-arquitectura-y-flujos.md`. Las decisiones que condicionan el proyecto se registran como ADR en `docs/adr/`, incluyendo el monolito PHP, el aislamiento multiempresa, las sesiones nativas y la evolución del esquema.

### 2.5 Implementación incremental

La funcionalidad se implementa en cambios pequeños y revisables. Las vistas, acciones, repositorios y controles de seguridad mantienen responsabilidades diferenciadas dentro del monolito PHP. Cuando un cambio modifica el comportamiento esperado, se actualizan también sus pruebas y la documentación afectada.

### 2.6 Verificación

La validación combina:

- PHPUnit para reglas, permisos, seguridad y comportamiento del backend.
- PHPStan para análisis estático.
- Comprobación de sintaxis PHP.
- Playwright para recorridos completos en un entorno E2E preparado.
- Pruebas manuales de despliegue y de los módulos principales.
- Revisión de diferencias con `git diff --check` antes de integrar cambios.

El plan detallado y los casos manuales están en `docs/05-pruebas.md`.

### 2.7 Integración continua y despliegue

GitHub Actions ejecuta las comprobaciones del proyecto en cada `push` y `pull_request`. El job PHP instala dependencias, valida sintaxis, ejecuta PHPUnit con cobertura, comprueba el umbral configurado y ejecuta PHPStan. El job E2E solo se activa cuando existe un staging configurado mediante `E2E_BASE_URL`.

Después de superar estas comprobaciones, el incremento puede desplegarse en Plesk. Producción ejecuta PHP 8.2 y MariaDB; Node.js se limita a las pruebas E2E y no forma parte del runtime productivo.

## 3. Trazabilidad

| Fase | Evidencia principal |
|---|---|
| Alcance | `docs/01-alcance-mvp.md` |
| Requisitos | `docs/02-requisitos.md` |
| Historias y aceptación | `docs/03-historias-usuario.md` |
| Especificaciones | `docs/specs/` |
| Diseño y decisiones | `docs/04-modelo-datos.md`, `docs/18-arquitectura-y-flujos.md`, `docs/adr/` |
| Implementación | `apps/crm/src/`, `apps/crm/public/`, `httpdocs/` |
| Pruebas | `apps/crm/tests/`, `e2e/`, `docs/05-pruebas.md` |
| Integración continua | `.github/workflows/ci.yml` |
| Incidencias y aprendizaje | `docs/10-incidencias-y-soluciones.md` |
| Despliegue y defensa | `README.md`, `docs/entrega/` |

## 4. Criterio de finalización de un incremento

Un cambio se considera terminado cuando, de forma proporcional a su alcance:

1. Responde a un requisito, historia o necesidad identificada.
2. Sus reglas y criterios de aceptación están claros.
3. La implementación respeta aislamiento por `tenant_id`, permisos y validación de entradas.
4. Las pruebas automatizadas relacionadas pasan.
5. PHPStan y la sintaxis no introducen errores.
6. El recorrido se valida manualmente cuando afecta a interfaz o despliegue.
7. La documentación y las cifras de calidad quedan actualizadas.
8. El cambio se integra en `main` y GitHub Actions confirma el resultado.

## 5. Estado verificable de calidad

La ejecución local repetida el 17/07/2026 completó **54 tests y 251 aserciones** sin errores. PHPStan también finalizó sin errores.

La última medición de cobertura guardada corresponde al 11/07/2026: **93,50 % de líneas (604/646)** en la capa lógica configurada. Esta cifra es una evidencia histórica y no debe confundirse con una medición de todo el producto ni con el número actual de pruebas. El pipeline exige al menos un 80 % de cobertura de sentencias sobre la capa incluida en su filtro.

## 6. Resumen para la defensa

> He seguido un desarrollo incremental orientado a requisitos. Cada funcionalidad parte de una historia o especificación con criterios de aceptación, se implementa y se valida con pruebas automáticas y manuales. GitHub Actions revisa sintaxis, PHPUnit, cobertura y PHPStan antes del despliegue en Plesk. No presento el proceso como Scrum o TDD estricto, sino como una metodología adaptada a un proyecto individual, trazable y apoyada por integración continua.
