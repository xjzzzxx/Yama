<?php
// 路径敏感 Negative
// 流敏感 Positive
// 流不敏感 Positive
$x = 10;
$y = 0;
for ($i = 0; $i < 10; $i++) {
    if ($i == 20) {
        $id = $_REQUEST['id'];
        $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
        $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
    }
}
