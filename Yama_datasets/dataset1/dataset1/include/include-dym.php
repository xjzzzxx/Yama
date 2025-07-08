<?php
$path = $_POST['path'];
if($path){
    $filename = "vulDym.php";
}else{
    $filename = "safe.php";
}
include("../include_lib/".$filename);
