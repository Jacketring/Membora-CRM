<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Support.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Auth.php';
require_once dirname(__DIR__) . '/src/Repositories.php';
$_SESSION = [];
$_POST = [];
