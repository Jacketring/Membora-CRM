# Auditoria web, SEO y accesibilidad - 14 de julio de 2026

## Cambios aplicados

- Posicionamiento principal cambiado de `ERP para gimnasios` a `software para gimnasios` y `software para gimnasios`.
- Title, meta description, canonical, Open Graph y Twitter Card en las cinco paginas publicas.
- JSON-LD `Organization`, `SoftwareApplication` y `FAQPage` en la home.
- `robots.txt` y `sitemap.xml` publicos; `/app/` queda fuera del rastreo.
- Imagen social propia en `httpdocs/assets/og-image.png`.
- Un solo `h1` por pagina y jerarquia de contenidos revisada.
- Textos de plantilla, mockup y resenas no verificadas eliminados.
- Ortografia espanola y campo `name="telefono"` corregidos.
- Planes con prestaciones, limites, CTA, IVA y referencia a modalidad anual.
- Separacion clara entre `Entrar`, demo temporal y prueba gratuita.
- Skip link, foco visible, estados `aria-live`, teclado/Escape, areas tactiles y `prefers-reduced-motion`.
- Mayor ancho de lectura controlado, espaciado consistente y responsive de formularios y planes.

## Alta self-service

La prueba de 14 dias exige confirmacion de email. El enlace caduca en una hora y solo puede usarse una vez. Tras confirmar, se crean el `Cliente`, la empresa, el tenant `TRIAL` y su administrador. Un segundo enlace permite revelar durante una hora la contrasena inicial cifrada y la consume en la primera visualizacion. Hay validacion de origen y honeypot; el rate limit especifico por IP y email queda configurable y desactivado por defecto durante la depuracion final.

## Validacion automatica

- Sintaxis PHP y JavaScript.
- PHPUnit y PHPStan.
- Parseo HTML y comprobacion de un unico `h1`, title, description y canonical por pagina.

## Validacion manual pendiente en produccion

No se inventa una puntuacion Lighthouse. Tras desplegar en Plesk se debe ejecutar Lighthouse y axe DevTools sobre la URL publica, guardar las capturas/resultados y verificar especialmente contraste, orden de foco, menu movil, formulario de prueba, Core Web Vitals y metadatos sociales.
