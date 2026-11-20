<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_login();

$RESOURCE = 'faculties';
require_once ROOT_PATH . '/includes/resource_engine.php';
