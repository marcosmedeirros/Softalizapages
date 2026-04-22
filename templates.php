<?php
require_once __DIR__ . "/auth.php";
requireLogin();

$error   = "";
$success = "";

// Gerencia ação de marcar/desmarcar site como inspiração disponível
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "toggle_inspiration") {
        $siteId  = trim($_POST["site_id"]  ?? "");
        $enabled = (int) ($_POST["enabled"] ?? 0);
        if ($siteId !== "") {
            try {
                $stmt = db()->prepare("update sites set is_inspiration = :v where id = :id");
                $stmt->execute(["v" => $enabled, "id" => $siteId]);
                $success = $enabled ? "Site marcado como modelo de inspiração." : "Site removido dos modelos de inspiração.";
            } catch (Throwable $e) {
                // Coluna ainda não existe — migração pendente
                $error = "Execute a migration_v2.sql para habilitar esta funcionalidade.";
            }
        }
    }
}

// Busca sites disponíveis e indica quais são inspirações
try {
    $sitesAll = db()->query(
        "select s.id, s.name, s.status,
                COALESCE(s.is_inspiration, 0) as is_inspiration,
                a.name as owner
         from sites s
         join associations a on a.id = s.association_id
         order by s.is_inspiration desc, s.name"
    )->fetchAll();
} catch (Throwable $e) {
    // Fallback sem a coluna is_inspiration
    $sitesAll = fetchSites();
    foreach ($sitesAll as &$s) { $s["is_inspiration"] = 0; }
    unset($s);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Modelos — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
  <style>
    .inspiration-toggle {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .switch {
      position: relative;
      display: inline-block;
      width: 36px;
      height: 20px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
      position: absolute;
      inset: 0;
      background: var(--border);
      border-radius: 99px;
      cursor: pointer;
      transition: background .2s;
    }
    .slider:before {
      content: "";
      position: absolute;
      width: 14px;
      height: 14px;
      left: 3px;
      top: 3px;
      background: #fff;
      border-radius: 50%;
      transition: transform .2s;
    }
    .switch input:checked + .slider { background: var(--primary); }
    .switch input:checked + .slider:before { transform: translateX(16px); }

    .site-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
      gap: 12px;
    }
    .site-row:last-child { border-bottom: 0; }
    .insp-badge {
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 999px;
      background: #dcfce7;
      color: #166534;
      font-weight: 600;
    }
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <a href="dashboard.php" class="brand" style="text-decoration:none;"><span class="dot"></span>softaliza</a>
    <nav class="nav">
      <div class="nav-section">Principal</div>
      <a href="dashboard.php"><span class="nav-icon">⊞</span> Hub de Sites</a>
      <a href="forms.php"><span class="nav-icon">📋</span> Formulários</a>
      <a href="templates.php" class="active"><span class="nav-icon">🎨</span> Modelos</a>
      <div class="nav-section" style="margin-top:12px;">Clientes</div>
      <a href="create-site.php"><span class="nav-icon">+</span> Novo formulário</a>
    </nav>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">
      <div class="sidebar-user-actions">
        <a href="change-password.php">Senha</a>
        <a href="logout.php" class="danger">Sair</a>
      </div>
    </div>
  </aside>

  <main class="content">
    <div class="page-header">
      <div class="page-header-left">
        <h1>Modelos de inspiração</h1>
        <p>Defina quais sites aparecem como referência visual no formulário do cliente.</p>
      </div>
      <div class="page-header-actions">
        <a class="btn btn-primary" href="create-site.php">+ Novo formulário</a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success mb-4"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
      <p style="margin:0 0 16px;font-size:14px;color:var(--muted);">
        Ative os sites que devem aparecer como opção de inspiração no formulário do cliente.
        O cliente poderá escolher um deles como referência visual.
      </p>

      <?php if (!$sitesAll): ?>
        <p style="color:var(--muted);font-size:14px;">Nenhum site criado ainda.</p>
      <?php else: ?>
        <?php foreach ($sitesAll as $s): ?>
        <div class="site-row">
          <div>
            <div style="font-weight:600;font-size:14px;">
              <?php echo htmlspecialchars($s["name"]); ?>
              <?php if ($s["is_inspiration"]): ?><span class="insp-badge" style="margin-left:8px;">✓ Ativo como inspiração</span><?php endif; ?>
            </div>
            <div class="meta"><?php echo htmlspecialchars($s["owner"] ?? ""); ?> &bull; <?php echo htmlspecialchars($s["status"]); ?></div>
          </div>
          <form method="post" class="inspiration-toggle">
            <input type="hidden" name="action"  value="toggle_inspiration" />
            <input type="hidden" name="site_id" value="<?php echo htmlspecialchars($s["id"]); ?>" />
            <input type="hidden" name="enabled" value="<?php echo $s["is_inspiration"] ? 0 : 1; ?>" />
            <label class="switch" title="<?php echo $s["is_inspiration"] ? 'Desativar' : 'Ativar como inspiração'; ?>">
              <input type="checkbox" <?php echo $s["is_inspiration"] ? "checked" : ""; ?> onchange="this.closest('form').submit()">
              <span class="slider"></span>
            </label>
            <span style="font-size:12px;color:var(--muted);"><?php echo $s["is_inspiration"] ? "Visível" : "Oculto"; ?></span>
          </form>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <p style="margin:16px 0 0;font-size:12px;color:var(--muted);">
        💡 Para que esta funcionalidade seja salva no banco, certifique-se de ter executado o
        <strong>migration_v2.sql</strong> e que a coluna <code>is_inspiration</code> exista na tabela <code>sites</code>.
      </p>
    </div>

  </main>
</div>

</body>
</html>
