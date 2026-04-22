<?php
// ============================================================
// vercel.php — Wrapper para a API de Domínios da Vercel
// Docs: https://vercel.com/platforms
// ============================================================

require_once __DIR__ . '/config.php';

class VercelDomains
{
    private string $token;
    private string $projectId;
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
        return $this->token !== '' && $this->projectId !== '';
    }

    // Adiciona um domínio ao projeto Vercel
    public function addDomain(string $domain): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $body = json_encode(['name' => $domain]);
        $resp = $this->request('POST', "/v9/projects/{$this->projectId}/domains", $body);

        if ($resp['status'] === 200 || $resp['status'] === 409) {
            // 409 = domain already exists in project (ok)
            return ['ok' => true, 'data' => $resp['body']];
        }

        $msg = $resp['body']['error']['message'] ?? 'Erro desconhecido da API Vercel.';
        return ['ok' => false, 'error' => $msg, 'status' => $resp['status']];
    }

    // Remove um domínio do projeto Vercel
    public function removeDomain(string $domain): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $resp = $this->request('DELETE', "/v9/projects/{$this->projectId}/domains/{$domain}");
        if ($resp['status'] === 200 || $resp['status'] === 204 || $resp['status'] === 404) {
            return ['ok' => true];
        }
        $msg = $resp['body']['error']['message'] ?? 'Erro ao remover domínio.';
        return ['ok' => false, 'error' => $msg];
    }

    // Verifica status e configuração DNS de um domínio
    public function getDomainStatus(string $domain): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $resp = $this->request('GET', "/v9/projects/{$this->projectId}/domains/{$domain}");
        if ($resp['status'] === 200) {
            $d = $resp['body'];
            return [
                'ok'       => true,
                'verified' => $d['verified'] ?? false,
                'cname'    => $d['cname'] ?? 'cname.vercel-dns.com',
                'nameservers' => $d['nameservers'] ?? [],
                'conflicts' => $d['conflicts'] ?? [],
            ];
        }
        return ['ok' => false, 'error' => 'Domínio não encontrado no projeto.'];
    }

    // Verifica/confirma o domínio (aciona a verificação DNS da Vercel)
    public function verifyDomain(string $domain): array
    {
        if (!$this->isConfigured()) return ['ok' => false, 'error' => 'Vercel não configurado.'];

        $resp = $this->request('POST', "/v9/projects/{$this->projectId}/domains/{$domain}/verify");
        if ($resp['status'] === 200) {
            $verified = $resp['body']['verified'] ?? false;
            return ['ok' => true, 'verified' => $verified];
        }
        $msg = $resp['body']['error']['message'] ?? 'Erro na verificação.';
        return ['ok' => false, 'error' => $msg];
    }

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
            CURLOPT_TIMEOUT        => 15,
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
