

<?php
session_start();
$cookie_name = "my favorite color";
$cookie_value = "yellow";
// setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/"); // 86400 = 1 day
$_COOKIE["{$cookie_name}"] = $cookie_value;
?>

<html>
<body>
<?php
if(isset($_COOKIE[$cookie_name])) {
  echo "Cookie '" . $cookie_name . "' is set!<br>";
  echo "Value is: " . $_COOKIE[$cookie_name];
} else {
  echo "Cookie named '" . $cookie_name . "' is not set!";
}

// setcookie($cookie_name, "kim", time() + (86400 * 30), "/"); // 86400 = 1 day
// session_destroy();

?>
</body>
</html>