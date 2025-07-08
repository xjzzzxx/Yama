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
    echo $ret;
}
foo();