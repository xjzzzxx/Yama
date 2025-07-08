<?php
class TestClass
{
    public $foo;
    public $doo = 'safe';
    public function __construct($foo){
        $this->foo = $foo;
    }

    public function __toString(){
        echo 'XSS: ' . $this->foo;
        return $this->foo;
    }
}

// will call the function __toString(), XSS vulnerability
function F(string $c){
    echo 'message';
}

$b = $_GET["p1"];
// $b = 'test';
$c = new TestClass($b);
$a = (string)$c;        //__toString
F($c);                  //__toString

