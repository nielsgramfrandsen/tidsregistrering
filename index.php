<?php
declare(strict_types=1);
require __DIR__ . '/inc/auth.php';

if (current_user() !== null) {
    header('Location: minside.php');
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    if (!csrf_valid()) {
        $error = 'Siden er udløbet. Prøv igen.';
    } else {
        $error = attempt_login($username, (string) ($_POST['password'] ?? ''));
        if ($error === null) {
            header('Location: minside.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log ind – <?= e($config['site_name']) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body class="split">
  <main class="intro">
    <h1>Velkommen til<br><?= e($config['site_name']) ?></h1>
    <p>Log ind for at se dit indhold. Har du ikke en bruger, så kontakt administratoren.</p>
  </main>

  <section class="panel" aria-labelledby="login-title">
    <form method="post" action="index.php" class="card" autocomplete="on">
      <h2 id="login-title">Log ind</h2>

      <?php if ($error): ?>
        <p class="error" role="alert"><?= e($error) ?></p>
      <?php endif; ?>

      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <label for="username">Brugernavn</label>
      <input id="username" name="username" type="text" value="<?= e($username) ?>"
             autocomplete="username" required autofocus>

      <label for="password">Adgangskode</label>
      <input id="password" name="password" type="password"
             autocomplete="current-password" required>

      <button type="submit">Log ind</button>
    </form>
  </section>
</body>
</html>
