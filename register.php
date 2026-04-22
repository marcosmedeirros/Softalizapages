<?php
require_once __DIR__ . "/auth.php";

if (isLoggedIn()) {
    header("Location: /dashboard.php");
    exit;
}

$error   = null;
$devLink = null;
$done    = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST["name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if (strlen($name) < 2) {
        $error = "Informe seu nome completo.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "E-mail inválido.";
    } elseif (strlen($password) < 8) {
        $error = "A senha deve ter pelo menos 8 caracteres.";
    } elseif ($password !== $confirm) {
        $error = "As senhas não coincidem.";
    } else {
        $result = attemptRegister($name, $email, $password);
        if (!$result["ok"]) {
            $error = "Este e-mail já está cadastrado.";
        } else {
            $done    = true;
            $devLink = $result["dev_link"];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Criar conta — Softaliza</title>
  <link rel="stylesheet" href="/assets/style.css" />
</head>
<body class="auth-body">
  <div class="auth-card">
    <a href="/" class="auth-brand"><span class="dot"></span>softaliza</a>

    <?php if ($done): ?>
      <h1 class="auth-title">Verifique seu e-mail</h1>
      <p class="auth-sub">
        Enviamos um link de confirmação para <strong><?php echo htmlspecialchars($_POST["email"]); ?></strong>.
        Clique no link para ativar sua conta.
      </p>
      <?php if ($devLink): ?>
        <div class="auth-alert info">
          <strong>Ambiente de desenvolvimento:</strong><br>
          <a href="<?php echo htmlspecialchars($devLink); ?>" style="word-break:break-all;">
            <?php echo htmlspecialchars($devLink); ?>
          </a>
        </div>
      <?php endif; ?>
      <a href="login.php" class="btn btn-block" style="margin-top:16px;">Ir para o login</a>
    <?php else: ?>
      <h1 class="auth-title">Criar conta</h1>
      <p class="auth-sub">Gerencie sites da sua organização na Softaliza.</p>

      <?php if ($error): ?>
        <div class="auth-alert error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form">
        <div class="field">
          <label>Nome completo</label>
          <input type="text" name="name" required autofocus
                 value="<?php echo htmlspecialchars($_POST["name"] ?? ""); ?>"
                 placeholder="Seu nome" />
        </div>
        <div class="field">
          <label>E-mail</label>
          <input type="email" name="email" required
                 value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                 placeholder="seu@email.com" />
        </div>
        <div class="field">
          <label>Senha <span class="field-hint">(mínimo 8 caracteres)</span></label>
          <input type="password" name="password" required placeholder="••••••••" />
        </div>
        <div class="field">
          <label>Confirmar senha</label>
          <input type="password" name="confirm" required placeholder="••••••••" />
        </div>
        <button type="submit" class="btn btn-block">Criar conta</button>
      </form>

      <p class="auth-footer-text">
        Já tem conta? <a href="login.php">Entrar</a>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>
