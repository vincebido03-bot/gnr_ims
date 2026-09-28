<?php

require_once __DIR__ . "/../../includes/numbering.php";
require_once __DIR__ . "/auth.php";

requireAdminAccess();
requireAdminPagePermission();

syncLowStockNotifications();

$user = currentUser();

?>