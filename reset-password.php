<?php
require_once __DIR__ . "/auth.php";

$token = trim($_GET["token"] ?? "");
$error = null;
$done  = false;

// Valida o token antes de mostrar o form
$user = $token ? findUserByToken("reset_token", $token) : null;
$validToken = $user
    && !empty($user["reset_token_expires_at"])
    && strtotime($user["reset_token_expires_at"]) >= time();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if (!$validToken) {
        $error = "Link inválido ou expirado.";
    } elseif (strlen($password) < 8) {
        $error = "A senha deve ter pelo menos 8 caracteres.";
    } elseif ($password !== $confirm) {
        $error = "As senhas não coincidem.";
    } else {
        $ok = resetUserPassword($token, $password);
        if ($ok) {
            flash("success", "Senha redefinida com sucesso! Faça login com a nova senha.");
            header("Location: /login.php");
            exit;
        }
        $error = "Erro ao redefinir senha. O link pode ter expirado.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Nova senha — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="auth-body">
  <div class="auth-card">
    <a href="/" class="auth-brand"><span class="dot"></span>softaliza</a>

    <?php if (!$validToken): ?>
      <div style="text-align:center;">
        <div style="font-size:48px;margin:16px 0;">⏱️</div>
        <h1 class="auth-title">Link inválido ou expirado</h1>
        <p class="auth-sub">Solicite um novo link de redefinição de senha.</p>
        <a href="forgot-password.php" class="btn btn-block" style="margin-top:8px;">Solicitar novo link</a>
      </div>
    <?php else: ?>
      <h1 class="auth-title">Criar nova senha</h1>
      <p class="auth-sub">Escolha uma senha forte para sua conta.</p>

      <?php if ($error): ?>
        <div class="auth-alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div class="field">
          <label>Nova senha <span class="field-hint">(mínimo 8 caracteres)</span></label>
          <input type="password" name="password" required autofocus placeholder="••••••••" />
        </div>
        <div class="field">
          <label>Confirmar nova senha</label>
          <input type="password" name="confirm" required placeholder="••••••••" />
        </div>
        <button type="submit" class="btn btn-block">Salvar nova senha</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
