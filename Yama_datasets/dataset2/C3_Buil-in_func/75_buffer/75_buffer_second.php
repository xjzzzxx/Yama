<?php
$a = $_GET["p1"];
ob_start();
echo $a;
ob_end_flush();