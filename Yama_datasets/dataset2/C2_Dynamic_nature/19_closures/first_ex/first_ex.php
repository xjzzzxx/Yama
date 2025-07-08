<?php
$greet = function($name)
{
    printf("Hello %s\r\n", $name);
    // echo $name;
};
$b = $_GET["p1"];
$greet($b);

