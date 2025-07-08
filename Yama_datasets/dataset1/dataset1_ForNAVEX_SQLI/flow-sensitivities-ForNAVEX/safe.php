<?php
// 路径敏感 Negative
// 流敏感 Negative
// 流不敏感 Negative
$x = 10;
if ($x > 0) {
    $id = $_REQUEST[ 'id' ];
    $id = 0;
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
}
$result = mysqli_query($GLOBALS["___mysqli_ston"],  $query );
