<?php


class TaintPropagationParser
{
    public $data;
    public $taintFileChain;

    public function __construct()
    {
        $this->taintFileChain = [];
    }


    public function loadFromFile($filePath)
    {
        if (file_exists($filePath)) {
            $serializedData = file_get_contents($filePath);
            $this->data = unserialize($serializedData);
            if ($this->data === false) {
                exit("Desialization failed, please check the data format.\n");
            }
        } else {
            exit("The serialized file does not exist, unable to read data.\n");
        }
    }


    public function parseTaintPropagation()
    {
        if (empty($this->data)) {
            echo "No data loaded, unable to resolve taint propagation.\n";
            return;
        }

        $uniqueEntries = [];  
        foreach ($this->data->vulStatistics as $key => $vulEntity) {
            if ($key != 'Sum') {
                foreach ($vulEntity as $entry) {
                    $filepath = $entry[0];
                    $lineEntries = $entry[1];
                    $hasContent = false;  
                    $fileChain = [];
                    
                    foreach ($lineEntries as $lineEntry) {
                        preg_match('/\[(.*?)\].*?_Line_(\d+)/', $lineEntry, $matches);

                        if ($matches) {
                            $extractedFilePath = $matches[1];
                            $lineNumber = $matches[2];
                            $uniqueKey = $extractedFilePath . "_Line_" . $lineNumber;

                            if (!in_array($uniqueKey, $uniqueEntries)) {
                                $uniqueEntries[] = $uniqueKey;
                                if (!$hasContent) {
                                    $hasContent = true;  
                                }

                                $fileChain[] = [
                                    "Filepath" => $extractedFilePath,
                                    "Line" => $lineNumber,
                                ];

                            }
                        }
                    }

                    if(!empty($fileChain)){
                        $this->taintFileChain[] = $fileChain;
                    }

                }
            }
        }
    }


    public function displayProcessedData()
    {
        $chainCount = 1;
        foreach ($this->taintFileChain as $chain) {
            echo "Chain" . $chainCount . ":\n";
            foreach ($chain as $entry) {
                echo "  File path: " . $entry['filepath'] . "\n";
                echo "  Line: " . $entry['Line'] . "\n";
            }
            echo "\n";
            $chainCount++;
        }
    }

}