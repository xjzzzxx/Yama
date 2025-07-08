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
        $id = $_REQUEST['id'];
        $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
        $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
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
        $id = 1;
        $query  = "SELECT first_name, last_name FROM users WHERE user_id = '$id';";
        $result = mysqli_query($GLOBALS["___mysqli_ston"],  $query);
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