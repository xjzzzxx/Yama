<?php
$b = $_GET["p1"];
//"first=value&arr[]=foo+bar&arr[]=baz"
$str = $b;
// $str = "first=value&arr[]=foo+bar&arr[]=baz";
parse_str($str, $output);
echo $output['first'];  // value
echo $output['arr'][0]; // foo bar
echo $output['arr'][1]; // baz
