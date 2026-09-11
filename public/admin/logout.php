<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin.php';

admin_logout();
header('Location: /admin/login.php');
exit;

