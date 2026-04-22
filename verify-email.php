<?php
require_once __DIR__ . "/auth.php";

$token = trim($_GET["token"] ?? "");
$ok    = false;

if ($token !== "") {
    $ok = verifyUserEmail($token);
}

if ($ok) {
    flash("success", "E-mail confirmado! Você já pode entrar na sua conta.");
    header("Location: /login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Verificação de e-mail — Softaliza</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="auth-body">
  <div class="auth-card" style="text-align:center;">
    <a href="/" class="auth-brand"><span class="dot"></span>softaliza</a>
    <div style="font-size:48px;margin:16px 0;">❌</div>
    <h1 class="auth-title">Link inválido</h1>
    <p class="auth-sub">
      Este link de verificação é inválido ou já foi utilizado.<br>
      Registre-se novamente ou entre em contato com o suporte.
    </p>
    <a href="register.php" class="btn btn-block" style="margin-top:8px;">Criar nova conta</a>
    <p class="auth-footer-text"><a href="login.php">Ir para o login</a></p>
  </div>
</body>
</html>
