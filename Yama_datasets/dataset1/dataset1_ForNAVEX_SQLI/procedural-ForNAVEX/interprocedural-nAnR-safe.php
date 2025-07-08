<?php
function safe()
{
    $a = 123;
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$a';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
}

function foo()
{
    safe();
}
foo();
