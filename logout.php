<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

logout_user();
header('Location: ' . url('login.php?signed_out=1'));
exit;
