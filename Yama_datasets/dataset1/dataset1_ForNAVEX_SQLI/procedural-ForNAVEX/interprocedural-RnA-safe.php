<?php
function safe()
{
    $a = 123;
    return $a;
}

function foo()
{
    $ret = safe();
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$ret';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
}
foo();
