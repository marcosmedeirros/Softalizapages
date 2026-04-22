<?php
require_once __DIR__ . "/auth.php";

if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$error = null;
$next  = preg_replace('/[^a-zA-Z0-9\/_\-\.\?=&]/', '', $_GET["next"] ?? "/dashboard.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $result = attemptLogin($email, $password);
    if ($result === "ok") {
        header("Location: " . $next);
        exit;
    }

    $messages = [
        "email_not_found" => "E-mail não encontrado.",
        "wrong_password"  => "Senha incorreta.",
        "not_verified"    => "Confirme seu e-mail antes de entrar. Verifique sua caixa de entrada.",
    ];
    $error = $messages[$result] ?? "Erro ao autenticar. Tente novamente.";
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Entrar — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="auth-body">
  <div class="auth-card">
    <a href="/" class="auth-brand"><span class="dot"></span>softaliza</a>
    <h1 class="auth-title">Entrar na conta</h1>
    <p class="auth-sub">Acesse o painel de gerenciamento de sites.</p>

    <?php if ($error): ?>
      <div class="auth-alert error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php $ok = flash("success"); if ($ok): ?>
      <div class="auth-alert success"><?php echo htmlspecialchars($ok); ?></div>
    <?php endif; ?>

    <form method="post" class="auth-form">
      <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
      <div class="field">
        <label>E-mail</label>
        <input class="input" type="email" name="email" required autofocus
               value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
               placeholder="seu@email.com" />
      </div>
      <div class="field">
        <label>Senha</label>
        <input class="input" type="password" name="password" required placeholder="••••••••" />
      </div>
      <div class="field-row">
        <a href="forgot-password.php" class="link-sm">Esqueci minha senha</a>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>

    <p class="auth-footer-text">
      Não tem conta? <a href="register.php">Criar conta grátis</a>
    </p>
  </div>
</body>
</html>
