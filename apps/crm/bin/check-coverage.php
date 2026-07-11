<?php

declare(strict_types=1);

$file = $argv[1] ?? 'coverage/clover.xml';
$minimum = (float) ($argv[2] ?? 80);
if (!is_file($file)) {
    fwrite(STDERR, "No existe el informe Clover: {$file}\n");
    exit(2);
}

$xml = simplexml_load_file($file);
$metrics = $xml?->project?->metrics;
$statements = (int) ($metrics['statements'] ?? 0);
$covered = (int) ($metrics['coveredstatements'] ?? 0);
$percentage = $statements > 0 ? ($covered / $statements) * 100 : 0.0;
printf("Cobertura de sentencias: %.2f%% (mínimo %.2f%%)\n", $percentage, $minimum);
exit($percentage + 0.00001 >= $minimum ? 0 : 1);
