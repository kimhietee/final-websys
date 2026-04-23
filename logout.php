<?php
/**
 * KIM INVENTORIES — logout.php
 * Destroys PHP session and redirects to login.
 */
session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;
