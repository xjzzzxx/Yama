<?php
// 上下文敏感 Positive
// 上下文不敏感 Negative or two Positive
function vul($a)
{
    if($a>5){
        $id = $_POST['xss'];
        $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
        $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
    }else{
        $id = 123;
        $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
        $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
    }
}

function foo1()
{
    $x = 2;
    vul($x);
}

function foo2()
{
    $x = 7;
    vul($x);
}

foo1();
foo2();