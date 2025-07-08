<?php
  class A {
      public static $one=1;

      public function __construct($b){
        self::$one = $b;
      }
    
      static function show_one() {
          // echo $this->one;
          echo self::$one;
      }
  }
  
$b = $_GET["p1"];
$a = new A($b);
$a::show_one();
