<?php
################  INCLUDES  ################  

include_once("config/config.php");

include_once("lib/output.php");
include_once("lib/FileOperator.php");
include_once("lib/Report.php");

include_once("parse/opcodeParse.php");
include_once("parse/includeAnalyzer.php");
require_once  './vendor/autoload.php';

################  CONFIG  ################
$defaultTarget = './exampleCms';
$defaultOutput = './result';

YamaWelcome();

$options = array(
    't::'   => 'target::',
    'o::'   => 'output::',
    'c::'   => 'cfgsave::',
    'e::'   => 'extractopcode::',
    'd::'   => 'debugmodel::',
    'v::'   => 'verbose::',
    'p::'   => 'pdf::',
    'h::'   => 'help::',
    's::'   => 'ifsave::',
);

$args = @getopt(implode('', array_keys($options)), array_values($options));
$targetProject = $args['t'] ?? ($args['target'] ?? $defaultTarget);
$outputPath = $args['o'] ?? ($args['output'] ?? $defaultOutput);
$cfgsave = $args['c'] ?? ($args['cfgsave'] ?? false);
$opcodeExtractFlag = $args['e'] ?? ($args['extractopcode'] ?? false);
$DebugFlag = $args['d'] ?? ($args['debugmodel'] ?? false);
$verbose = $args['v'] ?? ($args['verbose'] ?? false);
$pdfReport = $args['p'] ?? ($args['pdf'] ?? false);
$help = $args['h'] ?? ($args['help'] ?? true);
$ifsave = $args['s'] ?? ($args['ifsave'] ?? true);

if (!$help) {
    showHelp();
    return;
} else if($targetProject == $defaultTarget){
    showHelp();
    warningEcho("[-] The user did not specify a target app, Yama entered demonstration mode.(use -t to specify)\n");
}


#################  FileScan  #################  
// If multithreading
$parallelFlag = true;
// $parallelFlag = false;

if (!is_dir($targetProject)) {
    errorEcho("[-] The path of the app ($defaultTarget) does not exist.\n");
    return;
}
echo "[+] Target apps: $targetProject \n";
echo "[+] Collecting files information for target apps... \n";
$File = new FileOperator();
$FileNameArray = $File->file_scan($targetProject);
$FileCount = count($FileNameArray);
$DealCount = 0;

#################  MAIN  #################
$reportObject = new Report();
$opcodeParseObj = new OpcodeParse();
$includeFileChain = new IncludeAnalyzer();

// 1. OPCODE extraction
if ($opcodeExtractFlag) {
    if ($parallelFlag) {
        highlightEcho("[+] Start extracting Opcodes!\n");
        $startTime = microtime(true);
        #### Please replace the PHP here with your parallel supported PHP version for starting multi-threaded opcode extraction ####
        @system('E:\env\wamp\bin\php\php7.4.0\php.exe .\parallel\getOpcodes.php -t=' . $targetProject);                         
        $endTime = microtime(true);
        echo "[+] Opcodes extraction is complete!\n";
        highlightEcho("[+] The time spent on Opcodes extraction: " . round($endTime - $startTime, 3) . " seconds!\n");
    }else{
        highlightEcho("[+] Start extracting Opcodes!\n");
        $startTime = microtime(true);
        $opcodeParseObj->extract($FileNameArray);
        $endTime = microtime(true);
        echo "[+] Opcodes extraction is complete!\n";
        highlightEcho("[+] The time spent on Opcodes extraction: " . round($endTime - $startTime, 3) . " seconds!\n");
    }
} else {
    warningEcho("[-] OpcodeExtractFlag: False, decided not to extract Opcodes!(use -e=true to extract if you need)\n");
}

// 2.OPCODE analysis
if ($verbose) {
    highlightEcho("[+] Start Analysis!\n");
}else{
    highlightEcho("[+] Start Analysis!\n\n\n");
}
$analysisStartTime = microtime(true);
foreach ($FileNameArray as $phpfilePath) {
    $DealCount++;
    if ($verbose){
        echo progress_bar($DealCount, $FileCount, $info = " " . (string)$DealCount . " file(s) processed.", $width = 50, $phpfilePath);
    }else{
        echo progress_bar($DealCount, $FileCount, $info = " " . (string)$DealCount . " file(s) processed.", $width = 50);
    }

    $opcodefilePath = $File->get_opcode_path($phpfilePath);
    $seriliazefilePath = $File->get_seriliaze_path($phpfilePath);
    
    try {
        $fileObject = $opcodeParseObj->parse($opcodefilePath, $phpfilePath, $DebugFlag);
        @$opcodeParseObj->cfgBuild($fileObject, $cfgsave);

        @$fileObject->funcOpArray['main']->funcCallState = $opcodeParseObj->dataFlowAnalysis(NULL, $fileObject->funcOpArray['main'], $fileObject, $fileObject->includedFileObject, $includeFileChain, $DebugFlag);
    } catch (Throwable $th) {
        $reportObject->throwInfosave($phpfilePath, $th->getMessage());
    } 

    if (!empty($fileObject->funcOpArray['main']->funcCallState->vulChains)) {
        $reportObject->vulInfosave($phpfilePath, $fileObject->funcOpArray['main']->funcCallState->vulChains);
    }
    file_put_contents($seriliazefilePath, serialize($fileObject));
}

#################  Report output  #################  
$analysisEndTime = microtime(true);
highlightEcho("\n[+] The time spent on analysis: " . round($analysisEndTime - $analysisStartTime, 3) . " seconds!\n");

if ($outputPath == $defaultOutput) {
    warningEcho("[-] The user did not specify outputPath, Yama use default path: $defaultOutput (use -o to specify)\n");
}
$projectName = pathinfo($targetProject)['basename'];
$reportObject->show($projectName, $outputPath, $pdfReport, round($analysisEndTime - $analysisStartTime, 3), $ifsave);

$seriliazeReportPath = $outputPath .DIRECTORY_SEPARATOR . date("Ymd") . '_' . $projectName . '.ser';
$serializedData = ["INC" => $includeFileChain, "REP" => $reportObject];
file_put_contents($seriliazeReportPath, serialize($serializedData));