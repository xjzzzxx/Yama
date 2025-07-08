<?php
$a = "abc";
$b = $_GET["p1"];
$x = array(1, 2, $a => $b);
echo $x["ttt"];