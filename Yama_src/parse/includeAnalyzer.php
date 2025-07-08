<?php

class IncludeAnalyzer
{
    public $analyzedFiles = [];
    public $includeGraph = [];
    public $includedMapping = [];
    public $normalizedGraph = [];
    public $allChains = [];



    public function analyzeFile($currentfile, $includedfile, $lineNo)
    {
        if (in_array($includedfile, $this->analyzedFiles)) {
            return;
        }

        $this->analyzedFiles[] = $includedfile;
        $this->includedMapping[realpath($includedfile)] = $lineNo;

        $includedFlag = false;
        foreach ($this->includeGraph as $key => $value) {
            if($key == $currentfile){
                array_push($this->includeGraph[$currentfile], $includedfile);
                $includedFlag = true;
            }
        }

        if(!$includedFlag){
            $this->analyzedFiles[] = $currentfile;
            $this->includeGraph[$currentfile] = [$includedfile];
        }

        $this->includeGraph[$includedfile] = [];

    }


    public function normalizePaths()
    {
        foreach ($this->includeGraph as $file => $includes) {
            $normalizedFile = realpath($file);
            if ($normalizedFile === false) {
                continue;
            }
            $this->normalizedGraph[$normalizedFile] = [];
            foreach ($includes as $includedFile) {
                $normalizedIncludedFile = realpath($includedFile);
                if ($normalizedIncludedFile !== false) {
                    $this->normalizedGraph[$normalizedFile][] = $normalizedIncludedFile;
                }
            }
        }
    }    


    public function buildIncludeChains($includeGraph, $currentFile, $visitedFiles, $currentChain, &$allChains)
    {
        if (in_array($currentFile, $visitedFiles)) {
            return;
        }

        $currentChain[] = $currentFile;
        $visitedFiles[] = $currentFile;

        if (isset($includeGraph[$currentFile]) && !empty($includeGraph[$currentFile])) {
            foreach ($includeGraph[$currentFile] as $includedFile) {
                $this->buildIncludeChains($includeGraph, $includedFile, $visitedFiles, $currentChain, $allChains);
            }
        } else {
            $allChains[] = $currentChain;
        }
    }

    public function generateChains()
    {
        $this->normalizePaths();
        $allFiles = array_keys($this->normalizedGraph);
        $includedFiles = array_merge(...array_values($this->normalizedGraph));
        $startFiles = array_diff($allFiles, $includedFiles);

        if (empty($startFiles)) {
            $startFiles = $allFiles;
        }

        foreach ($startFiles as $startFile) {
            $this->buildIncludeChains($this->normalizedGraph, $startFile, [], [], $this->allChains);
        }
    }


    public function displayAllChains()
    {
        echo "All Include chains：\n\n";
        foreach ($this->allChains as $chain) {
            echo implode(" -> ", $chain) . "\n\n";
        }
    }


    public function displayFilteredChains($targetFile)
    {

        $filteredChains = $this->filterChainsByFile(realpath($targetFile));

        echo "The Includechain containing specified file path：\n\n";
        foreach ($filteredChains as $chain) {
            echo implode(" -> ", $chain) . "\n\n";
        }
    }

    function filterChainsByFile($targetFile)
    {
        $filteredChains = [];

        foreach ($this->allChains as $chain) {
            if (in_array($targetFile, $chain)) {
                $filteredChains[] = $chain;
            }
        }

        return $filteredChains;
    }


}
