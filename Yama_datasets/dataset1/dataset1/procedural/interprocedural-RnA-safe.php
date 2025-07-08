<?php
function safe()
{
    $a = 123;
    return $a;
}

function foo()
{
    $ret = safe();
    echo $ret;
}
foo();
