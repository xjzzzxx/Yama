<?php
interface Number
{
    public function get();
    public function output();
}

class One implements Number
{
    public function get()
    {
        return 1;
    }
    public function output()
    {
        $x = $_POST['string'];
        echo $x;
    }
}

class Two implements Number
{
    public function get()
    {
        return 2;
    }
    public function output()
    {
        $x = 123;
        echo $x;
    }
}

function id(Number $n)
{
    return $n;
}


$n1 = new One();
$n2 = new Two();
$x = id($n1);
$y = id($n2);
