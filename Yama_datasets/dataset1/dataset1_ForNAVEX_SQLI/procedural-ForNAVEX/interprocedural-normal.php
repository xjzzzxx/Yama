<?php
function vul($x)
{
    $a = $x;
    return $a;
}

function foo()
{
    $x = $_REQUEST['string'];
    $ret = vul($x);
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$ret';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
}
foo();