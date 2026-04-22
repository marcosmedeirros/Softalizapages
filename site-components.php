<?php
require __DIR__ . "/data/db.php";

$siteId = $_GET["site_id"] ?? "";
$part = $_GET["part"] ?? "header";
$part = $part === "footer" ? "footer" : "header";

$site = $siteId ? fetchSite($siteId) : null;
if (!$site) {
  header("Location: sites.php");
  exit;
}

ensureSiteSharedDefaults($siteId);
$dir = siteSharedDir($siteId);
$path = $dir . "/" . $part . ".html";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $content = $_POST["content"] ?? "";
  if (file_put_contents($path, $content) === false) {
    $error = "Falha ao salvar o componente.";
  } else {
    $success = "Componente salvo com sucesso.";
  }
}

$contentValue = file_get_contents($path);
if ($contentValue === false) {
  $contentValue = "";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Editar <?php echo $part; ?> - Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="brand"><span class="dot"></span>softaliza</div>
      <nav class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="forms.php">Formularios</a>
        <a href="sites.php">Sites</a>
        <a href="templates.php">Modelos</a>
        <a href="create-site.php">Novo formulario</a>
      </nav>
    </aside>

    <main class="content">
      <div class="header">
        <div>
          <h1>Editar <?php echo $part; ?></h1>
          <p><?php echo htmlspecialchars($site["name"]); ?></p>
        </div>
        <a class="btn" href="site.php?id=<?php echo $site["id"]; ?>">Voltar</a>
      </div>

      <section class="card">
        <form method="post">
          <label>
            HTML do <?php echo $part; ?>
            <textarea class="search" name="content" rows="14" style="font-family: Consolas, 'Courier New', monospace;"><?php echo htmlspecialchars($contentValue); ?></textarea>
          </label>

          <div style="margin-top: 12px;">
            <button class="btn" type="submit">Salvar</button>
          </div>

          <?php if ($error): ?>
            <div class="footer-hint"><?php echo htmlspecialchars($error); ?></div>
          <?php elseif ($success): ?>
            <div class="footer-hint"><?php echo htmlspecialchars($success); ?></div>
          <?php else: ?>
            <div class="footer-hint">Esse componente e usado apenas neste site.</div>
          <?php endif; ?>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
