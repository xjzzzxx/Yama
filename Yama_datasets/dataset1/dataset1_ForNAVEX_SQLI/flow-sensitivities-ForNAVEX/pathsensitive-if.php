<?php
// 路径敏感 Negative
// 流敏感 Positive
// 流不敏感 Positive
$x = 10;
$y = 0;
if ($x > 0) {
    $y = $x + 1;
} else {
    $id = $_REQUEST[ 'id' ];
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query );
}
