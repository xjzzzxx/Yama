<?php
$path = $_POST['path'];
if($path){
    $filename = "vul.php";
}else{
    $filename = "safe.php";
}
include("../include_lib/".$filename);
