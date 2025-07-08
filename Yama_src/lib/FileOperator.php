<?php

class FileOperator{
    public $dir;
    public function __construct()
    {
        $this->dir = "";
    }
    

    public function file_scan($dir)
    {
        $result = [];
        $temp = scandir($dir);
        foreach ($temp as $filename) {
            $tmp_file = $dir . '/' . $filename;
            if (is_dir($tmp_file)) {
                if ($filename == '.' || $filename == '..') {
                    continue;
                }
                $tmpArr = $this->file_scan($tmp_file);
                foreach ($tmpArr as $value) {
                    if (in_array($this->get_file_extension($value), $GLOBALS['FILETYPES']))
                        $result[] = $value;
                }
            } else if (in_array($this->get_file_extension($tmp_file), $GLOBALS['FILETYPES'])) {
                $this->get_file_extension($tmp_file);
                $file_path = str_replace('\\', '/', realpath($tmp_file));
                $result[] = $file_path;
            }
        }
        return $result;
    }


    public function get_file_extension($filedir)
    {
        $filedir = explode('.', $filedir);
        return end($filedir);
    }


    public function get_opcode_path($phpfilepath)
    {
        $fileInfo = pathinfo($phpfilepath);
        return $fileInfo["dirname"] . '/' . $fileInfo['filename'] . ".opcode";
    }


    public function get_seriliaze_path($phpfilepath)
    {
        $fileInfo = pathinfo($phpfilepath);
        return $fileInfo["dirname"] . '/' . $fileInfo['filename'] . ".serialization";
    }

}