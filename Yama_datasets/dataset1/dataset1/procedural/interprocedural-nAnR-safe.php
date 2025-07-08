<?php
function safe()
{
    $a = 123;
    echo $a;
}

function foo()
{
    safe();
}
foo();
