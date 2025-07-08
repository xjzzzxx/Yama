<?php
class A {
    public static function who($b) {
        echo "safe";
    }
    public static function test($b) {
        static::who($b); 
    }
}

class B extends A {
    public static function who($b) {
        echo $b;
    }
}

$b = $_GET["p1"];
A::test($b); 