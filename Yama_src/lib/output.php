<?php
function YamaWelcome()
{
    $r = mt_rand(0,255);
    $b = mt_rand(0,255);
    $g = mt_rand(0,255);
    $rbgESC = "\x1b[38;2;$r;$b;$g"."m";
    echo chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J';
    echo <<< WELCOME
$rbgESC
__     __                   
\ \   / /                   
 \ \_/ /_ _ _ __ ___   __ _ 
  \   / _` | '_ ` _ \ / _` |
   | | (_| | | | | | | (_| |
   |_|\__,_|_| |_| |_|\__,_|
Version: 1.0

\x1b[0m
WELCOME;
}
function showHelp()
{
    // echo chr(27) . chr(91) . 'H' . chr(27) . chr(91) . 'J';
    warningEcho("Usage: php yama.php -t=<target> [-o=<output_file>] [-c=<1 or 0>] [-e=<1 or 0>] [-d=<1 or 0>] [-v=<1 or 0>]\n");
    echo "Options:\n";
    echo "  -t, --target            The path of the app you want to test \n";
    echo "  -o, --output            Output file Path (default: ./result.html)\n";
    echo "  -c, --cfgsave           Whether save cfg.dot for each file(default: false)\n";
    echo "  -e, --extractopcode     Whether execute opcodes Extraction(default: true). If it has already been extracted, you can set false to improve the running speed\n";
    echo "  -d, --debugmodel        Whether display debug information(default: false). Debugmodel will output a large amount of information during the execution of Yama. If debugging is required, it can be set to True\n";
    echo "  -v, --verbose           Whether display analysis progress(default: false).\n";
    echo "  -p, --pdf               Whether generate PDF report(need Pandoc, default: false).\n";
    echo "  -h, --help              Show help\n\n";
}



function warningEcho($string)
{
    echo "\x1b[38;2;255;255;102m$string\x1b[0m";
}

function errorEcho($string)
{
    echo "\x1b[38;2;255;102;102m$string\x1b[0m";
}

function highlightEcho($string)
{
    echo "\x1b[38;2;144;238;144m$string\x1b[0m";  
}

function normalEcho($string)
{
    echo "\x1b[38;2;204;204;204m$string\x1b[0m";
}


function debugEcho($string, $debug=False, $style="default")
{
    if($debug){
        switch ($style) {
            case 'default':
                echo $string;
                break;
            case 'error':
            case 'red':
                errorEcho($string);
                break;
            case 'warning':
            case 'yellow':
                warningEcho($string);
                break;
            case 'highlight':
            case 'green':
                highlightEcho($string);
                break;
            default:
                echo $string;
                break;
        }       
    }
}


function progress_bar($done, $total, $info = "", $width = 50, $currentFilePath = false, $showFlag = "VERBOSE")
{
    if($currentFilePath){
        echo "\033[A\033[K";
        echo "\033[A\033[K";
        echo "[$showFlag] Current Deal: $currentFilePath\n";
        $perc = round(($done * 100) / $total);
        $bar = round(($width * $perc) / 100);
        return sprintf("%s%%[%s>%s]%s\n", $perc, str_repeat("=", $bar), str_repeat(" ", $width - $bar), $info);
    }else{
        $perc = round(($done * 100) / $total);
        $bar = round(($width * $perc) / 100);
        return sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width - $bar), $info);
    }

}