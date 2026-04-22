<?php
require_once __DIR__ . '/auth.php';
if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Softaliza — Crie sites profissionais para organizações</title>
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body class="landing-body">

  <!-- NAV -->
  <nav class="landing-nav">
    <div class="landing-nav-inner">
      <div class="brand"><span class="dot"></span>softaliza</div>
      <div class="landing-nav-links">
        <a href="/create-site.php">Solicitar site</a>
        <a href="/login.php">Entrar</a>
        <a href="/register.php" class="btn btn-primary btn-sm">Criar conta</a>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section style="background:#fff;">
    <div class="hero">
      <div class="hero-inner">
        <div class="hero-eyebrow">🚀 Plataforma de sites para organizações</div>
        <h1 class="hero-title">
          Sites profissionais<br>
          para sua <span>organização</span>
        </h1>
        <p class="hero-sub">
          A Softaliza cria e gerencia sites de eventos, congressos e associações —
          com domínio próprio, páginas personalizadas e gestão centralizada.
        </p>
        <div class="hero-actions">
          <a href="/create-site.php" class="btn btn-primary btn-lg">Solicitar meu site</a>
          <a href="/login.php" class="btn btn-secondary btn-lg">Acessar painel</a>
        </div>
      </div>
      <div class="hero-visual">
        <div class="mock-browser">
          <div class="mock-bar">
            <span class="mock-dot r"></span>
            <span class="mock-dot y"></span>
            <span class="mock-dot g"></span>
            <span class="mock-url">congresso2025.org.br</span>
          </div>
          <div class="mock-content">
            <div class="mock-header-strip"></div>
            <div class="mock-body">
              <div class="mock-line w80"></div>
              <div class="mock-line w60"></div>
              <div class="mock-line w90"></div>
              <div class="mock-btns">
                <div class="mock-btn"></div>
                <div class="mock-btn ghost"></div>
              </div>
            </div>
            <div class="mock-cards">
              <div class="mock-card"></div>
              <div class="mock-card"></div>
              <div class="mock-card"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section class="features">
    <div class="features-inner">
      <h2 class="features-title">Tudo o que você precisa</h2>
      <p class="features-sub">Da solicitação à publicação, gerenciamos cada etapa com você.</p>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">📋</div>
          <h3>Formulário inteligente</h3>
          <p>Preencha um formulário detalhado com logo, cores, páginas e conteúdo. Nossa equipe recebe tudo organizado.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🎨</div>
          <h3>Design personalizado</h3>
          <p>Templates profissionais com sua identidade visual — cores, logo e tipografia da sua organização.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🌐</div>
          <h3>Domínio próprio + SSL</h3>
          <p>Cada site recebe seu domínio personalizado com certificado SSL automático via Vercel Platforms.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">⚙️</div>
          <h3>Gestão centralizada</h3>
          <p>Hub com todos os sites em um painel. Crie páginas, publique, altere domínios e acompanhe o status.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">📄</div>
          <h3>Páginas HTML</h3>
          <p>Páginas geradas como arquivos HTML puros — rápidas, leves e sem dependências de CMS complexos.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🚀</div>
          <h3>Deploy automático</h3>
          <p>Publique páginas com um clique. A infraestrutura Vercel garante disponibilidade global e alta performance.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section">
    <h2>Pronto para começar?</h2>
    <p>Solicite o site da sua organização agora ou faça login para gerenciar sites existentes.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
      <a href="/create-site.php" class="btn btn-primary btn-lg">Solicitar meu site</a>
      <a href="/login.php" class="btn btn-secondary btn-lg" style="background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff;">Já tenho conta</a>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="landing-footer">
    <div class="brand"><span class="dot"></span>softaliza</div>
    <p style="margin-top:8px;">© <?php echo date('Y'); ?> Softaliza. Todos os direitos reservados.</p>
  </footer>

</body>
</html>
