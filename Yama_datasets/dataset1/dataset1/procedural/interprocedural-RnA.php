<?php
function vul()
{
    $a = $_REQUEST['string'];
    return $a;
}

function foo()
{
    $ret = vul();
    echo $ret;
}
foo();
