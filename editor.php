<?php
require_once __DIR__ . "/auth.php";
requireLogin();

$pageId = $_GET["id"] ?? "";
$page = $pageId ? fetchSitePage($pageId) : null;

if (!$page) {
  header("Location: dashboard.php");
  exit;
}

$site = fetchSite($page["site_id"]);
$siteName = $site ? $site["name"] : "Site";

$dir = ensureSiteFolder($page["site_id"]);
$fileName = basename($page["file"]);
$path = $dir . "/" . $fileName;

if (!file_exists($path)) {
  $default = buildPageHtmlForSite(
    $page["site_id"],
    $page["title"],
    "<h1>" . htmlspecialchars($page["title"], ENT_QUOTES) . "</h1>"
  );
  file_put_contents($path, $default);
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $content = $_POST["content"] ?? "";
  $newTitle = trim($_POST["title"] ?? "");
  $status = $_POST["status"] ?? $page["status"];
  $status = $status === "publicado" ? "publicado" : "rascunho";

  if ($newTitle === "") {
    $error = "Informe o titulo da pagina.";
  } else {
    if ($newTitle !== $page["title"]) {
      $updated = renamePage($page["id"], $newTitle);
      if ($updated) {
        $page = $updated;
        $fileName = basename($page["file"]);
        $path = $dir . "/" . $fileName;
      }
    }

    if (file_put_contents($path, $content) === false) {
      $error = "Falha ao salvar o HTML.";
    } else {
      updatePageStatus($page["id"], $status);
      $page["status"] = $status;
      $success = "Pagina salva com sucesso.";
    }
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
  <title>Editar pagina - Softaliza</title>
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body>
  <div class="site-hub">
    <!-- Editor sidebar -->
    <aside class="site-sidebar">
      <a href="site.php?id=<?php echo $page['site_id']; ?>&tab=pages" class="site-sidebar-back">
        ← Voltar às páginas
      </a>
      <div class="site-sidebar-info">
        <div class="site-sidebar-icon"><?php echo mb_strtoupper(mb_substr($siteName, 0, 1)); ?></div>
        <div class="site-sidebar-name"><?php echo htmlspecialchars($siteName); ?></div>
        <div class="site-sidebar-domain" style="margin-top:6px;">Editando: <?php echo htmlspecialchars($page['title']); ?></div>
      </div>
      <div style="padding:0 4px;margin-top:12px;">
        <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Arquivo</div>
        <div style="font-family:monospace;font-size:12px;color:var(--text);background:var(--bg);padding:8px 10px;border-radius:8px;border:1px solid var(--border);"><?php echo htmlspecialchars($page['file']); ?></div>
      </div>
    </aside>

    <main class="site-content">
      <div class="site-header-bar">
        <div class="site-header-left">
          <h1>Editar página</h1>
          <span class="badge badge-muted" style="margin-left:8px;"><?php echo htmlspecialchars($page['title']); ?></span>
        </div>
        <div class="site-header-actions">
          <a href="sites/<?php echo $page['site_id']; ?>/<?php echo $page['file']; ?>" target="_blank" class="btn btn-secondary btn-sm">Preview ↗</a>
        </div>
      </div>

      <div class="card">
        <form method="post">
          <div class="card-body" style="display:grid;gap:14px;">
            <div class="field">
              <label>Título da página</label>
              <input class="input" type="text" name="title" value="<?php echo htmlspecialchars($page['title']); ?>" />
            </div>
            <div class="field">
              <label>HTML da página</label>
              <textarea class="input" name="content" rows="24" style="font-family:'Courier New',monospace;font-size:13px;line-height:1.5;"><?php echo htmlspecialchars($contentValue); ?></textarea>
            </div>
          </div>
          <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;">
            <button class="btn btn-primary btn-sm" type="submit" name="status" value="publicado">🚀 Publicar</button>
            <button class="btn btn-secondary btn-sm" type="submit" name="status" value="rascunho">💾 Salvar rascunho</button>
          </div>

          <?php if ($error): ?>
            <div class="footer-hint"><?php echo htmlspecialchars($error); ?></div>
          <?php elseif ($success): ?>
            <div class="footer-hint"><?php echo htmlspecialchars($success); ?></div>
          <?php else: ?>
            <div class="footer-hint">Edite o HTML e publique quando estiver pronto.</div>
          <?php endif; ?>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
