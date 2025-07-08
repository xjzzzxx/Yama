<?php
// Path-sensitive   => Negative
// Flow-sensitive   => Positive
// Flow-insensitive => Positive
$x = 10;
$y = 0;
if ($x > 0) {
    $y = $x + 1;
} else {
    $z = $_POST['xss'];
    echo $z;
}