<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vercel.php';
requireLogin();

$id   = $_GET['id'] ?? '';
$tab  = $_GET['tab'] ?? 'pages';
$site = $id ? fetchSite($id) : null;

if (!$site) { header('Location: dashboard.php'); exit; }

$error   = '';
$success = '';

// ── POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Pages
    if ($action === 'create_page') {
        $title  = trim($_POST['page_title'] ?? '');
        $status = ($_POST['page_status'] ?? '') === 'publicado' ? 'publicado' : 'rascunho';
        if ($title === '') {
            $error = 'Informe o título da página.'; $tab = 'pages';
        } else {
            createPageForSite($site['id'], $title, $status);
            header("Location: site.php?id={$id}&tab=pages&ok=page_created"); exit;
        }
    }
    if ($action === 'duplicate_page') {
        $pageId = $_POST['page_id'] ?? '';
        if ($pageId) { duplicatePage($pageId); }
        header("Location: site.php?id={$id}&tab=pages"); exit;
    }
    if ($action === 'delete_page') {
        $pageId = $_POST['page_id'] ?? '';
        if ($pageId) { deletePage($pageId); }
        header("Location: site.php?id={$id}&tab=pages"); exit;
    }
    if ($action === 'toggle_page_status') {
        $pageId    = $_POST['page_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? 'rascunho';
        if ($pageId) { updatePageStatus($pageId, $newStatus); }
        header("Location: site.php?id={$id}&tab=pages"); exit;
    }

    // Settings
    if ($action === 'save_settings') {
        $name = trim($_POST['site_name'] ?? '');
        if ($name !== '') updateSiteName($site['id'], $name);

        $status = ($_POST['site_status'] ?? '') === 'ativo' ? 'ativo' : 'rascunho';
        updateSiteStatus($site['id'], $status);

        header("Location: site.php?id={$id}&tab=settings&ok=saved"); exit;
    }

    // Domain
    if ($action === 'save_domain') {
        $domain = trim($_POST['domain'] ?? '');
        $oldDomain = $site['domain'] ?? '';

        $vercel = new VercelDomains();

        // Remove domínio antigo da Vercel se mudou
        if ($oldDomain && $oldDomain !== $domain && $vercel->isConfigured()) {
            $vercel->removeDomain($oldDomain);
        }

        // Salva no banco
        updateSiteDomain($site['id'], $domain);

        // Adiciona novo domínio na Vercel
        if ($domain && $vercel->isConfigured()) {
            $res = $vercel->addDomain($domain);
            if (!$res['ok']) {
                $error = 'Domínio salvo no banco, mas a Vercel retornou erro: ' . $res['error'];
            }
        }

        if (!$error) {
            header("Location: site.php?id={$id}&tab=domain&ok=saved"); exit;
        }
        $tab = 'domain';
    }

    if ($action === 'verify_domain') {
        $vercel = new VercelDomains();
        $domain = $site['domain'] ?? '';
        if ($domain && $vercel->isConfigured()) {
            $res = $vercel->verifyDomain($domain);
            $success = $res['verified'] ? 'Domínio verificado com sucesso!' : 'Verificação ainda pendente. Configure o DNS e tente novamente.';
        } else {
            $success = 'Vercel não configurado. Verifique o config.php.';
        }
        $tab = 'domain';
    }

    if ($action === 'delete_site') {
        deleteSite($site['id']);
        header('Location: dashboard.php'); exit;
    }
}

// Flash messages
if (($_GET['ok'] ?? '') === 'saved')        $success = 'Alterações salvas com sucesso!';
if (($_GET['ok'] ?? '') === 'page_created') $success = 'Página criada com sucesso!';

