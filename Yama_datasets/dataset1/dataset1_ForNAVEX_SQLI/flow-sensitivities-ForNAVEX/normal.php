<?php
// 路径敏感 Positive
// 流敏感 Positive
// 流不敏感 Positive
$x = 10;
if ($x > 0) {
    $id = $_REQUEST['id'];
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
}
$result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
