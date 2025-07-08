<?php
// Path-sensitive   => Positive
// Flow-sensitive   => Positive
// Flow-insensitive => Negative
$x = 10;

if ($x > 0) {
    $y = $_POST['xss'];
} else {
    $y = 123;
}
echo $y;
