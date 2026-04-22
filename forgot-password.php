<?php
require_once __DIR__ . "/auth.php";

if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$done    = false;
$devLink = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email  = trim($_POST["email"] ?? "");
    $result = attemptForgotPassword($email);
    $done    = true;
    $devLink = $result["dev_link"] ?? null;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Recuperar senha — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="auth-body">
  <div class="auth-card">
    <a href="/" class="auth-brand"><span class="dot"></span>softaliza</a>

    <?php if ($done): ?>
      <h1 class="auth-title">Verifique seu e-mail</h1>
      <p class="auth-sub">
        Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha em breve.
      </p>
      <?php if ($devLink): ?>
        <div class="auth-alert info">
          <strong>Ambiente de desenvolvimento:</strong><br>
          <a href="<?php echo htmlspecialchars($devLink); ?>" style="word-break:break-all;">
            <?php echo htmlspecialchars($devLink); ?>
          </a>
        </div>
      <?php endif; ?>
      <a href="login.php" class="btn btn-block" style="margin-top:16px;">Voltar ao login</a>
    <?php else: ?>
      <h1 class="auth-title">Recuperar senha</h1>
      <p class="auth-sub">Informe seu e-mail e enviaremos um link para criar uma nova senha.</p>

      <form method="post" class="auth-form">
        <div class="field">
          <label>E-mail</label>
          <input type="email" name="email" required autofocus placeholder="seu@email.com" />
        </div>
        <button type="submit" class="btn btn-block">Enviar link</button>
      </form>

      <p class="auth-footer-text"><a href="login.php">Voltar ao login</a></p>
    <?php endif; ?>
  </div>
</body>
</html>
