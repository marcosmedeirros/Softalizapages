<?php
// ============================================================
// vercel.php — Vercel Multi-Project API wrapper
// Docs: https://vercel.com/docs/rest-api
// ============================================================

require_once __DIR__ . '/config.php';

class VercelDomains
{
    private string $token;
    private string $projectId;  // projeto legado (single-project mode)
    private string $teamId;
    private string $baseUrl = 'https://api.vercel.com';

    public function __construct()
    {
        $this->token     = VERCEL_TOKEN;
        $this->projectId = VERCEL_PROJECT_ID;
        $this->teamId    = VERCEL_TEAM_ID;
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    // ── Projetos ──────────────────────────────────────────────

    /**
     * Cria um projeto Vercel dedicado para um site.
     * Retorna ['ok' => true, 'project_id' => '...', 'name' => '...']
     */
    public function createProject(string $siteName, string $siteId): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Token Vercel não configurado.'];

        // Nome do projeto: prefixo + siteId (slugificado, máx 52 chars)
        $slug = 'site-' . substr(preg_replace('/[^a-z0-9]/', '-', strtolower($siteName)), 0, 40);
        $slug = rtrim($slug, '-');

        $body = json_encode([
            'name'      => $slug,
            'framework' => null,   // static HTML, sem framework
        ]);

        $resp = $this->request('POST', '/v9/projects', $body);

        if (in_array($resp['status'], [200, 201])) {
            return [
                'ok'         => true,
                'project_id' => $resp['body']['id'],
                'name'       => $resp['body']['name'],
            ];
        }

        // Projeto já existe com esse nome → busca pelo slug
        if ($resp['status'] === 409) {
            return $this->getProjectByName($slug);
        }

        $msg = $resp['body']['error']['message'] ?? 'Erro ao criar projeto Vercel.';
        return ['ok' => false, 'error' => $msg, 'status' => $resp['status']];
    }

    /**
     * Busca um projeto pelo nome.
     */
    public function getProjectByName(string $name): array
    {
        $resp = $this->request('GET', '/v9/projects/' . urlencode($name));
        if ($resp['status'] === 200) {
            return ['ok' => true, 'project_id' => $resp['body']['id'], 'name' => $resp['body']['name']];
        }
        return ['ok' => false, 'error' => 'Projeto não encontrado.'];
    }

    // ── Deploy de arquivos HTML ───────────────────────────────

    /**
     * Faz deploy dos arquivos HTML de um site no Vercel.
     *
     * $files = [
     *   'index.html'  => '<html>...</html>',
     *   'sobre.html'  => '<html>...</html>',
     *   '_shared/header.html' => '...',
     * ]
     *
     * Retorna ['ok' => true, 'url' => 'https://...vercel.app']
     */
    public function deploy(string $projectId, array $files): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Token Vercel não configurado.'];
        if (empty($files))          return ['ok' => false, 'error' => 'Nenhum arquivo para deploy.'];

        // 1. Faz upload de cada arquivo e coleta os hashes SHA1
        $fileEntries = [];
        foreach ($files as $path => $content) {
            $upload = $this->uploadFile($content);
            if (!$upload['ok']) return $upload;
            $fileEntries[] = [
                'file' => $path,
                'sha'  => $upload['sha'],
                'size' => strlen($content),
            ];
        }

        // 2. Cria o deployment referenciando os hashes
        $body = json_encode([
            'name'      => $projectId,
            'projectId' => $projectId,
            'target'    => 'production',
            'files'     => $fileEntries,
        ]);

        $resp = $this->request('POST', '/v13/deployments', $body);

        if (in_array($resp['status'], [200, 201])) {
            $url = $resp['body']['url'] ?? '';
            return ['ok' => true, 'url' => 'https://' . $url, 'id' => $resp['body']['id'] ?? ''];
        }

