<?php
// sites.php — redireciona para o hub principal
require_once __DIR__ . '/auth.php';
requireLogin();
header('Location: dashboard.php');
exit;
