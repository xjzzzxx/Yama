<?php
$b = $_GET["p1"];

trait SayWorld {
    public function sayHello($b) {
        echo $b;
    }
}

class MyHelloWorld{
    use SayWorld;
    public function helloworld($a){
        echo $a;
    }
}

$o = new MyHelloWorld();
// will call the function sayHello() and print $b
$o->sayHello($b);

