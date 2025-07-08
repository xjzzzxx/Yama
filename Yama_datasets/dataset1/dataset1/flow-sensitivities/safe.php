<?php
// Path-sensitive   => Negative
// Flow-sensitive   => Negative
// Flow-insensitive => Negative
$x = 10;

if ($x > 0) {
    $y = $_POST['xss'];
    $y = 0;
}
echo $y;
