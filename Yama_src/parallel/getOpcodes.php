<?php
use parallel\{Runtime, Future};

function get_file_extension_para($filedir)
{
    $filedir = explode('.', $filedir);
    return end($filedir);
}

function file_scan_para($dir)
{
    $FILETYPES = array(                        // filetypes to scan
        'php',
        'phps',
        'php4',
        'php5',
        'php7',
        'php8',
        'phtml'
    );
    $result = [];
    $temp = scandir($dir);
    foreach ($temp as $filename) {
        $tmp_file = $dir . '/' . $filename;
        if (is_dir($tmp_file)) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }
            $tmpArr = file_scan_para($tmp_file);
            foreach ($tmpArr as $value) {
                if (in_array(get_file_extension_para($value), $FILETYPES))
                    $result[] = $value;
            }
        } else if (in_array(get_file_extension_para($tmp_file), $FILETYPES)) {
            $file_path = str_replace('\\', '/', realpath($tmp_file));
            $result[] = $file_path;
        }

    }
    return $result;
}

function get_output_dir_para($filedir)
{
    $filedirinfo = pathinfo($filedir);
    $result[] = $filedirinfo['dirname'];
    $result[] = $filedirinfo['filename'];
    $result[] = $filedirinfo['dirname'] . '/' . $filedirinfo['filename'] . '.opcode';
    
    return $result;
}

function progress_bar($done, $total, $info = "", $width = 50, $currentFilePath = false, $showFlag = "VERBOSE")
{
    if ($currentFilePath) {
        echo "\033[A\033[K";
        echo "\033[A\033[K";
        echo "[$showFlag] Current Deal: $currentFilePath\n";
        $perc = round(($done * 100) / $total);
        $bar = round(($width * $perc) / 100);
        return sprintf("%s%%[%s>%s]%s\n", $perc, str_repeat("=", $bar), str_repeat(" ", $width - $bar), $info);
    } else {
        $perc = round(($done * 100) / $total);
        $bar = round(($width * $perc) / 100);
        return sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width - $bar), $info);
    }
}

function extractPara($filePathArray)
{
    $DealCount = 0;
    $runtimes = [];
    $futures = [];
    $FileCount = count($filePathArray);
    $MAX_CONCURRENT_TASKS = 100;
    // $thiscommand = "php -dvld.execute=0 -dvld.dump_paths=0 -dvld.active=1 -dvld.verbosity=3 -dvld.save_paths=1 -dvld.save_dir=";
    $thiscommand = "php -dvld.execute=0 -dvld.dump_paths=0 -dvld.active=1 -dvld.verbosity=3 ";
    foreach($filePathArray as $item){
        /**
         * $dirinfo[0] = Path
         * $dirinfo[1] = xxx
         * $dirinfo[2] = Path/xxx.opcode
         */
        while (count($futures) >= $MAX_CONCURRENT_TASKS) {
            foreach ($futures as $key => $future) {
                if ($future->done()) {
                    unset($futures[$key]);
                    break;
                }
            }
            usleep(100000); // 100ms
        }
        $dirinfo = get_output_dir_para($item);
        $cfgOldPath = $dirinfo[0] . DIRECTORY_SEPARATOR . "paths.dot";
        $cfgNewPath = $dirinfo[0] . DIRECTORY_SEPARATOR . $dirinfo[1] . ".dot";

        $command = $thiscommand . $dirinfo[0] . " ";
        // $getOpAndCfg = $command . $item . " > " . $dirinfo[2] . " 2>&1 ";
        $getOpAndCfg = $thiscommand . $item . " > " . $dirinfo[2] . " 2>&1 ";

        $DealCount++;
        echo progress_bar($DealCount, $FileCount, $info = " " . (string)$DealCount . " file(s) processed.", $width = 50, $item, "OpcodesExtract");
        $runtime = new Runtime();
        $runtimes[] = $runtime;
        $futures[] = $runtime->run(function() use ($getOpAndCfg, $cfgOldPath, $cfgNewPath){
            @system($getOpAndCfg);
            // rename($cfgOldPath, $cfgNewPath);
        });
    }
    foreach ($futures as $future) {
        $future->value();
    }
}


$options = array(
    't::'   => 'target::',
);
$args = @getopt(implode('', array_keys($options)), array_values($options));
$targetProject = $args['t'] ?? ($args['target'] ?? $defaultTarget);

$FileNameArray = file_scan_para($targetProject);
extractPara($FileNameArray);