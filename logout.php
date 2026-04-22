<?php
require_once __DIR__ . "/auth.php";
logoutSession();
header("Location: /login.php");
exit;
