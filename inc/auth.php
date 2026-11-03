<?php
declare(strict_types=1);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$config = require __DIR__ . '/config.php';

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

function current_user(): ?string
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        header('Location: index.php');
        exit;
    }
}

/** Returnerer null ved succes, ellers en fejlbesked. */
function attempt_login(string $username, string $password): ?string
{
    global $config;

    // Enkel bremse: maks. 5 forkerte forsøg pr. 10 minutter pr. session
    $now = time();
    $_SESSION['fails'] = array_filter(
        $_SESSION['fails'] ?? [],
        fn($t) => $t > $now - 600
    );
    if (count($_SESSION['fails']) >= 5) {
        return 'For mange forsøg. Vent et par minutter, og prøv igen.';
    }

    $hash = $config['users'][$username] ?? '';
    // Kør altid password_verify, så svartiden ikke afslører om brugeren findes
    $dummy = '$2y$10$usesomesillystringforsaleuQwMZ5nq6s0Q7yQ0o8k1pQe2v3wX4y';
    $ok = password_verify($password, $hash !== '' ? $hash : $dummy) && $hash !== '';

    if (!$ok) {
        $_SESSION['fails'][] = $now;
        usleep(400000);
        return 'Forkert brugernavn eller adgangskode.';
    }

    session_regenerate_id(true);
    $_SESSION['user'] = $username;
    $_SESSION['fails'] = [];
    return null;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
