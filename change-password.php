<?php
require_once __DIR__ . "/auth.php";
requireLogin();

$user  = currentUser();
$error = null;
$done  = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current  = $_POST["current"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if (strlen($password) < 8) {
        $error = "A nova senha deve ter pelo menos 8 caracteres.";
    } elseif ($password !== $confirm) {
        $error = "As senhas não coincidem.";
    } else {
        $ok = changeUserPassword($user["id"], $current, $password);
        if ($ok) {
            $done = true;
        } else {
            $error = "Senha atual incorreta.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mudar senha — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="auth-body">
  <div class="auth-card">
    <a href="/dashboard.php" class="auth-brand"><span class="dot"></span>softaliza</a>

    <?php if ($done): ?>
      <div style="text-align:center;">
        <div style="font-size:48px;margin:16px 0;">✅</div>
        <h1 class="auth-title">Senha atualizada</h1>
        <p class="auth-sub">Sua senha foi alterada com sucesso.</p>
        <a href="dashboard.php" class="btn btn-block" style="margin-top:8px;">Voltar ao dashboard</a>
      </div>
    <?php else: ?>
      <h1 class="auth-title">Mudar senha</h1>
      <p class="auth-sub">Conta: <strong><?php echo htmlspecialchars($user["email"]); ?></strong></p>

      <?php if ($error): ?>
        <div class="auth-alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <div class="field">
          <label>Senha atual</label>
          <input type="password" name="current" required autofocus placeholder="••••••••" />
        </div>
        <div class="field">
          <label>Nova senha <span class="field-hint">(mínimo 8 caracteres)</span></label>
          <input type="password" name="password" required placeholder="••••••••" />
        </div>
        <div class="field">
          <label>Confirmar nova senha</label>
          <input type="password" name="confirm" required placeholder="••••••••" />
        </div>
        <button type="submit" class="btn btn-block">Salvar senha</button>
      </form>

      <p class="auth-footer-text"><a href="dashboard.php">Cancelar</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
