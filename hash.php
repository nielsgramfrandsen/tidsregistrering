<?php
// Hjælpeside til at lave en adgangskode-hash. SLET DENNE FIL, når du er færdig.
$hash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pw'] ?? '') !== '') {
    $hash = password_hash((string) $_POST['pw'], PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lav hash</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="content">
    <h1>Lav adgangskode-hash</h1>
    <form method="post" class="card" style="max-width:28rem">
      <label for="pw">Ny adgangskode</label>
      <input id="pw" name="pw" type="text" required>
      <button type="submit">Lav hash</button>
    </form>
    <?php if ($hash): ?>
      <p>Kopiér hele linjen herunder ind i <code>inc/config.php</code>, og slet så denne fil.</p>
      <p><code style="word-break:break-all"><?= htmlspecialchars($hash) ?></code></p>
    <?php endif; ?>
  </main>
</body>
</html>
