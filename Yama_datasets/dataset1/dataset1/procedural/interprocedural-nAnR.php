<?php
function vul()
{
    $a = $_REQUEST['string'];
    echo $a;
}

function foo()
{
    vul();
}
foo();
