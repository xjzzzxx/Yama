<?php
// Context-sensitive    => Positive
// Context-insensitive  => Negative or two Positive
function vul($a)
{
    $y = 123;
    if($a>5){
        $y = $_POST['xss'];
        echo $y;
    }else{
        echo $y;
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