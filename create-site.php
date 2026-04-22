<?php
ob_start();
require __DIR__ . '/data/db.php';

$error   = '';
$success = '';

try {
    $sites = db()->query(
        "select s.id, s.name, a.name as owner
         from sites s
         join associations a on a.id = s.association_id
         where s.status = 'ativo' and s.is_inspiration = 1
         order by s.name"
    )->fetchAll();
} catch (Throwable $e) {
    $sites = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $associationName = trim($_POST['association_name'] ?? '');
    $siteName        = trim($_POST['site_name']        ?? '');
    $contactEmail    = trim($_POST['contact_email']    ?? '');
    $contactPhone    = trim($_POST['contact_phone']    ?? '');
    $contactAddress  = trim($_POST['contact_address']  ?? '');
    $domain          = trim($_POST['domain']           ?? '');
    $inspirationLink = trim($_POST['inspiration_link'] ?? '');
    $notes           = trim($_POST['notes']            ?? '');
    $eventDate       = trim($_POST['event_date']       ?? '');
    $eventLocation   = trim($_POST['event_location']   ?? '');
    $primaryColor    = trim($_POST['primary_color']    ?? '');
    $secondaryColor  = trim($_POST['secondary_color']  ?? '');
    $accentColor     = trim($_POST['accent_color']     ?? '');
    $inspirationId   = trim($_POST['inspiration_site_id'] ?? '');

    $socialLinks = [];
    foreach (['instagram','facebook','twitter','linkedin','youtube','website'] as $net) {
        $v = trim($_POST['social_' . $net] ?? '');
        if ($v !== '') $socialLinks[$net] = $v;
    }

    $pagesRequested  = array_filter(array_map('trim', $_POST['pages_requested'] ?? []));
    $pagesContentRaw = $_POST['pages_content'] ?? [];
    $pagesContent    = [];
    foreach ($pagesRequested as $pg) {
        $pagesContent[$pg] = trim($pagesContentRaw[$pg] ?? '');
    }

    $logoUrl = null;
    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/logos/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','svg','webp','gif'];
        if (in_array($ext, $allowed, true)) {
            $filename = generateUuid() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename);
            $logoUrl = 'uploads/logos/' . $filename;
        }
    }

    if ($associationName === '' || $siteName === '') {
        $error = 'Preencha os campos obrigatórios: nome da organização e nome do site.';
    } else {
        try {
            createFormRequest([
                'association_name'    => $associationName,
                'contact_email'       => $contactEmail    ?: null,
                'contact_phone'       => $contactPhone    ?: null,
                'contact_address'     => $contactAddress  ?: null,
                'site_name'           => $siteName,
                'domain'              => $domain          ?: null,
                'notes'               => $notes           ?: null,
                'logo_url'            => $logoUrl,
                'event_date'          => $eventDate       ?: null,
                'event_location'      => $eventLocation   ?: null,
                'primary_color'       => $primaryColor    ?: null,
                'secondary_color'     => $secondaryColor  ?: null,
                'accent_color'        => $accentColor     ?: null,
                'social_links'        => $socialLinks     ?: null,
                'pages_requested'     => array_values($pagesRequested) ?: null,
                'pages_content'       => $pagesContent    ?: null,
                'inspiration_site_id' => $inspirationId   ?: null,
                'inspiration_link'    => $inspirationLink ?: null,
            ]);
            $success = 'ok';
        } catch (Throwable $e) {
            $error = 'Falha ao enviar o formulário. Tente novamente em instantes.';
        }
    }
}

