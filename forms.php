<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $formId = $_POST['form_id'] ?? '';

    if ($formId === '') {
        $error = 'Formulário inválido.';
    } elseif ($action === 'delete_form') {
        deleteForm($formId);
        header('Location: forms.php');
        exit;
    } else {
        try {
            $siteId = createSiteFromForm($formId);
            if ($siteId) {
                header('Location: site.php?id=' . $siteId . '&tab=pages&ok=created');
                exit;
            }
            $error = 'Formulário já convertido ou não encontrado.';
        } catch (Throwable $e) {
            $error = 'Falha ao criar o site a partir do formulário.';
        }
    }
}

$forms = fetchForms();
$user  = currentUser();

$pendingForms   = array_filter($forms, fn($f) => $f['status'] === 'novo');
$convertedForms = array_filter($forms, fn($f) => $f['status'] === 'convertido');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Formulários — Softaliza</title>
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
      <a href="dashboard.php">
        <span class="nav-icon">⊞</span> Hub de Sites
      </a>
      <a href="forms.php" class="active">
        <span class="nav-icon">📋</span> Formulários
        <?php if (count($pendingForms) > 0): ?>
          <span class="badge badge-warning" style="margin-left:auto;padding:2px 7px;font-size:11px;"><?php echo count($pendingForms); ?></span>
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

    <div class="page-header">
      <div class="page-header-left">
        <h1>Formulários</h1>
        <p>Solicitações de clientes para criação de novos sites.</p>
      </div>
      <div class="page-header-actions">
        <a href="create-site.php" class="btn btn-primary">+ Novo formulário</a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:28px;">
      <div class="stat-card">
        <div class="stat-label">Pendentes</div>
        <div class="stat-value" style="color:var(--warning);"><?php echo count($pendingForms); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Convertidos</div>
        <div class="stat-value" style="color:var(--success);"><?php echo count($convertedForms); ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total</div>
        <div class="stat-value"><?php echo count($forms); ?></div>
      </div>
    </div>

    <!-- Search -->
    <div class="flex items-center gap-3 mb-4">
      <input
        type="search"
        id="formSearch"
        placeholder="Buscar formulários..."
        class="input"
        style="max-width:280px;"
        autocomplete="off"
      />
      <span class="text-muted text-sm"><?php echo count($forms); ?> formulário<?php echo count($forms) !== 1 ? 's' : ''; ?></span>
    </div>

    <?php if (empty($forms)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <h3>Nenhum formulário ainda</h3>
        <p>Compartilhe o formulário público com seus clientes para receber solicitações.</p>
        <a href="create-site.php" class="btn btn-primary" style="margin-top:16px;">Ver formulário público</a>
      </div>
    <?php else: ?>

      <!-- Pending forms first -->
      <?php if (count($pendingForms) > 0): ?>
      <div class="section-header mb-4" style="margin-bottom:12px;">
        <span class="section-title">Aguardando conversão (<?php echo count($pendingForms); ?>)</span>
      </div>
      <div class="form-list mb-6" id="pendingList">
        <?php foreach ($pendingForms as $form): ?>
          <?php echo renderFormCard($form, true); ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Converted forms -->
      <?php if (count($convertedForms) > 0): ?>
      <div class="section-header" style="margin-bottom:12px;">
        <span class="section-title">Convertidos em site (<?php echo count($convertedForms); ?>)</span>
      </div>
      <div class="form-list" id="convertedList">
        <?php foreach ($convertedForms as $form): ?>
          <?php echo renderFormCard($form, false); ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<?php
function renderFormCard(array $form, bool $isPending): string
{
    $id        = htmlspecialchars($form['id']);
    $siteName  = htmlspecialchars($form['site_name']);
    $assocName = htmlspecialchars($form['association_name']);
    $email     = htmlspecialchars($form['contact_email'] ?? '');
    $phone     = htmlspecialchars($form['contact_phone'] ?? '');
    $domain    = htmlspecialchars($form['domain'] ?? '');
    $date      = date('d/m/Y', strtotime($form['created_at']));
    $eventDate = htmlspecialchars($form['event_date'] ?? '');
    $eventLoc  = htmlspecialchars($form['event_location'] ?? '');

    $pages = is_array($form['pages_requested'])
        ? $form['pages_requested']
        : (json_decode($form['pages_requested'] ?? '[]', true) ?: []);

    $statusBadge = $isPending
        ? '<span class="badge badge-warning">Pendente</span>'
        : '<span class="badge badge-success">Convertido</span>';

    $pagePills = '';
    foreach (array_slice($pages, 0, 5) as $pg) {
        $pagePills .= '<span class="badge badge-primary">' . htmlspecialchars($pg) . '</span>';
    }
    if (count($pages) > 5) {
        $pagePills .= '<span class="badge badge-muted">+' . (count($pages) - 5) . '</span>';
    }

    $createBtn = $isPending
        ? "<form method='post' style='display:contents;'>
            <input type='hidden' name='action' value='create_site'>
            <input type='hidden' name='form_id' value='{$id}'>
            <button class='btn btn-primary btn-sm' type='submit'>🚀 Criar site</button>
           </form>"
        : '';

    $deleteBtn = "<form method='post' style='display:contents;' onsubmit='return confirm(\"Excluir este formulário?\")'>
        <input type='hidden' name='action' value='delete_form'>
        <input type='hidden' name='form_id' value='{$id}'>
        <button class='btn btn-ghost btn-sm btn-icon' type='submit' title='Excluir'>🗑</button>
      </form>";

    $colors = '';
    if ($form['primary_color']) {
        $c = htmlspecialchars($form['primary_color']);
        $colors = "<span style='display:inline-block;width:14px;height:14px;border-radius:50%;background:{$c};border:1px solid rgba(0,0,0,.1);vertical-align:middle;margin-right:4px;'></span>{$c}";
    }

    $logoHtml = $form['logo_url']
        ? "<img src='" . htmlspecialchars($form['logo_url']) . "' style='max-height:40px;border-radius:6px;border:1px solid var(--border);' alt='Logo'>"
        : '';

    $detailRows = [
        'E-mail'      => $email ?: '—',
        'Telefone'    => $phone ?: '—',
        'Domínio'     => $domain ?: '—',
        'Data evento' => $eventDate ?: '—',
        'Local'       => $eventLoc ?: '—',
        'Template'    => htmlspecialchars($form['template'] ?? '—'),
        'Cores'       => $colors ?: '—',
    ];

    $detailGrid = '';
    foreach ($detailRows as $label => $val) {
        $detailGrid .= "<div class='detail-item'><strong>{$label}</strong><span>{$val}</span></div>";
    }

    if ($logoHtml) {
        $detailGrid .= "<div class='detail-item' style='grid-column:span 2;'><strong>Logo</strong><span>{$logoHtml}</span></div>";
    }

    if ($pagePills) {
        $detailGrid .= "<div class='detail-item' style='grid-column:span 2;'><strong>Páginas solicitadas</strong><span style='display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;'>{$pagePills}</span></div>";
    }

    return "
    <div class='form-card' data-form='" . strtolower($form['site_name'] . ' ' . $form['association_name']) . "'>
      <div class='form-card-header' onclick='this.nextElementSibling.classList.toggle(\"open\")'>
        <div style='width:36px;height:36px;border-radius:9px;background:var(--primary-lt);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;color:var(--primary);flex-shrink:0;'>
          " . mb_strtoupper(mb_substr($form['site_name'], 0, 1)) . "
        </div>
        <div class='form-card-info'>
          <div class='form-card-name'>{$siteName}</div>
          <div class='form-card-meta'>
            <span>{$assocName}</span>
            " . ($eventDate ? "<span>📅 {$eventDate}</span>" : '') . "
            " . ($eventLoc  ? "<span>📍 {$eventLoc}</span>" : '') . "
            <span>Enviado em {$date}</span>
          </div>
        </div>
        <div class='form-card-actions'>
          {$statusBadge}
          {$createBtn}
          {$deleteBtn}
          <span style='color:var(--muted);font-size:12px;'>▾</span>
        </div>
      </div>
      <div class='form-card-detail'>
        <div class='detail-grid'>{$detailGrid}</div>
      </div>
    </div>";
}
?>

<script>
const formSearch = document.getElementById('formSearch');
if (formSearch) {
  formSearch.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    document.querySelectorAll('.form-card').forEach(card => {
      card.style.display = (!q || (card.dataset.form || '').includes(q)) ? '' : 'none';
    });
  });
}
</script>
</body>
</html>
