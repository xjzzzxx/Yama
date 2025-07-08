<?php
$b = $_GET["p1"];
$util = (new class {
    public function log($msg)
    {
        echo $msg;
    }
});

$util2 = (new class
{
    public function log2($msg)
    {
        echo $msg;
    }
});

// will print the input $b, XSS vulnerability
$util->log($b);
$util2->log2($b);
