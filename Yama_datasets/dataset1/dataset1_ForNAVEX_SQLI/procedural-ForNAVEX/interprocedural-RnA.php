<?php
function vul()
{
    $a = $_REQUEST['string'];
    return $a;
}

function foo()
{
    $ret = vul();
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$ret';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
}
foo();
