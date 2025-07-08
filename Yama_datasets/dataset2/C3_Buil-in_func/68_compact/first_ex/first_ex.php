<?php
$a = $_GET["p1"];
$city  = "San Francisco";
$state = "CA";
$event = $a;

$location_vars = array("city", "state");
$result = compact("event", $location_vars);  
echo $result['event'];
