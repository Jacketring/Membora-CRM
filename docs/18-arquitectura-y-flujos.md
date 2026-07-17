# Arquitectura y flujos

Fecha de actualización: 17/07/2026.

## Flujo metodológico

```mermaid
flowchart LR
    Scope[Alcance] --> Requirements[Requisitos]
    Requirements --> Stories[Historias de usuario]
    Stories --> Spec[Especificación y aceptación]
    Spec --> Tests[Pruebas]
    Tests --> Implementation[Implementación]
    Implementation --> CI[GitHub Actions]
    CI --> Deploy[Plesk]
    Deploy --> Validation[Validación]
    Validation --> Scope
```

Este ciclo incremental se detalla en `docs/19-metodologia-desarrollo.md`.

## Arquitectura del CRM

```mermaid
flowchart LR
    Browser[Navegador] --> Router[public/index.php<br/>router]
    Router --> Actions[Actions]
    Actions --> Repositories[Repositorios por dominio]
    Repositories --> PDO[PDO]
    PDO --> DB[(MariaDB)]
    Router --> Views[Views PHP]
    Views --> Browser
```

## Despliegue bajo un único dominio

```mermaid
flowchart LR
    Public[https://membora.es/] --> Web[httpdocs]
    Public --> Proxies[httpdocs/api/*.php]
    App[https://membora.es/app/] --> Bridge[httpdocs/app/index.php]
    Bridge --> PublicEntry[apps/crm/public/index.php]
    PublicEntry --> PrivateCode[apps/crm/src]
    Proxies --> PublicEntry
```

`httpdocs` es el único document root. El puente permite servir el CRM y sus recursos autorizados sin publicar directamente el código, la configuración ni los repositorios.

## Captación web

```mermaid
sequenceDiagram
    participant W as Web pública
    participant API as API webhook
    participant CRM as CRM
    participant DB as MariaDB
    participant M as Email
    W->>API: Formulario con origen público o token de tenant
    API->>API: Valida rate limit, honeypot y contacto
    API->>CRM: Payload normalizado
    CRM->>DB: Crea o actualiza lead
    CRM->>M: Envía confirmación
    CRM-->>W: Respuesta genérica
```

## Alta de prueba y recuperación

```mermaid
sequenceDiagram
    participant U as Visitante
    participant API as /api/trial
    participant DB as MariaDB
    participant M as Email
    participant CRM as CRM
    U->>API: Datos y consentimiento
    API->>API: Origen, honeypot y rate limit configurable
    API->>DB: Guarda solicitud y hash del token
    API->>M: Enlace de activación de una hora
    U->>CRM: activate-trial?token=... y confirmación POST
    CRM->>DB: Crea Cliente CRM, empresa TRIAL, tenant y administrador
    CRM->>M: Segundo enlace para revelar la credencial
    U->>CRM: trial-credentials?token=... y confirmación POST
    CRM->>DB: Consume la credencial cifrada
    CRM-->>U: Muestra la contraseña inicial una sola vez
```

El limite especifico del alta se controla con `TRIAL_RATE_LIMIT_ENABLED` y esta desactivado por defecto durante la depuracion. La validacion de origen y el honeypot siguen siendo obligatorios. La entrega usa AES-256-GCM, cabeceras `no-store` y consumo previo a la visualizacion.

La recuperación ordinaria reutiliza `auth_tokens`: responde de forma neutra, envía un enlace temporal y revoca el token después de cambiar la contraseña.

## Demo temporal

```mermaid
sequenceDiagram
    participant W as Web pública
    participant CRM as CRM
    participant DB as MariaDB
    W->>CRM: POST demo_login
    CRM->>DB: Crea usuario temporal y datos demo
    CRM-->>W: Sesión real durante 20 minutos
    W->>CRM: Salir, cerrar o caducar
    CRM->>DB: Elimina usuario y programa limpieza de respaldo
    CRM-->>W: Retorno a WEB_APP_URL
```

## Stripe Billing de prueba

```mermaid
sequenceDiagram
    participant A as Admin CRM
    participant CRM as StripeBillingService
    participant S as Stripe Test
    participant DB as MariaDB
    A->>CRM: Invocar acción técnica de checkout para empresa y plan
    CRM->>S: Customer, Price y Checkout Session
    S-->>A: Checkout alojado
    S->>CRM: Webhook firmado
    CRM->>DB: Registra stripe_event idempotente
    CRM->>DB: Sincroniza suscripción, factura, cobro y acceso
```

La URL de éxito no confirma el pago. Solo el webhook firmado modifica el estado financiero y de acceso. El código rechaza claves que no sean `sk_test_`; Stripe Live permanece pendiente.

La interfaz visible de empresas y facturas no muestra actualmente el bloque de diagnostico, el boton de Checkout ni la cancelacion directa en Stripe. El backend y el webhook de prueba se conservan como integracion tecnica, mientras la gestion diaria visible usa el estado local de renovacion.

Las cuentas `TRIAL` sí disponen de un recorrido especifico: el banner calcula los dias restantes, `upgrade-plan` muestra solo planes pagados y `create_tenant_stripe_checkout` obtiene la empresa desde la sesion. La eleccion se guarda en campos pendientes y Stripe recoge el metodo de pago. `invoice.paid` aplica el plan dentro de la transaccion que crea el pago y la factura de plataforma.

## Modo soporte multi-tenant

```mermaid
flowchart LR
    Admin[Superadmin] --> Company[Empresa seleccionada]
    Company --> Context[Contexto de soporte con tenant_id]
    Context --> Gym[CRM del gimnasio + banner]
    Gym --> Return[Salir de soporte]
    Return --> Admin
```

El tenant objetivo se obtiene de la empresa conectada y se guarda en sesión; no se acepta desde formularios libres de usuarios de gimnasio.
