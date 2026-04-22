<?php
ob_start();

require_once __DIR__ . "/data/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Sessão ───────────────────────────────────────────────────

function currentUser(): ?array
{
    return $_SESSION["user"] ?? null;
}

function isLoggedIn(): bool
{
    return isset($_SESSION["user"]["id"]);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: /login.php?next=" . urlencode($_SERVER["REQUEST_URI"]));
        exit;
    }
}

function loginSession(array $user): void
{
    session_regenerate_id(true);
    $_SESSION["user"] = [
        "id"    => $user["id"],
        "name"  => $user["name"],
        "email" => $user["email"],
    ];
}

function logoutSession(): void
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}

// ── Autenticação ─────────────────────────────────────────────

function attemptLogin(string $email, string $password): string
{
    $user = findUserByEmail($email);
    if (!$user) return "email_not_found";
    if (!password_verify($password, $user["password"])) return "wrong_password";
    if (empty($user["email_verified_at"])) return "not_verified";
    loginSession($user);
    return "ok";
}

function attemptRegister(string $name, string $email, string $password): array
{
    if (findUserByEmail($email)) {
        return ["ok" => false, "error" => "email_taken"];
    }
    $user  = createUser($name, $email, $password);
    $token = $user["verification_token"];
    $link  = siteBaseUrl() . "/verify-email.php?token=" . $token;
    sendAuthEmail(
        $email,
        "Confirme seu e-mail — Softaliza",
        "Olá, {$name}!\n\nClique no link abaixo para ativar sua conta:\n{$link}\n\nO link é válido por 48 horas."
    );
    return ["ok" => true, "dev_link" => $link, "token" => $token];
}

function attemptForgotPassword(string $email): array
{
    $token = generatePasswordResetToken($email);
    if (!$token) return ["ok" => false];
    $link = siteBaseUrl() . "/reset-password.php?token=" . $token;
    sendAuthEmail(
        $email,
        "Redefinição de senha — Softaliza",
        "Clique no link abaixo para redefinir sua senha:\n{$link}\n\nO link expira em 2 horas."
    );
    return ["ok" => true, "dev_link" => $link];
}

// ── E-mail ───────────────────────────────────────────────────

function sendAuthEmail(string $to, string $subject, string $body): void
{
    $headers = "From: noreply@softaliza.com.br\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($to, $subject, $body, $headers);
    // Em desenvolvimento o link é exibido na tela pelo código chamador
}

function siteBaseUrl(): string
{
    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    $host   = $_SERVER["HTTP_HOST"] ?? "localhost";
    return $scheme . "://" . $host;
}

// ── Flash messages ───────────────────────────────────────────

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION["flash"][$key] = $value;
        return null;
    }
    $msg = $_SESSION["flash"][$key] ?? null;
    unset($_SESSION["flash"][$key]);
    return $msg;
}