        $msg = $resp['body']['error']['message'] ?? 'Erro ao criar deployment.';
        return ['ok' => false, 'error' => $msg, 'status' => $resp['status']];
    }

    /**
     * Faz upload de um arquivo para a Vercel e retorna o SHA1.
     * A Vercel deduplica automaticamente arquivos com o mesmo hash.
     */
    private function uploadFile(string $content): array
    {
        $sha  = sha1($content);
        $size = strlen($content);

        $ch = curl_init($this->baseUrl . '/v2/files');
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/octet-stream',
            'x-vercel-digest: ' . $sha,
            'Content-Length: ' . $size,
        ];
        if ($this->teamId !== '') {
            $headers[] = 'x-vercel-team-id: ' . $this->teamId;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 200 = uploaded, 204 = already exists (deduplicated) — ambos ok
        if ($status === 200 || $status === 204) {
            return ['ok' => true, 'sha' => $sha];
        }

        $decoded = $raw ? json_decode($raw, true) : [];
        $msg = $decoded['error']['message'] ?? "Erro no upload (HTTP {$status}).";
        return ['ok' => false, 'error' => $msg];
    }

    // ── Domínios ─────────────────────────────────────────────

    /**
     * Adiciona um domínio a um projeto específico.
     * Se $projectId for null, usa o projeto padrão (VERCEL_PROJECT_ID).
     */
    public function addDomain(string $domain, ?string $projectId = null): array
    {
        $pid = $projectId ?? $this->projectId;
        if (!$this->isConfigured() || !$pid) return ['ok' => false, 'error' => 'Projeto Vercel não configurado.'];

        $body = json_encode(['name' => $domain]);
        $resp = $this->request('POST', "/v9/projects/{$pid}/domains", $body);

        if ($resp['status'] === 200 || $resp['status'] === 409) {
            return ['ok' => true, 'data' => $resp['body']];
        }

        $msg = $resp['body']['error']['message'] ?? 'Erro desconhecido da API Vercel.';
        return ['ok' => false, 'error' => $msg, 'status' => $resp['status']];
    }

    /**
     * Remove um domínio de um projeto.
     */
    public function removeDomain(string $domain, ?string $projectId = null): array
    {
        $pid = $projectId ?? $this->projectId;
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $resp = $this->request('DELETE', "/v9/projects/{$pid}/domains/{$domain}");
        if (in_array($resp['status'], [200, 204, 404])) {
            return ['ok' => true];
        }
        $msg = $resp['body']['error']['message'] ?? 'Erro ao remover domínio.';
        return ['ok' => false, 'error' => $msg];
    }

    /**
     * Retorna status DNS e configuração de um domínio.
     * Resposta inclui o CNAME necessário para o cliente configurar.
     */
    public function getDomainStatus(string $domain, ?string $projectId = null): array
    {
        $pid = $projectId ?? $this->projectId;
        if (!$this->isConfigured() || !$pid) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $resp = $this->request('GET', "/v9/projects/{$pid}/domains/{$domain}");
        if ($resp['status'] === 200) {
            $d = $resp['body'];
            return [
                'ok'        => true,
                'verified'  => $d['verified'] ?? false,
                'cname'     => $d['cname'] ?? 'cname.vercel-dns.com',
                'conflicts' => $d['conflicts'] ?? [],
            ];
        }
        return ['ok' => false, 'error' => 'Domínio não encontrado no projeto.'];
    }

    /**
     * Aciona a verificação DNS de um domínio na Vercel.
     */
    public function verifyDomain(string $domain, ?string $projectId = null): array
    {
        $pid = $projectId ?? $this->projectId;
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $resp = $this->request('POST', "/v9/projects/{$pid}/domains/{$domain}/verify");
        if ($resp['status'] === 200) {
            return ['ok' => true, 'verified' => $resp['body']['verified'] ?? false];
        }
        $msg = $resp['body']['error']['message'] ?? 'Erro na verificação.';
        return ['ok' => false, 'error' => $msg];
    }

    // ── HTTP helper ───────────────────────────────────────────

    private function request(string $method, string $path, ?string $body = null): array
    {
        $url = $this->baseUrl . $path;
        if ($this->teamId !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'teamId=' . urlencode($this->teamId);
        }

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = $raw ? json_decode($raw, true) : [];
        return ['status' => $status, 'body' => $decoded ?? []];
    }
}
