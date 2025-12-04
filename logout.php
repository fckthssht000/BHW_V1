<?php
session_start();
require_once 'automatic_backup.php';
session_destroy();
header("Location: index.php");
exit;
?>