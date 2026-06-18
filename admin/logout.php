<?php
require __DIR__ . '/_sesion.php';
session_destroy();
header('Location: /admin/index.php');
exit;
