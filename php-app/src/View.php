<?php

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/Views/' . $view . '.php';
}

function render_layout(string $title, string $contentView, array $data = []): void
{
    $user = Auth::requireUser();
    $flash = flash();
    extract($data, EXTR_SKIP);
    require __DIR__ . '/Views/layout.php';
}
