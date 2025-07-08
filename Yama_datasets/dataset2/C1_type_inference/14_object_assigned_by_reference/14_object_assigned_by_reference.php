<?php
    class myclass{
	    public $prop;
    };
    $x = "safe";
    $obj = "abc";
    $obj = new myclass();       // There is a syntax error in the source code, this sentence needs to be added
    $obj->prop = &$x;
    $x = $_GET["p1"];
    echo $obj->prop;
