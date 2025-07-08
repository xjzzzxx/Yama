<?php
function vul($x)
{
    $a = $x;
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$a';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
}

function foo()
{
    $x = $_REQUEST['string'];
    vul($x);
}
foo();
