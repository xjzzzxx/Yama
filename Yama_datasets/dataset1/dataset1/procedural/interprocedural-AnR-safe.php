<?php
function safe($x)
{
    $a = 123;
    echo $a;
}

function foo()
{
    $x = $_REQUEST['string'];
    safe($x);
}
foo();