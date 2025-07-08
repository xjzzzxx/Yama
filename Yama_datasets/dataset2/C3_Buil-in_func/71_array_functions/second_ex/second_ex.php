<?php
$val = $_GET["p1"];
$input = array(12, 10, 9);

$result = array_pad($input, 5, $val);       // 第二个参数指定将数组扩展到长度5
echo $result[3];
