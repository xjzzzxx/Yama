<?php
function sum($a) {
    // it will print all the parameters
    // XSS vulnerability with the last element $b
    $x = func_num_args();
    echo $x;
    echo $a;
    foreach (func_get_args() as $n) {
        echo $n;
    }
}

$b = $_GET["p1"];
// $b = 4;
sum(1, 2, 3, $b);