$pages  = fetchSitePages($site['id']);
$vercel = new VercelDomains();
$domainStatus = null;
if ($site['domain'] && $vercel->isConfigured()) {
    $domainStatus = $vercel->getDomainStatus($site['domain']);
}
$siteInitial = mb_strtoupper(mb_substr($site['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($site['name']); ?> — Softaliza</title>
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body>
<div class="site-hub">

  <!-- SITE SIDEBAR -->
  <aside class="site-sidebar">
    <a href="dashboard.php" class="site-sidebar-back">
      ← Voltar ao Hub
    </a>

    <div class="site-sidebar-info">
      <div class="site-sidebar-icon"><?php echo $siteInitial; ?></div>
      <div class="site-sidebar-name"><?php echo htmlspecialchars($site['name']); ?></div>
      <?php if ($site['domain']): ?>
        <div class="site-sidebar-domain">
          <a href="https://<?php echo htmlspecialchars($site['domain']); ?>" target="_blank" rel="noopener">
            <?php echo htmlspecialchars($site['domain']); ?> ↗
          </a>
        </div>
      <?php else: ?>
        <div class="site-sidebar-domain" style="color:var(--muted-2);">Sem domínio</div>
      <?php endif; ?>
      <div style="margin-top:10px;">
        <?php if ($site['status'] === 'ativo'): ?>
          <span class="badge badge-success">Ativo</span>
        <?php else: ?>
          <span class="badge badge-muted">Rascunho</span>
        <?php endif; ?>
      </div>
    </div>

    <nav class="site-nav">
      <a href="?id=<?php echo $id; ?>&tab=pages" class="<?php echo $tab==='pages'?'active':''; ?>">
        <span class="nav-icon">📄</span> Páginas
        <span style="margin-left:auto;font-size:12px;color:var(--muted-2);"><?php echo count($pages); ?></span>
      </a>
      <a href="?id=<?php echo $id; ?>&tab=settings" class="<?php echo $tab==='settings'?'active':''; ?>">
        <span class="nav-icon">⚙️</span> Configurações
      </a>
      <a href="?id=<?php echo $id; ?>&tab=domain" class="<?php echo $tab==='domain'?'active':''; ?>">
        <span class="nav-icon">🌐</span> Domínio
        <?php if (!$site['domain']): ?>
          <span class="badge badge-warning" style="margin-left:auto;font-size:10px;padding:2px 6px;">!</span>
        <?php endif; ?>
      </a>
      <a href="?id=<?php echo $id; ?>&tab=info" class="<?php echo $tab==='info'?'active':''; ?>">
        <span class="nav-icon">ℹ️</span> Informações
      </a>
    </nav>

    <div class="sidebar-spacer"></div>

    <div style="padding:10px 0 4px;">
      <button
        type="button"
        onclick="document.getElementById('deleteModal').setAttribute('aria-hidden','false')"
        style="width:100%;padding:8px 10px;border-radius:var(--radius-sm);border:none;background:transparent;color:#ef4444;font-size:13px;font-weight:600;cursor:pointer;text-align:left;display:flex;align-items:center;gap:6px;"
      >
        🗑 Excluir site
      </button>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <div class="site-content">

    <!-- Header bar -->
    <div class="site-header-bar">
      <div class="site-header-left">
        <h1><?php echo htmlspecialchars($site['name']); ?></h1>
      </div>
      <div class="site-header-actions">
        <?php if ($site['domain']): ?>
          <a href="https://<?php echo htmlspecialchars($site['domain']); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
            Visitar site ↗
          </a>
        <?php endif; ?>
        <?php if (count($pages) > 0): ?>
          <a href="sites/<?php echo $id; ?>/index.html" target="_blank" class="btn btn-secondary btn-sm">
            Preview ↗
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success mb-4"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         TAB: PÁGINAS
    ══════════════════════════════════════════════════════════ -->
    <?php if ($tab === 'pages'): ?>

      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 style="font-size:17px;font-weight:700;">Páginas</h2>
          <p class="text-muted text-sm mt-1"><?php echo count($pages); ?> página<?php echo count($pages) !== 1 ? 's' : ''; ?> neste site</p>
        </div>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('newPageForm').classList.toggle('hidden')">
          + Nova página
        </button>
      </div>

      <!-- New page form (hidden by default) -->
      <div id="newPageForm" class="card mb-4 hidden">
        <div class="card-header"><h3>Nova página</h3></div>
        <form method="post" class="card-body">
          <input type="hidden" name="action" value="create_page">
          <div class="settings-form settings-form-2">
            <div class="field">
              <label>Título da página</label>
              <input class="input" type="text" name="page_title" placeholder="Ex.: Programação" required autofocus />
            </div>
            <div class="field">
              <label>Status inicial</label>
              <select class="input" name="page_status">
                <option value="rascunho">Rascunho</option>
                <option value="publicado">Publicado</option>
              </select>
            </div>
          </div>
          <div style="margin-top:14px;display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary btn-sm">Criar página</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('newPageForm').classList.add('hidden')">Cancelar</button>
          </div>
        </form>
      </div>

      <?php if (empty($pages)): ?>
        <div class="empty-state" style="padding:48px 20px;">
          <div class="empty-state-icon">📄</div>
          <h3>Sem páginas ainda</h3>
          <p>Crie a primeira página para este site.</p>
        </div>
      <?php else: ?>
        <div class="pages-list">
          <?php foreach ($pages as $page): ?>
          <?php $isPublished = $page['status'] === 'publicado'; ?>
          <div class="page-item">
            <div class="page-item-icon"><?php echo $isPublished ? '🌐' : '📝'; ?></div>
            <div class="page-item-info">
              <div class="page-item-title"><?php echo htmlspecialchars($page['title']); ?></div>
              <div class="page-item-file"><?php echo htmlspecialchars($page['file']); ?></div>
            </div>
            <?php if ($isPublished): ?>
              <span class="badge badge-success">Publicado</span>
            <?php else: ?>
              <span class="badge badge-muted">Rascunho</span>
            <?php endif; ?>
            <div class="page-item-actions">
              <a href="editor.php?id=<?php echo $page['id']; ?>" class="btn btn-secondary btn-sm btn-icon" title="Editar">✏️</a>
              <a href="sites/<?php echo $id; ?>/<?php echo $page['file']; ?>" target="_blank" class="btn btn-secondary btn-sm btn-icon" title="Visualizar">👁</a>
              <form method="post" style="display:contents;">
                <input type="hidden" name="action" value="toggle_page_status">
                <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">
                <input type="hidden" name="new_status" value="<?php echo $isPublished ? 'rascunho' : 'publicado'; ?>">
                <button class="btn btn-secondary btn-sm btn-icon" type="submit" title="<?php echo $isPublished ? 'Despublicar' : 'Publicar'; ?>">
                  <?php echo $isPublished ? '🔒' : '🚀'; ?>
                </button>
              </form>
              <form method="post" style="display:contents;">
                <input type="hidden" name="action" value="duplicate_page">
                <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">
                <button class="btn btn-secondary btn-sm btn-icon" type="submit" title="Duplicar">📋</button>
              </form>
              <button class="btn btn-ghost btn-sm btn-icon" type="button" title="Excluir página"
                onclick="confirmDeletePage('<?php echo $page['id']; ?>','<?php echo htmlspecialchars(addslashes($page['title'])); ?>')">
                🗑
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════
         TAB: CONFIGURAÇÕES
    ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'settings'): ?>

      <h2 style="font-size:17px;font-weight:700;margin-bottom:20px;">Configurações do site</h2>

      <div class="settings-grid">
        <!-- General -->
        <div class="settings-section">
          <div class="settings-section-header">
            <h3>Informações gerais</h3>
            <p>Nome e status de publicação do site.</p>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="save_settings">
            <div class="settings-section-body">
              <div class="settings-form">
                <div class="field">
                  <label>Nome do site</label>
                  <input class="input" type="text" name="site_name" value="<?php echo htmlspecialchars($site['name']); ?>" required />
                </div>
                <div class="field">
                  <label>Status</label>
                  <select class="input" name="site_status">
                    <option value="rascunho" <?php echo $site['status'] !== 'ativo' ? 'selected' : ''; ?>>Rascunho — não acessível publicamente</option>
                    <option value="ativo"    <?php echo $site['status'] === 'ativo' ? 'selected' : ''; ?>>Ativo — acessível pelo domínio</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="settings-footer">
              <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
            </div>
          </form>
        </div>

        <!-- Info only -->
        <div class="settings-section">
          <div class="settings-section-header">
            <h3>Detalhes do cliente</h3>
            <p>Informações registradas no formulário original.</p>
          </div>
          <div class="settings-section-body">
            <table style="width:100%;font-size:13.5px;border-collapse:collapse;">
              <?php
              $rows = [
                'Organização' => $site['owner']          ?? '—',
                'E-mail'      => $site['contact_email']  ?? '—',
                'Template'    => $site['template']       ?? '—',
                'Plano'       => $site['plan']           ?? '—',
                'Criado em'   => $site['created_at']     ?? '—',
                'Obs.'        => $site['notes']          ?? '—',
              ];
              foreach ($rows as $label => $value): ?>
              <tr>
                <td style="padding:8px 0;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;padding-right:16px;"><?php echo $label; ?></td>
                <td style="padding:8px 0;"><?php echo htmlspecialchars($value); ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </div>

    <!-- ══════════════════════════════════════════════════════
         TAB: DOMÍNIO
    ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'domain'): ?>

      <h2 style="font-size:17px;font-weight:700;margin-bottom:6px;">Domínio personalizado</h2>
      <p class="text-muted text-sm mb-6">Cada site pode ter seu próprio domínio via Vercel Platforms.</p>

      <!-- Current domain status -->
      <?php if ($site['domain']): ?>
      <div class="domain-status-card mb-6">
        <div class="domain-status-row">
          <div>
            <div class="domain-name">
              🌐 <?php echo htmlspecialchars($site['domain']); ?>
              <?php if ($domainStatus && $domainStatus['ok'] && $domainStatus['verified']): ?>
                <span class="badge badge-success" style="font-size:11px;">Verificado</span>
              <?php elseif ($domainStatus && $domainStatus['ok']): ?>
                <span class="badge badge-warning" style="font-size:11px;">Aguardando DNS</span>
              <?php elseif (!$vercel->isConfigured()): ?>
                <span class="badge badge-muted" style="font-size:11px;">Vercel não configurado</span>
              <?php else: ?>
                <span class="badge badge-muted" style="font-size:11px;">Não verificado</span>
              <?php endif; ?>
            </div>
            <div class="text-muted text-xs mt-1">Domínio configurado para este site</div>
          </div>
          <div style="display:flex;gap:8px;">
            <a href="https://<?php echo htmlspecialchars($site['domain']); ?>" target="_blank" class="btn btn-secondary btn-sm">Abrir ↗</a>
            <?php if ($vercel->isConfigured()): ?>
            <form method="post" style="display:contents;">
              <input type="hidden" name="action" value="verify_domain">
              <button class="btn btn-secondary btn-sm" type="submit">Verificar DNS</button>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($domainStatus && $domainStatus['ok'] && !$domainStatus['verified']): ?>
        <div class="domain-dns">
          <p style="margin:0 0 12px;font-size:13px;font-weight:600;">Configure o DNS do seu domínio:</p>
          <table class="dns-table">
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Nome</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>CNAME</td>
                <td><?php echo htmlspecialchars($site['domain']); ?></td>
                <td><code><?php echo htmlspecialchars($domainStatus['cname'] ?? 'cname.vercel-dns.com'); ?></code></td>
              </tr>
            </tbody>
          </table>
          <p style="margin:12px 0 0;font-size:12px;color:var(--muted);">
            Após configurar o DNS no seu registrador de domínio, clique em "Verificar DNS" para confirmar.
            A propagação pode levar até 48 horas.
          </p>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Edit domain form -->
      <div class="settings-section">
        <div class="settings-section-header">
          <h3><?php echo $site['domain'] ? 'Alterar domínio' : 'Adicionar domínio'; ?></h3>
          <p>O domínio será registrado no projeto Vercel automaticamente via API.</p>
        </div>
        <form method="post">
          <input type="hidden" name="action" value="save_domain">
          <div class="settings-section-body">
            <div class="field">
              <label>Domínio</label>
              <input class="input" type="text" name="domain"
                value="<?php echo htmlspecialchars($site['domain'] ?? ''); ?>"
                placeholder="exemplo.com.br" />
              <span class="field-hint">Sem http:// ou www. Ex.: congresso2025.org.br</span>
            </div>
          </div>
          <div class="settings-footer">
            <button type="submit" class="btn btn-primary btn-sm">
              <?php echo $site['domain'] ? 'Atualizar domínio' : 'Adicionar domínio'; ?>
            </button>
          </div>
        </form>
      </div>

      <?php if (!$vercel->isConfigured()): ?>
      <div class="alert alert-info mt-4">
        <span>ℹ️</span>
        <span>
          A integração com a Vercel não está configurada. Defina as variáveis
          <code style="background:#c7d2fe20;padding:1px 5px;border-radius:4px;">VERCEL_TOKEN</code> e
          <code style="background:#c7d2fe20;padding:1px 5px;border-radius:4px;">VERCEL_PROJECT_ID</code>
          no seu ambiente ou em <code>config.php</code>.
          <a href="https://vercel.com/account/tokens" target="_blank" rel="noopener">Gerar token →</a>
        </span>
      </div>
      <?php endif; ?>

      <div class="card mt-4">
        <div class="card-header"><h3>Como funciona</h3></div>
        <div class="card-body" style="font-size:13.5px;color:var(--muted);line-height:1.7;">
          <ol style="margin:0;padding-left:18px;display:grid;gap:8px;">
            <li>Informe o domínio desejado (sem www ou http://)</li>
            <li>A Softaliza registra o domínio via <strong>Vercel Domains API</strong></li>
            <li>Configure um registro <strong>CNAME</strong> no seu registrador de domínio</li>
            <li>Clique em "Verificar DNS" após a propagação (pode levar até 48h)</li>
            <li>O SSL é provisionado automaticamente pela Vercel</li>
          </ol>
        </div>
      </div>

    <!-- ══════════════════════════════════════════════════════
         TAB: INFO
    ══════════════════════════════════════════════════════════ -->
    <?php elseif ($tab === 'info'): ?>

      <h2 style="font-size:17px;font-weight:700;margin-bottom:20px;">Visão geral</h2>

      <div class="stats-row" style="margin-bottom:20px;">
        <div class="stat-card">
          <div class="stat-label">Páginas</div>
          <div class="stat-value"><?php echo count($pages); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Publicadas</div>
          <div class="stat-value"><?php echo count(array_filter($pages, fn($p) => $p['status'] === 'publicado')); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Rascunhos</div>
          <div class="stat-value"><?php echo count(array_filter($pages, fn($p) => $p['status'] !== 'publicado')); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Domínio</div>
          <div class="stat-value" style="font-size:16px;"><?php echo $site['domain'] ? '✓' : '—'; ?></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>Lista de páginas</h3></div>
        <?php if (empty($pages)): ?>
          <div class="card-body text-muted">Nenhuma página criada ainda.</div>
        <?php else: ?>
        <table class="table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Arquivo</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pages as $page): ?>
            <tr>
              <td class="font-bold"><?php echo htmlspecialchars($page['title']); ?></td>
              <td style="font-family:monospace;font-size:12.5px;color:var(--muted);"><?php echo htmlspecialchars($page['file']); ?></td>
              <td>
                <?php if ($page['status'] === 'publicado'): ?>
                  <span class="badge badge-success">Publicado</span>
                <?php else: ?>
                  <span class="badge badge-muted">Rascunho</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="editor.php?id=<?php echo $page['id']; ?>" class="btn btn-secondary btn-sm">Editar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    <?php endif; ?>
  </div><!-- /site-content -->
</div><!-- /site-hub -->

<!-- Modal: delete page -->
<div class="modal-backdrop" id="deletePageModal" aria-hidden="true">
  <div class="modal-box">
    <div class="modal-header"><h3>Excluir página</h3></div>
    <div class="modal-body">Tem certeza que deseja excluir a página <strong id="deletePageName"></strong>? Esta ação não pode ser desfeita.</div>
    <form method="post" class="modal-footer">
      <input type="hidden" name="action" value="delete_page">
      <input type="hidden" name="page_id" id="deletePageId">
      <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('deletePageModal').setAttribute('aria-hidden','true')">Cancelar</button>
    </form>
  </div>
</div>

<!-- Modal: delete site -->
<div class="modal-backdrop" id="deleteModal" aria-hidden="true">
  <div class="modal-box">
    <div class="modal-header"><h3>Excluir site</h3></div>
    <div class="modal-body">
      Tem certeza que deseja excluir <strong><?php echo htmlspecialchars($site['name']); ?></strong>?<br>
      Todos os arquivos e páginas serão removidos permanentemente.
    </div>
    <form method="post" class="modal-footer">
      <input type="hidden" name="action" value="delete_site">
      <button type="submit" class="btn btn-danger btn-sm">Sim, excluir tudo</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('deleteModal').setAttribute('aria-hidden','true')">Cancelar</button>
    </form>
  </div>
</div>

<script>
function confirmDeletePage(id, name) {
  document.getElementById('deletePageId').value = id;
  document.getElementById('deletePageName').textContent = name;
  document.getElementById('deletePageModal').setAttribute('aria-hidden', 'false');
}
document.querySelectorAll('.modal-backdrop').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m) m.setAttribute('aria-hidden', 'true'); });
});
</script>
</body>
</html>