$availablePages = [
    'home'          => ['label' => 'Home / Página inicial',              'icon' => '🏠'],
    'sobre'         => ['label' => 'Sobre o evento / organização',       'icon' => 'ℹ️'],
    'programacao'   => ['label' => 'Programação',                        'icon' => '📅'],
    'palestrantes'  => ['label' => 'Palestrantes',                       'icon' => '🎤'],
    'comissoes'     => ['label' => 'Comissões científica / organizadora','icon' => '👥'],
    'trabalhos'     => ['label' => 'Submissão de trabalhos',             'icon' => '📄'],
    'inscricoes'    => ['label' => 'Inscrições',                         'icon' => '✍️'],
    'hospedagem'    => ['label' => 'Hospedagem / Local',                 'icon' => '🏨'],
    'patrocinadores'=> ['label' => 'Patrocinadores / Apoiadores',        'icon' => '🤝'],
    'contato'       => ['label' => 'Contato',                           'icon' => '📬'],
    'faq'           => ['label' => 'Perguntas frequentes (FAQ)',         'icon' => '❓'],
    'galeria'       => ['label' => 'Galeria de fotos',                  'icon' => '🖼️'],
    'noticias'      => ['label' => 'Notícias / Blog',                   'icon' => '📰'],
    'transmissao'   => ['label' => 'Transmissão ao vivo',               'icon' => '📺'],
];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Solicitar site — Softaliza</title>
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body class="public-page">

  <!-- NAV -->
  <nav class="public-nav">
    <a href="/" class="brand" style="text-decoration:none;"><span class="dot"></span>softaliza</a>
    <a href="/login.php" class="btn btn-secondary btn-sm" style="color:#cbd5e1;border-color:#334155;background:transparent;">Área restrita</a>
  </nav>

  <!-- HERO -->
  <div class="public-hero">
    <h1>Solicitar site</h1>
    <p>Preencha o formulário e nossa equipe criará o site da sua organização.</p>
  </div>

  <!-- FORM WRAP -->
  <div class="public-form-wrap">

    <?php if ($success === 'ok'): ?>
    <!-- SUCCESS STATE -->
    <div class="form-card" style="text-align:center;padding:48px 32px;">
      <div style="font-size:52px;margin-bottom:16px;">🎉</div>
      <h2 style="font-size:22px;font-weight:800;margin-bottom:8px;">Formulário enviado!</h2>
      <p style="color:var(--muted);font-size:15px;max-width:420px;margin:0 auto 24px;">
        Nossa equipe recebeu sua solicitação e entrará em contato pelo e-mail informado em breve.
      </p>
      <a href="/" class="btn btn-primary">Voltar ao início</a>
    </div>

    <?php else: ?>

    <!-- STEPPER -->
    <div class="stepper" id="stepper">
      <div class="step-item active" id="si-1">
        <div class="step-num">1</div>
        <div class="step-label">Identificação</div>
      </div>
      <div class="step-connector"></div>
      <div class="step-item" id="si-2">
        <div class="step-num">2</div>
        <div class="step-label">Visual</div>
      </div>
      <div class="step-connector"></div>
      <div class="step-item" id="si-3">
        <div class="step-num">3</div>
        <div class="step-label">Páginas</div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="mainForm" novalidate>

      <!-- ════════════════════════════════════════════════════
           ETAPA 1 — Identificação & contato
      ════════════════════════════════════════════════════════ -->
      <div class="form-card step-panel active" id="step-1">

        <div class="form-section-title">Dados da organização e evento</div>
        <div class="form-grid form-grid-2">
          <div class="field">
            <label>Nome da organização *</label>
            <input class="input" type="text" name="association_name" placeholder="Ex.: Sociedade Brasileira de Física" required />
          </div>
          <div class="field">
            <label>Nome do site *</label>
            <input class="input" type="text" name="site_name" placeholder="Ex.: XXIII Congresso Nacional de Física" required />
          </div>
          <div class="field">
            <label>E-mail de contato</label>
            <input class="input" type="email" name="contact_email" placeholder="contato@evento.com.br" />
          </div>
          <div class="field">
            <label>Telefone / WhatsApp</label>
            <input class="input" type="tel" name="contact_phone" placeholder="(51) 99999-0000" />
          </div>
          <div class="field" style="grid-column:span 2;">
            <label>Endereço / Cidade do evento</label>
            <input class="input" type="text" name="contact_address" placeholder="Ex.: Centro de Convenções — Porto Alegre, RS" />
          </div>
          <div class="field">
            <label>Data do evento</label>
            <input class="input" type="text" name="event_date" placeholder="Ex.: 10 a 14 de novembro de 2025" />
          </div>
          <div class="field">
            <label>Local do evento</label>
            <input class="input" type="text" name="event_location" placeholder="Ex.: Hotel Laghetto, Gramado" />
          </div>
          <div class="field" style="grid-column:span 2;">
            <label>Domínio desejado <span class="field-hint">(se já tiver)</span></label>
            <input class="input" type="text" name="domain" placeholder="congresso2025.org.br" />
          </div>
          <div class="field" style="grid-column:span 2;">
            <label>Observações gerais</label>
            <textarea class="input" name="notes" rows="3" placeholder="Prazos, restrições, informações adicionais..."></textarea>
          </div>
        </div>

        <div class="form-section-title" style="margin-top:24px;">Redes sociais</div>
        <div class="form-grid form-grid-2">
          <?php foreach (['instagram'=>'Instagram','facebook'=>'Facebook','twitter'=>'X / Twitter','linkedin'=>'LinkedIn','youtube'=>'YouTube','website'=>'Site institucional'] as $net => $lbl): ?>
          <div class="field">
            <label><?php echo $lbl; ?></label>
            <input class="input" type="url" name="social_<?php echo $net; ?>" placeholder="https://" />
          </div>
          <?php endforeach; ?>
        </div>

        <div class="step-nav">
          <span></span>
          <button type="button" class="btn btn-primary next-step">Próximo →</button>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════
           ETAPA 2 — Visual
      ════════════════════════════════════════════════════════ -->
      <div class="form-card step-panel" id="step-2">

        <div class="form-section-title">Logo da organização</div>
        <div class="logo-drop" id="logoDrop" onclick="document.getElementById('logoFile').click()">
          <input type="file" id="logoFile" name="logo" accept="image/*">
          <div id="logoPlaceholder">
            <div style="font-size:36px;margin-bottom:8px;">🖼️</div>
            <div style="font-size:14px;color:var(--muted);">Clique para enviar a logo</div>
            <div style="font-size:12px;color:var(--muted-2);margin-top:4px;">PNG, SVG, JPG ou WebP</div>
          </div>
          <img id="logoPreview" style="display:none;max-height:80px;border-radius:8px;" alt="Preview" />
        </div>

        <div class="form-section-title" style="margin-top:24px;">Paleta de cores</div>
        <div class="form-grid form-grid-3">
          <?php
          $colors = [
            'primary_color'   => ['Cor primária',   '#1f4d8f', 'Fundo do cabeçalho, botões principais'],
            'secondary_color' => ['Cor secundária', '#0ea5e9', 'Seções alternadas, destaques'],
            'accent_color'    => ['Cor de destaque', '#f59e0b', 'Badges, links, elementos especiais'],
          ];
          foreach ($colors as $name => [$label, $default, $hint]): $tid = $name . '_text'; ?>
          <div class="field">
            <label><?php echo $label; ?> <span class="field-hint"><?php echo $hint; ?></span></label>
            <div class="color-row">
              <input type="color" id="<?php echo $name; ?>_picker" value="<?php echo $default; ?>"
                oninput="document.getElementById('<?php echo $tid; ?>').value=this.value">
              <input class="input" type="text" id="<?php echo $tid; ?>" name="<?php echo $name; ?>" value="<?php echo $default; ?>"
                oninput="document.getElementById('<?php echo $name; ?>_picker').value=this.value" />
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="form-section-title" style="margin-top:24px;">Inspiração visual <span class="field-hint" style="font-size:12px;">(opcional)</span></div>
        <p style="font-size:13px;color:var(--muted);margin:0 0 14px;">Selecione um site existente como referência de estilo ou informe um link.</p>

        <?php if ($sites): ?>
        <div class="inspiration-grid">
          <label class="inspiration-card">
            <input type="radio" name="inspiration_site_id" value="" checked />
            <div class="inspiration-thumb">🚫</div>
            <div class="inspiration-info"><strong>Nenhum</strong><span>Sem referência</span></div>
          </label>
          <?php foreach ($sites as $s): ?>
          <label class="inspiration-card">
            <input type="radio" name="inspiration_site_id" value="<?php echo htmlspecialchars($s['id']); ?>" />
            <div class="inspiration-thumb">🌐</div>
            <div class="inspiration-info">
              <strong><?php echo htmlspecialchars($s['name']); ?></strong>
              <span><?php echo htmlspecialchars($s['owner'] ?? ''); ?></span>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="field" style="margin-top:18px;">
          <label>Link de inspiração <span class="field-hint">(Behance, site externo, etc.)</span></label>
          <input class="input" type="url" name="inspiration_link" placeholder="https://" />
        </div>

        <div class="step-nav">
          <button type="button" class="btn btn-secondary prev-step">← Anterior</button>
          <button type="button" class="btn btn-primary next-step">Próximo →</button>
        </div>
      </div>

      <!-- ════════════════════════════════════════════════════
           ETAPA 3 — Páginas
      ════════════════════════════════════════════════════════ -->
      <div class="form-card step-panel" id="step-3">

        <div class="form-section-title">Quais páginas seu site deve ter?</div>
        <p style="font-size:13px;color:var(--muted);margin:0 0 18px;">Selecione as páginas desejadas. Para cada uma você pode descrever o conteúdo.</p>

        <div class="pages-grid">
          <?php foreach ($availablePages as $slug => $pg): ?>
          <label class="page-check">
            <input type="checkbox" name="pages_requested[]" value="<?php echo $slug; ?>"
              onchange="togglePageContent('<?php echo $slug; ?>', this.checked)">
            <span><?php echo $pg['icon']; ?></span>
            <?php echo htmlspecialchars($pg['label']); ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div id="pagesContentContainer" style="margin-top:20px;display:grid;gap:12px;">
          <?php foreach ($availablePages as $slug => $pg): ?>
          <div class="page-content-area" id="content-<?php echo $slug; ?>">
            <div class="field">
              <label>
                Conteúdo da página: <strong><?php echo htmlspecialchars($pg['label']); ?></strong>
                <span class="field-hint">Descreva textos, listas e informações que devem aparecer.</span>
              </label>
              <textarea class="input" name="pages_content[<?php echo $slug; ?>]" rows="4"
                placeholder="Ex.: Texto de boas-vindas, programação dos dias..."></textarea>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="step-nav">
          <button type="button" class="btn btn-secondary prev-step">← Anterior</button>
          <button type="submit" class="btn btn-primary">✓ Enviar solicitação</button>
        </div>
      </div>

    </form>
    <?php endif; ?>
  </div><!-- /public-form-wrap -->

  <div style="text-align:center;padding:32px;color:var(--muted);font-size:13px;">
    © <?php echo date('Y'); ?> Softaliza · <a href="/">softaliza.com.br</a>
  </div>

