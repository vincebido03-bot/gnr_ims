<?php

require_once __DIR__ . "/auth.php";

requireAdminAccess();

syncLowStockNotifications();

$user = currentUser();

?>