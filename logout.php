<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    logout();
}
header('Location: index.php');
exit;
