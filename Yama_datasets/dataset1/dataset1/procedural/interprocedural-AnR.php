<?php
function vul($x)
{
    echo $x;
}

function foo()
{
    $x = $_REQUEST['string'];
    vul($x);
}
foo();