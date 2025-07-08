<?php
function vul()
{
    $a = $_REQUEST['string'];
    $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$a';";
    $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
}

function foo()
{
    vul();
}
foo();
