<?php

session_start();

$_SESSION = [];

session_destroy();

header("Location: /grease-n-resin/admin/index.php");
exit;