</body>
<script>
// ── Stepper ──────────────────────────────────────────────────
let currentStep = 1;

function goToStep(step) {
  document.querySelectorAll('.step-panel').forEach((p, i) => {
    p.classList.toggle('active', i + 1 === step);
  });
  for (let i = 1; i <= 3; i++) {
    const si = document.getElementById('si-' + i);
    if (!si) continue;
    si.classList.toggle('active', i === step);
    si.classList.toggle('done',   i < step);
  }
  currentStep = step;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

document.querySelectorAll('.next-step').forEach(btn => {
  btn.addEventListener('click', () => { if (currentStep < 3) goToStep(currentStep + 1); });
});
document.querySelectorAll('.prev-step').forEach(btn => {
  btn.addEventListener('click', () => { if (currentStep > 1) goToStep(currentStep - 1); });
});

// ── Logo preview ─────────────────────────────────────────────
document.getElementById('logoFile')?.addEventListener('change', function() {
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('logoPreview');
    img.src = e.target.result;
    img.style.display = 'block';
    document.getElementById('logoPlaceholder').style.display = 'none';
  };
  reader.readAsDataURL(file);
});

// ── Pages checkbox ────────────────────────────────────────────
function togglePageContent(slug, checked) {
  document.getElementById('content-' + slug)?.classList.toggle('visible', checked);
}
</script>
</html>
