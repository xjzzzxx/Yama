<?php
function safe($x)
{
    $a = 123;
    return $a;
}

function foo()
{
    $x = $_REQUEST['string'];
    $ret = safe($x);
    echo $ret;
}
foo();