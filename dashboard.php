<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$stats = fetchStats();
$sites = fetchSites();
$forms = fetchForms();
$user  = currentUser();

$pendingForms = array_filter($forms, fn($f) => $f['status'] === 'novo');
$pendingCount = count($pendingForms);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Hub — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <a href="dashboard.php" class="brand" style="text-decoration:none;">
      <span class="dot"></span>softaliza
    </a>

    <nav class="nav">
      <div class="nav-section">Principal</div>
      <a href="dashboard.php" class="active">
        <span class="nav-icon">⊞</span> Hub de Sites
      </a>
      <a href="forms.php">
        <span class="nav-icon">📋</span> Formulários
        <?php if ($pendingCount > 0): ?>
          <span class="badge badge-warning" style="margin-left:auto;padding:2px 7px;font-size:11px;"><?php echo $pendingCount; ?></span>
        <?php endif; ?>
      </a>
      <a href="templates.php">
        <span class="nav-icon">🎨</span> Modelos
      </a>

      <div class="nav-section" style="margin-top:12px;">Clientes</div>
      <a href="create-site.php">
        <span class="nav-icon">+</span> Novo formulário
      </a>
    </nav>

    <div class="sidebar-spacer"></div>

    <div class="sidebar-user">
      <div class="sidebar-user-info">
        <div class="sidebar-avatar"><?php echo mb_strtoupper(mb_substr($user['name'], 0, 1)); ?></div>
        <div>
          <div class="sidebar-user-name"><?php echo htmlspecialchars($user['name']); ?></div>
          <div class="sidebar-user-email"><?php echo htmlspecialchars($user['email']); ?></div>
        </div>
      </div>
      <div class="sidebar-user-actions">
        <a href="change-password.php">Senha</a>
        <a href="logout.php" class="danger">Sair</a>
      </div>
    </div>
  </aside>

  <!-- CONTENT -->
  <main class="content">

    <!-- Header -->
    <div class="page-header">
      <div class="page-header-left">
        <h1>Hub de Sites</h1>
        <p>Central de gerenciamento de todos os sites Softaliza.</p>
      </div>
      <div class="page-header-actions">
        <a href="forms.php" class="btn btn-secondary">Ver formulários</a>
        <a href="create-site.php" class="btn btn-primary">+ Novo formulário</a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-label">Sites ativos</div>
        <div class="stat-value"><?php echo $stats['ativos']; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total de sites</div>
        <div class="stat-value"><?php echo count($sites); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Organizações</div>
        <div class="stat-value"><?php echo $stats['organizacoes']; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Páginas criadas</div>
        <div class="stat-value"><?php echo $stats['eventos']; ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Formulários pendentes</div>
        <div class="stat-value" style="color:<?php echo $pendingCount > 0 ? 'var(--warning)' : 'inherit'; ?>">
          <?php echo $pendingCount; ?>
        </div>
      </div>
    </div>

    <?php if ($pendingCount > 0): ?>
    <div class="alert alert-warning mb-6">
      <span>📋</span>
      <span>
        Você tem <strong><?php echo $pendingCount; ?> formulário<?php echo $pendingCount > 1 ? 's' : ''; ?> pendente<?php echo $pendingCount > 1 ? 's' : ''; ?></strong> aguardando conversão em site.
        <a href="forms.php" style="font-weight:600;">Ver formulários →</a>
      </span>
    </div>
    <?php endif; ?>

    <!-- Sites grid -->
    <div class="section-header">
      <span class="section-title">Sites (<?php echo count($sites); ?>)</span>
      <input
        type="search"
        id="siteSearch"
        placeholder="Filtrar sites..."
        class="input"
        style="width:220px;"
        autocomplete="off"
      />
    </div>

    <?php if (empty($sites)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">🌐</div>
        <h3>Nenhum site ainda</h3>
        <p>Converta um formulário de cliente em site para começar.</p>
        <a href="forms.php" class="btn btn-primary" style="margin-top:16px;">Ver formulários</a>
      </div>
    <?php else: ?>
      <div class="sites-grid" id="sitesGrid">
        <?php foreach ($sites as $s): ?>
        <?php
          $initial = mb_strtoupper(mb_substr($s['name'], 0, 1));
          $domain  = $s['domain'] ?? null;
          $isAtivo = $s['status'] === 'ativo';
          $pages   = (int)($s['page_count'] ?? 0);
        ?>
        <a class="site-card" href="site.php?id=<?php echo urlencode($s['id']); ?>" data-name="<?php echo htmlspecialchars(strtolower($s['name'] . ' ' . ($s['owner'] ?? ''))); ?>">
          <div class="site-card-top">
            <div class="site-card-icon"><?php echo $initial; ?></div>
            <div class="site-card-name"><?php echo htmlspecialchars($s['name']); ?></div>
            <div class="site-card-domain">
              <?php if ($domain): ?>
                <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 1c-2 2-3 4-3 7s1 5 3 7M8 1c2 2 3 4 3 7s-1 5-3 7M1 8h14" stroke="currentColor" stroke-width="1.5"/></svg>
                <?php echo htmlspecialchars($domain); ?>
              <?php else: ?>
                Sem domínio configurado
              <?php endif; ?>
            </div>
          </div>
          <div class="site-card-footer">
            <div class="site-card-meta">
              <span class="site-card-meta-item">
                <?php if ($isAtivo): ?>
                  <span class="badge badge-success">Ativo</span>
                <?php else: ?>
                  <span class="badge badge-muted">Rascunho</span>
                <?php endif; ?>
              </span>
              <span class="site-card-meta-item text-muted text-xs">
                📄 <?php echo $pages; ?> pág.
              </span>
              <span class="site-card-meta-item text-muted text-xs">
                <?php echo htmlspecialchars($s['owner'] ?? ''); ?>
              </span>
            </div>
            <span class="site-card-enter">
              Abrir →
            </span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

<script>
const searchInput = document.getElementById('siteSearch');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.site-card').forEach(card => {
      const name = card.dataset.name || '';
      card.style.display = (!q || name.includes(q)) ? '' : 'none';
    });
  });
}
</script>
</body>
</html>
