<?php
// Path-sensitive   => Negative
// Flow-sensitive   => Positive
// Flow-insensitive => Positive
$x = 10;
$y = 0;
for ($i=0; $i < 10; $i++) { 
    if($i == 20){
        $z = $_POST['xss'];
        echo $z;
    }
}
