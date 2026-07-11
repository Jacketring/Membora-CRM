<?php
$currentVersion = '0.5';
$releaseNotes = [
    [
        'version' => '0.5',
        'date' => '08/07/2026',
        'status' => 'Version de trabajo',
        'summary' => 'Consolidacion del MVP PHP para TFM, con administración SaaS, CRM de gimnasio y mejoras de operación.',
        'changes' => [
            'Contactos unificados en Admin CRM para leads web y clientes comerciales.',
            'Cambio de tipo entre Lead y Cliente CRM sin duplicar filas.',
            'Pagos, renovaciones, facturas PDF y control de cobros SaaS.',
            'Pagos de socios, renovación de membresías y factura PDF desde el CRM de cliente.',
            'Check-ins, alertas de riesgo, auditoría interna y logs de actividad de empresas.',
            'Permisos por rol y acceso de soporte desde Admin CRM.',
            'Login demo cliente y demo administrador con sesión temporal.',
            'Navegacion reorganizada y acceso a Logs CRM desde el menu del usuario.',
            'Modo claro como apariencia predeterminada; el modo oscuro queda como opcion manual del usuario.',
        ],
    ],
    [
        'version' => '0.4',
        'date' => '03/07/2026',
        'status' => 'Iteracion interna',
        'summary' => 'Endurecimiento operativo del CRM y preparacion de entrega academica.',
        'changes' => [
            'Módulo de auditoría con datos sanitizados.',
            'Alertas reguladas para evitar generacion repetida.',
            'Tareas internas asignadas a usuarios del equipo.',
            'Documentacion de alcance, modelo de datos, pruebas y errores resueltos.',
        ],
    ],
    [
        'version' => '0.3',
        'date' => '30/06/2026',
        'status' => 'Base funcional',
        'summary' => 'Migracion del planteamiento inicial a una aplicacion PHP monolitica desplegable en Plesk.',
        'changes' => [
            'CRM de gimnasio con leads, socios, membresías, clases, reservas y usuarios.',
            'Admin CRM con empresas cliente, planes SaaS y pagos de plataforma.',
            'Web comercial estatica con formulario conectado al webhook del CRM.',
            'Eliminación de dependencia de Node.js, Next.js, NestJS, Prisma y build en producción.',
        ],
    ],
];
?>

<div class="page-heading novedades-heading">
  <div>
    <h2>Novedades</h2>
    <p>Canal interno de versiones, mejoras y cambios relevantes del CRM.</p>
  </div>
  <span class="version-pill">v<?= e($currentVersion) ?></span>
</div>

<section class="novedades-hero">
  <article>
    <span>Version actual</span>
    <strong>Membora CRM <?= e($currentVersion) ?></strong>
    <p>El producto sigue en fase de desarrollo del MVP. La version 0.5 recoge las funciones principales, pero todavía no se considera una version final de producción.</p>
  </article>
  <article>
    <span>Actualizaciones</span>
    <strong>Canal de producto</strong>
    <p>Las mejoras se agrupan por version para que el equipo pueda revisar rapidamente que ha cambiado y que queda pendiente.</p>
  </article>
</section>

<section class="release-timeline" aria-label="Historial de versiones">
  <?php foreach ($releaseNotes as $release): ?>
    <article class="release-card">
      <header>
        <div>
          <span><?= e($release['date']) ?></span>
          <h3>Version <?= e($release['version']) ?></h3>
        </div>
        <strong><?= e($release['status']) ?></strong>
      </header>
      <p><?= e($release['summary']) ?></p>
      <ul>
        <?php foreach ($release['changes'] as $change): ?>
          <li><?= e($change) ?></li>
        <?php endforeach; ?>
      </ul>
    </article>
  <?php endforeach; ?>
</section>
