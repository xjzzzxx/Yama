<?php
// Path-sensitive   => Positive
// Flow-sensitive   => Positive
// Flow-insensitive => Positive
$x = 10;

if ($x > 0) {
    $y = $_POST['xss'];
}
echo $y;
