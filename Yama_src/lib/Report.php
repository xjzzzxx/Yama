<?php


class Report{
    public $vulInfo;
    public $vulStatistics;
    public $throwInfo;
    public $throwStatistics;
    
    public function __construct()
    {
        $this->vulInfo = [];
        $this->throwInfo = [];
        $this->vulStatistics = [
            "XSS" => [],
            "SQLI" => [],
            "CE" => [],
            "FI" => [],
            "AFD" => [],
            "UFU" => [],
            "SDE" => [],
            "POSSIBLE_CE" => [],
            "POSSIBLE_VUL" => [],
        ];
    }
    
    public function vulInfosave($filePath, $vulChain)
    {
        $this->vulInfo[] = ["filePath" => $filePath, "vulChain" => $vulChain];
    }

    public function throwInfosave($filePath, $throwMessage)
    {
        $this->throwInfo[] = ["filePath" => $filePath, "throwMessage" => $throwMessage];
    }


    public function reclassification(&$vulStatistics, $vulInfo)
    {
        foreach ($vulInfo as $vulItem) {
            $vulFilePath = $vulItem['filePath'];
            $vulChain = $vulItem['vulChain'];
            foreach ($vulChain as $chainItem) {
                $vulType = $chainItem[0];
                $vulItemChain = $chainItem[1];
                array_push($vulStatistics[$vulType], [$vulFilePath, $vulItemChain]);
            }
        }
    }

    public function markdownReport($lTime, $project, $vulStatistics, $aTime)
    {
        $reportDate = $lTime;
        $reportProject = $project;
        array_pop($vulStatistics);

        $reportContents = "";
        $vulDetailCount = 2;
        $reportVulTotal = 0; 
        $markdownTemplate = file_get_contents('./lib/report.md');

        foreach ($vulStatistics as $vulType => $vulData) {
            $seenChains = [];
            $filteredVuls = [];

            foreach ($vulData as $vulItem) {
                $filePath = $vulItem[0];
                $vulChains = $vulItem[1];

                if (is_array($vulChains)) {
                    $chainStr = implode("\n->", $vulChains);
                } else {
                    $chainStr = trim($vulChains);
                }

                $hash = md5($chainStr); 
                if (in_array($hash, $seenChains)) {
                    continue; 
                }

                $seenChains[] = $hash;
                $filteredVuls[] = [$filePath, $vulChains]; 
            }

            if (count($filteredVuls) > 0) {
                errorEcho("\t$vulType\tvulnerabilities : " . count($filteredVuls) . "\n");
                $markdownTemplate .= sprintf("%s: %s\n\n", $vulType, count($filteredVuls));

                $mdtitle = sprintf("# 0x0%d %s\n\n", $vulDetailCount, $vulType);
                $reportContents .= $mdtitle;

                foreach ($filteredVuls as $vulItem) {
                    $filePath = $vulItem[0];
                    $vulChains = $vulItem[1];

                    $mdfilePath = sprintf("FilePath:  [%s](%s)\n\n", $filePath, $filePath);

                    if (is_array($vulChains)) {
                        $chainStr = implode("\n->", $vulChains);
                    } else {
                        $chainStr = trim($vulChains);
                    }

                    $mdvulChains = sprintf("VulChains: \n```\n%s\n```\n\n", $chainStr);

                    $reportContents .= $mdfilePath;
                    $reportContents .= $mdvulChains;
                    $reportContents .= "***\n";
                }

                $vulDetailCount++;
            }

            $reportVulTotal += count($filteredVuls);
        }

        echo "[+] Yama discovered a total of ";
        errorEcho($reportVulTotal . " vulnerabilities");

        $placeholders = array(
            '{{reportDate}}' => $reportDate,
            '{{reportProject}}' => $reportProject,
            '{{reportVulTotal}}' => $reportVulTotal,
            '{{reportTime}}' => $aTime,
        );

        $markdownTemplate = str_replace(array_keys($placeholders), array_values($placeholders), $markdownTemplate);

        $reportContents = $markdownTemplate . $reportContents;
        return $reportContents;
    }

    public function md2pdf($mdPath, $pdfPath)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $pandocHelp = 'pandoc -h 2>nul'; 
        } else {
            $pandocHelp = 'pandoc -h 2>/dev/null'; 
        }
        $pandocLive = exec($pandocHelp, $outputArray, $returnVar);
        if($pandocLive){
            $pandoc_md2pdf = 'pandoc '.$mdPath.' -o '.$pdfPath;
            system($pandoc_md2pdf, $returnVar);
            if(!$returnVar){
                echo ("[+] Vulnerability_Report(pdf) are saved in: ");
                errorEcho(realpath($pdfPath) . "\n");
            }
        }else{
            warningEcho("[-] Pandoc not installed. We recommend using Pandoc to generate PDF versions of the report. \n");
        }
    }

    public function show($projectName, $savePath, $pdfReport, $aTime, $ifsave=true)
    {
        date_default_timezone_set('Asia/Shanghai');
        $date = new DateTime();
        $sTime = $date->format('Ymd');
        $lTime = $date->format("Y-m-d H:i:s");

        highlightEcho("\n[+] Summary of Analysis Results:\n");
        $throwInfo = print_r($this->throwInfo, true);
        $this->reclassification($this->vulStatistics, $this->vulInfo);
        $this->vulStatistics = $this->removeDuplicates($this->vulStatistics);

        $reportContents = $this->markdownReport($lTime, $projectName, $this->vulStatistics, $aTime);

        $vulData = print_r($this->vulInfo, true);
        $vulReportPath = $savePath . DIRECTORY_SEPARATOR . $sTime .'_'. $projectName . '_vulResult.txt';
        $vulReportMarkdownPath = $savePath . DIRECTORY_SEPARATOR . $sTime .'_'. $projectName . '_vulResult.md';
        $vulReporPdfPath = $savePath . DIRECTORY_SEPARATOR . $sTime .'_'. $projectName . '_vulResult.pdf';
        $errotReportPath = $savePath .DIRECTORY_SEPARATOR . $sTime . '_' . $projectName . '_errorReport.txt';

        if($ifsave){
            highlightEcho("\n[+] Analysis result saving!\n");
            file_put_contents($vulReportMarkdownPath, $reportContents);
            echo ("[+] Vulnerability_Report(markdown) are saved in: ");
            errorEcho(realpath($vulReportMarkdownPath) . "\n");
            if ($pdfReport) {
                $this->md2pdf($vulReportMarkdownPath, $vulReporPdfPath);
            }
            file_put_contents($errotReportPath, $throwInfo);
        }
        
        highlightEcho("\n[+] Analysis completed!\n");
        highlightEcho("See You♪");
    }

    public function removeDuplicates($vulStatistics) {
        $rDvulStatistics = [];
        $vulCount = 0;
        foreach ($vulStatistics as $vulType => $vulData){
            if(count($vulData)){
                $uniqueArr = [];
                $hashTable = [];
                foreach ($vulData as $vulItem) {
                    $key = serialize($vulItem);
                    if (!isset($hashTable[$key])) {
                        $uniqueArr[] = $vulItem;
                        $hashTable[$key] = true;
                        $vulCount++;
                    }
                }
                $rDvulStatistics[$vulType] = $uniqueArr;
            }
        }
        $rDvulStatistics["Sum"] = $vulCount;
        return $rDvulStatistics;
    }

}