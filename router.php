<?php
// ============================================================
// router.php — Multi-tenant domain router
// Fica na raiz pública do servidor (ex: /var/www/softaliza/public/)
// Nginx direciona TODO tráfego aqui via try_files
// ============================================================

define('SOFTALIZA_ROOT', dirname(__DIR__));  // pasta acima de public/
define('SITES_ROOT', SOFTALIZA_ROOT . '/sites');
define('ADMIN_HOST', getenv('ADMIN_HOST') ?: 'admin.softaliza.com.br');

require SOFTALIZA_ROOT . '/data/db.php';

// ── 1. Detecta o host da requisição ──────────────────────────
$host = strtolower(trim($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $host);  // remove porta se houver

// ── 2. Painel admin — não roteia como site ────────────────────
if ($host === ADMIN_HOST || $host === '' || $host === 'localhost') {
    // Serve o painel normalmente (index.php do admin)
    $adminFile = SOFTALIZA_ROOT . '/admin/index.php';
    if (file_exists($adminFile)) {
        require $adminFile;
    } else {
        http_response_code(404);
        echo '<h1>Painel não encontrado</h1>';
    }
    exit;
}

// ── 3. Busca o site pelo domínio no banco ─────────────────────
$site = fetchSiteByDomain($host);

if (!$site) {
    http_response_code(404);
    include SOFTALIZA_ROOT . '/public/404.php';
    exit;
}

// ── 4. Determina qual arquivo servir ──────────────────────────
$siteDir = SITES_ROOT . '/' . $site['id'];

// Pega o path da URL (ex: /sobre, /programacao.html)
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestPath = '/' . trim($requestPath, '/');

// Remove a extensão .html se vier sem ela, tenta com ela
$candidates = resolveFilePath($siteDir, $requestPath);

$filePath = null;
foreach ($candidates as $candidate) {
    if (file_exists($candidate) && is_file($candidate)) {
        $filePath = $candidate;
        break;
    }
}

// Fallback para index.html se nada foi encontrado
if (!$filePath) {
    $index = $siteDir . '/index.html';
    if (file_exists($index)) {
        $filePath = $index;
    }
}

// 404 real
if (!$filePath) {
    http_response_code(404);
    serveSite404($site, $siteDir);
    exit;
}

// ── 5. Serve o arquivo HTML ───────────────────────────────────
serveHtmlFile($filePath, $site, $siteDir);
exit;


// ════════════════════════════════════════════════════════════
// Funções auxiliares
// ════════════════════════════════════════════════════════════

/**
 * Gera lista de caminhos candidatos para um request path
 */
function resolveFilePath(string $siteDir, string $requestPath): array
{
    $candidates = [];

    if ($requestPath === '/' || $requestPath === '') {
        $candidates[] = $siteDir . '/index.html';
        return $candidates;
    }

    // Remove extensão e tenta com/sem .html
    $clean = preg_replace('/\.html?$/i', '', $requestPath);
    $candidates[] = $siteDir . $clean . '.html';
    $candidates[] = $siteDir . $clean . '/index.html';
    $candidates[] = $siteDir . $requestPath;  // exato

    return $candidates;
}

/**
 * Serve um arquivo HTML injetando header/footer do site
 */
function serveHtmlFile(string $filePath, array $site, string $siteDir): void
{
    $html = file_get_contents($filePath);
    if ($html === false) {
        http_response_code(500);
        echo '<h1>Erro ao carregar página</h1>';
        return;
    }

    // Injeta meta tag canônica com o domínio correto
    $domain  = htmlspecialchars($site['domain'] ?? '', ENT_QUOTES);
    $path    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $canonical = $domain ? "<link rel=\"canonical\" href=\"https://{$domain}{$path}\" />" : '';

    if ($canonical) {
        $html = preg_replace('/<\/head>/i', $canonical . "\n</head>", $html, 1);
    }

    // Injeta cores do site como variáveis CSS
    $cssVars = buildSiteCssVars($site);
    if ($cssVars) {
        $html = preg_replace('/<\/head>/i', "<style>:root{{$cssVars}}</style>\n</head>", $html, 1);
    }

    header('Content-Type: text/html; charset=utf-8');
    header('X-Site-Id: ' . $site['id']);
    echo $html;
}

/**
 * Gera variáveis CSS com as cores do site
 */
function buildSiteCssVars(array $site): string
{
    $vars = '';
    if (!empty($site['primary_color'])) {
        $vars .= '--site-primary:' . htmlspecialchars($site['primary_color'], ENT_QUOTES) . ';';
    }
    if (!empty($site['secondary_color'])) {
        $vars .= '--site-secondary:' . htmlspecialchars($site['secondary_color'], ENT_QUOTES) . ';';
    }
    if (!empty($site['accent_color'])) {
        $vars .= '--site-accent:' . htmlspecialchars($site['accent_color'], ENT_QUOTES) . ';';
    }
    return $vars;
}

/**
 * Página 404 customizada para o site
 */
function serveSite404(array $site, string $siteDir): void
{
    $custom = $siteDir . '/404.html';
    if (file_exists($custom)) {
        serveHtmlFile($custom, $site, $siteDir);
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    $name = htmlspecialchars($site['name'] ?? 'Site', ENT_QUOTES);
    echo "<!DOCTYPE html><html lang='pt-br'><head><meta charset='utf-8'><title>Página não encontrada — {$name}</title></head>"
       . "<body style='font-family:Arial,sans-serif;text-align:center;padding:60px'>"
       . "<h1>404</h1><p>Página não encontrada em <strong>{$name}</strong>.</p>"
       . "<a href='/'>← Voltar ao início</a></body></html>";
}
