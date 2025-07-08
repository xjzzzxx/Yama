<?php
require_once("./config/config.php");
require_once("./lib/constructer.php");
require_once("./lib/output.php");
require_once("./config/config.php");

global $INTERNALFUNCLIST;


class OpcodeParse{
    public $command;
    private $TAINTSOURCE = ["_GET", "_POST", "_REQUEST",  "_COOKIE", "_FILES"];
    private $FETCHISSOURCE = ["_GET", "_POST", "_REQUEST",  "_COOKIE", "_FILES", "_SESSION"];
    
    function __construct()
    {
        $this->command = "php -dvld.execute=0 -dvld.dump_paths=0 -dvld.active=1 -dvld.verbosity=3 -dvld.save_paths=1 -dvld.save_dir=";
    }


    public function extract($filePathArray)
    {
        $DealCount = 0;
        $FileCount = count($filePathArray);
        foreach($filePathArray as $item){

            $dirinfo = $this->get_output_dir($item);
            $cfgOldPath = $dirinfo[0] . DIRECTORY_SEPARATOR . "paths.dot";
            $cfgNewPath = $dirinfo[0] . DIRECTORY_SEPARATOR . $dirinfo[1] . ".dot";

            $command = $this->command . $dirinfo[0] . " ";
            $getOpAndCfg = $command . $item . " > " . $dirinfo[2] . " 2>&1 ";

            $DealCount++;
            echo progress_bar($DealCount, $FileCount, $info = " " . (string)$DealCount . " file(s) processed.", $width = 50, $item, "OpcodesExtract");
            
            @system($getOpAndCfg);
            rename($cfgOldPath, $cfgNewPath);
        }
    }


    public function opcodeParse(&$funcObject, $currentContent, $CurrentNo = 0)
    {
        $patternOP1 = "/OP1\[([^\[]*)\]/";
        $patternOP2 = "/OP2\[(.*?)\]/";
        $patternRES = "/RES\[([^\[]*)\]/";
        $patternEXT = "/EXT_JMP.*\[([^\[]*)\]/";

        $patternJMP = "/JMP(.*)/";

        $half = substr($currentContent, 0, 50);
        if (preg_match($patternOP1, $currentContent, $match)) {
            $OP1 = trim($match[1], ' ,');
            $OP1 = explode(" ", $OP1);
            $op1_type = $OP1[0];
            if ($op1_type == "IS_UNUSED") {
                $op1_value = '';
            } else {
                $op1_value = $OP1[count($OP1) - 1];
                $op1_value = trim($op1_value, '\'');
            }
        } else {
            $op1_type = '';
            $op1_value = '';
        }

        if (preg_match($patternOP2, $currentContent, $match)) {
            $OP2 = trim($match[1], ' ,');
            $OP2 = explode(" ", $OP2);
            $op2_type = $OP2[0];
            if ($op2_type == "IS_UNUSED") {
                $op2_value = '';
            }else if($op2_type == "["){
                array_shift($OP2);
                $op2_value = implode('',$OP2);
            }else {
                $op2_value = $OP2[count($OP2) - 1];
                $op2_value = trim($op2_value, '\'');
            }
        } else {
            $op2_type = '';
            $op2_value = '';
        }

        switch ($op1_value) {
            case '<true>':
                $op1_value = true;
                break;
            case '<false>':
                $op1_value = false;
                break;            
            default:
                break;
        }
        switch ($op2_value) {
            case '<true>':
                $op2_value = true;
                break;
            case '<false>':
                $op2_value = false;
                break;            
            default:
                break;
        }



        if (preg_match($patternRES, $currentContent, $match)) {
            $RES = trim($match[1], ' ,');
            $RES = explode(" ", $RES);
            $res_type = $RES[0];
            if ($res_type == "IS_UNUSED") {
                $res_value = '';
            } else {
                $res_value = $RES[count($RES) - 1];
                $res_value = trim($res_value,'\'');
            }
        } else {
            $res_type = '';
            $res_value = '';
        }


        $lineNo = trim(substr($half, 0, 5));
        $opcodeNo = trim(substr($half, 20, 3));
        $opcodeName = trim(substr($half, 25));
        $fetch_flag = trim(substr($currentContent, 54, 6));
        $extended_value = trim(substr($currentContent, 70, 2));
        $jmp_ext = "";
        if ($lineNo) {
            $CurrentNo = $lineNo;
        }


        $current_oplineNo = count($funcObject->opArray);
        $jumpInfo = &$funcObject->jumpInfo;

        $fastcallRetAddr = 0;

        switch ($opcodeNo) {
            case ZEND_JMP:
                $target_oplineNo = (int)trim($op1_value, '->');                        
                array_push($funcObject->jmpNode, $target_oplineNo);
                array_push($funcObject->entryNode, $target_oplineNo);
                $jumpInfo[] = array($current_oplineNo, $target_oplineNo);
                if($opcodeNo == ZEND_FAST_CALL){
                    $fastcallRetAddr = $current_oplineNo + 1;
                }
                break;             

            case ZEND_JMPZ:
            case ZEND_JMPNZ:
            case ZEND_JMPZ_EX:
            case ZEND_JMPNZ_EX:
            case ZEND_FE_RESET_R:
            case ZEND_FE_RESET_RW:
            case ZEND_JMP_SET:
            case ZEND_COALESCE:
            case ZEND_ASSERT_CHECK:
            case ZEND_JMP_NULL:
                $target_oplineNo = (int)trim($op2_value, '->');                        
                array_push($funcObject->entryNode, $current_oplineNo + 1);             
                array_push($funcObject->entryNode, $target_oplineNo);                  
                $jumpInfo[] = array($current_oplineNo, $target_oplineNo);
                break;

            case ZEND_FE_FETCH_R:
            case ZEND_FE_FETCH_RW:
                preg_match($patternEXT, $currentContent, $match);
                $EXT = trim($match[1], ' ,');
                $EXT_ADR = (int)trim($EXT, '->');
                $current_oplineNo = count($funcObject->opArray);
                array_push($funcObject->entryNode, $current_oplineNo + 1);                  
                array_push($funcObject->entryNode, $EXT_ADR);                               
                $jumpInfo[] = array($current_oplineNo, $EXT_ADR);
                $jmp_ext = $EXT;
                break;
            
            case ZEND_SWITCH_STRING:
                preg_match($patternEXT, $currentContent, $match);
                $EXT = trim($match[1], ' ,');
                $EXT_ADR = (int)trim($EXT, '->');
                $jmp_ext = $EXT_ADR;
                break;

            
            case ZEND_RETURN:
                break;
            
            case ZEND_EXIT:
                break;

            case ZEND_INIT_FCALL:
                $callee = "Internal::" . $op2_value;
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;
            case ZEND_INIT_FCALL_BY_NAME:
                $callee = "External::" . $op2_value;
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;
            case ZEND_INIT_USER_CALL:
                $callee = "External::" . $op2_value;
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;                
            case ZEND_NEW:
                $callee = "Class::" . $op1_value . "::__construct";
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;
            case ZEND_INIT_METHOD_CALL:
                $callee = "##Method##::" . $op2_value;
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;
            case ZEND_INIT_STATIC_METHOD_CALL:
                $callee = "Static_Method::" . $op1_value . "::" .  $op2_value;
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;
            case ZEND_INIT_DYNAMIC_CALL:
                $callee = "Dynamic_Call::" . $op2_value;    
                $funcObject->cgInfo[] = new CallNode($funcObject->funcName, $callee, $current_oplineNo);
                break;


            default:
                break;
        }


        $opcodeObject = new Opcode($opcodeNo, $opcodeName, $CurrentNo, $op1_value, $op1_type, $op2_value, $op2_type, $res_value, $res_type, $extended_value, $fetch_flag, $jmp_ext);
        $funcObject->opArray[] = $opcodeObject;
        return $CurrentNo;
    }


    public function parse($opcodefilename, $phpfilename, $debugFlag = False)
    {
        $file = new SplFileObject($opcodefilename);
        $fileObject = new FileInfo('');
        $fileObject->phpFilePath = $phpfilename;

        $funcName = "";
        $scopeClassName = "";
        $className = "";
        $parentClassName = "";
        $classTraitName = [];
        $propertiesArray = array();
        $staticPropertyArray = array();
        $CurrentNo = 0;

        $patternfilename = "/filename:(.*)/";
        $patternshortfuncName = "/function name:(.*)/";
        $patternNumOps = "/number of ops:(.*)/";
        $patternCvVar = "/compiled vars:  (.*)/";
        $patternMain = "function name:  (null)";
        $patternProperties = "Start Properties extract:";
        $patternFunction = "/Function (.*):/";
        $patternFuncRetType = "/\[\*\]Function return type:(.*)/";
        $patternClass = "/Class (.*):/";
        $patternClassTrait = "/\[\+\]ClassTrait ClassName: (.*)/";
        $patternParentClass = "/\[\+\]Class Parent Name: (.*)/";
        $patternFunctionScope = "/\[\+\]Function Source Class Name: (.*)/";
        $patternClassEnd = "/End of class (.*)/";

        $patternArgStart = "\[\*\]Start of arg_info extract.";
        $patternArgInfoNo = "/\[\*\]ArgInfoNo\*\*(.*)\*\*/";
        $patternArgName = "/\[\*\]name:(.*)/";
        $patternArgType = "/\[\*\]type:(.*)/";
        $patternArgIsRef = "/\[\*\]is_ref:(.*)/";
        $patternArgIsVariadic = "/\[\*\]is_variadic:(.*)/";
        $patternArgEnd = "[*]End of arg_info extract.";

        $patternStaticStart = "[*]Start static_variables extract:";
        $patternArrayStart = "[+]Start ARRAY extract:";
        $patternArrayEnd = "[+]End ARRAY extract.";
        $patternStaticVar = "/\[\*\]Key: (.*), Value: (.*), Type: (.*)/";
        $patternArraySubStart = "[+]Start SUBARRAY extract:";
        $patternArraySubEnd = "[+]End SUBARRAY extract.";
        $patternStaticSubVar = "/\[\*\]SUBARRAY Key: (.*), Value: (.*), Type: (.*)/";
        $patternArrayKeyVar = "/\[\+\]subkey: (.*), Value: (.*), Type: (.*)/";
        $patternArrayIdxVar = "/\[\+\]sub_idx: (.*), Value: (.*), Type: (.*)/";
        $patternStaticEnd = "[*]End static_variables extract.";


        $arginfoNo = 0;
        $argname = 0;
        $argis_ref = 0;
        $argis_variadic = 0;
        

        $patternPropertiesKey = "/Key: ([^,]*)/";
        $patternPropertiesValue = "/Value: (.*)/";
        $patternPropertiesType = "/Type: (.*)/";
        $patternPropertiesVar = "/Key: (.*), Value: (.*), Type: (.*)/";

        $mainCheckFlag = 0;
        $classCheckFlag = 0;
        $functionCheckFlag = 0;
        $funcArgCheckFlag = 0;
        $funcStaticVarCheckFlag = 0;
        $propertiesCheckFlag = 0;
        $opcodeCheckFlag = 0;
        $CVFlag = 1;

        $numberOfOps = 0;

        $argfuncRetType = '';

        debugEcho("[DEBUG] Opcodes parsing!\n", $debugFlag, 'green');
        while (!$file->eof()) {
            $currentLine = $file->key();
            $currentContent = $file->fgets();

            if (preg_match($patternfilename, $currentContent, $match) and !isset($filename)) {
                $filename = trim($match[1]);
                continue;
            }

            if ($mainCheckFlag == 1) {
                if (!$numberOfOps) {
                    preg_match($patternNumOps, $currentContent, $match);
                    $fileObject->funcOpArray["main"] = new FuncInfo("main", $phpfilename, (int)trim($match[1]));
                    $file->seek($currentLine + 1);
                    $opcodeCheckFlag = 1;
                    $numberOfOps = 1;
                    $CVFlag = 0;
                    continue;
                }
                if(!$CVFlag){
                    $compiledVars = array();
                    preg_match($patternCvVar, $currentContent, $match);
                    $explode1 = explode(',',$match[1]);
                    if($explode1[0] != 'none'){
                        $explode2 = array_map('trim', $explode1);
                        foreach ($explode2 as $item) {
                            $tmpexplode = explode('=', $item);
                            $tmpexplode = array_map('trim', $tmpexplode);
                            $tmpkey = substr($tmpexplode[1], 1);
                            $tmpvalue = $tmpexplode[0];
                            $compiledVars[$tmpkey] = $tmpvalue;
                        }
                        $fileObject->funcOpArray["main"]->compiledVars = $compiledVars;
                    }
                    $file->seek($currentLine + 3);
                    $CVFlag = 1;
                    continue;
                }
                if ($opcodeCheckFlag & trim($currentContent) != '') {
                    $CurrentNo = $this->opcodeParse($fileObject->funcOpArray["main"], $currentContent, $CurrentNo);
                    continue;
                }
                $opcodeCheckFlag = 0;
                $mainCheckFlag = 0;
                $numberOfOps = 0;
                continue;
            }

            if ($funcArgCheckFlag == 1){
                if(preg_match($patternFunctionScope, $currentContent, $match)){
                    $scopeClassName = $match[1];
                    continue;
                }
                if(strpos($currentContent, $patternArgEnd) !== false){
                    $funcArgCheckFlag = 0;
                    $funcStaticVarCheckFlag = 1;
                    continue;
                }

                if (preg_match($patternFuncRetType, $currentContent, $match)) {
                    $argfuncRetType = trim($match[1]);
                    continue;
                }

                if (!preg_match($patternArgInfoNo, $currentContent, $match)){
                    continue;
                }else{
                    $arginfoNo = (int)trim($match[1]);
                    $currentLine = $currentLine + 1;
                    $file->seek($currentLine);

                    $currentContent = $file->fgets();
                    preg_match($patternArgName, $currentContent, $match);
                    $argname = trim($match[1]);
                    $currentLine = $currentLine + 1;
                    $file->seek($currentLine);

                    $currentContent = $file->fgets();
                    preg_match($patternArgType, $currentContent, $match);
                    $argType = trim($match[1]);
                    $currentLine = $currentLine + 1;
                    $file->seek($currentLine);

                    $currentContent = $file->fgets();
                    preg_match($patternArgIsRef, $currentContent, $match);
                    $argis_ref = (int)trim($match[1]);
                    $currentLine = $currentLine + 1;
                    $file->seek($currentLine);

                    $currentContent = $file->fgets();
                    preg_match($patternArgIsVariadic, $currentContent, $match);
                    $argis_variadic = (int)trim($match[1]);
                    $currentLine = $currentLine + 1;
                    $file->seek($currentLine);

                    if($classCheckFlag == 1){
                        if (!isset($fileObject->classOpArray[$className]->funcOpArray[$funcName])) {
                            $fileObject->classOpArray[$className]->funcOpArray[$funcName] = new FuncInfo($funcName, $phpfilename);
                            $fileObject->classOpArray[$className]->funcOpArray[$funcName]->scopeClassName = $scopeClassName;
                            $fileObject->classOpArray[$className]->funcOpArray[$funcName]->funcRetType = $argfuncRetType;
                        }
                        $fileObject->classOpArray[$className]->funcOpArray[$funcName]->argsInfo($arginfoNo, $argname, $argType, $argis_ref, $argis_variadic);
                    }else{
                        if (!isset($fileObject->funcOpArray[$funcName])) {
                            $fileObject->funcOpArray[$funcName] = new FuncInfo($funcName, $phpfilename);
                            $fileObject->funcOpArray[$funcName]->funcRetType = $argfuncRetType;
                        }
                        $fileObject->funcOpArray[$funcName]->argsInfo($arginfoNo, $argname, $argType, $argis_ref, $argis_variadic);
                    }
                }
                continue;
            }

            if ($funcStaticVarCheckFlag == 1) {
                if (strpos($currentContent, $patternStaticStart) !== false){
                    continue;
                } 
                else {
                    while($funcStaticVarCheckFlag){
                        if(preg_match($patternStaticVar, $currentContent, $match))
                        {
                            $tmp_key = $match[1];
                            $tmp_value = $match[2];
                            $tmp_type = $match[3];
                            if($tmp_type != "IS_ARRAY"){
                                if ($classCheckFlag == 1) {
                                    if (!isset($fileObject->classOpArray[$className]->funcOpArray[$funcName])) {
                                        $fileObject->classOpArray[$className]->funcOpArray[$funcName] = new FuncInfo($funcName, $phpfilename);
                                    }
                                    $this->funcStaticVarDeal($fileObject->classOpArray[$className]->funcOpArray[$funcName], $tmp_key, $tmp_value, $tmp_type);
                                } else {
                                    if (!isset($fileObject->funcOpArray[$funcName])) {
                                        $fileObject->funcOpArray[$funcName] = new FuncInfo($funcName, $phpfilename);
                                    }
                                    $this->funcStaticVarDeal($fileObject->funcOpArray[$funcName], $tmp_key, $tmp_value, $tmp_type);
                                }
                            }
                        }
                        elseif(strpos($currentContent, $patternArrayStart) !== false)
                        {
                            $arrayKey = $tmp_key;
                            $arrayType = $tmp_type;
                            $arrayCheckFlag = 1;
                            $tmp_array = new ArrayStruct();
                            while($arrayCheckFlag){
                                if(preg_match($patternArrayKeyVar, $currentContent, $match)){
                                    $tmp_array->keyArray[$match[1]] = new ValueStruct($match[2]);      
                                }
                                else if(preg_match($patternArrayIdxVar, $currentContent, $match)){
                                    $tmp_array->keyArray[$match[1]] = new ValueStruct($match[2]); 
                                }
                                else if (preg_match($patternStaticSubVar, $currentContent, $match)) {
                                    $tmp_subarray = new ArrayStruct();
                                    $subarrayCheckFlag = 1;
                                    $tmp_array->keyArray[$match[1]] = &$tmp_subarray; 
                                    while($subarrayCheckFlag){
                                        if (preg_match($patternArrayKeyVar, $currentContent, $match)) {
                                            $tmp_subarray->keyArray[$match[1]] = new ValueStruct($match[2]);      
                                        } 
                                        else if (preg_match($patternArrayIdxVar, $currentContent, $match)) {
                                            $tmp_subarray->keyArray[$match[1]] = new ValueStruct($match[2]);
                                        } 
                                        else if(strpos($currentContent, $patternArraySubEnd) !== false){
                                            $subarrayCheckFlag = 0;
                                            continue;
                                        }
                                        $currentLine = $currentLine + 1;
                                        $file->seek($currentLine);
                                        $currentContent = $file->fgets();
                                    }
                                }
                                else if(strpos($currentContent, $patternArrayEnd) !== false){
                                    $arrayCheckFlag = 0;
                                    if ($classCheckFlag == 1
                                    ) {
                                        $this->funcStaticArrayVarDeal($fileObject->classOpArray[$className]->funcOpArray[$funcName], $arrayKey, $tmp_array, $arrayType);
                                    } else {
                                        $this->funcStaticArrayVarDeal($fileObject->funcOpArray[$funcName], $arrayKey, $tmp_array, $arrayType);
                                    }
                                    continue;
                                }
                                $currentLine = $currentLine + 1;
                                $file->seek($currentLine);
                                $currentContent = $file->fgets();
                            }
                        }
                        else if(strpos($currentContent, $patternStaticEnd) !== false){
                            $funcStaticVarCheckFlag = 0;
                            $functionCheckFlag = 1;
                        }
                        $currentLine = $currentLine + 1;
                        $file->seek($currentLine);
                        $currentContent = $file->fgets();
                    }
                }
                continue;
            }

            
            if ($classCheckFlag == 0 and $functionCheckFlag == 1) {
                if (!$numberOfOps) {
                    while (!preg_match($patternNumOps, $currentContent, $match)) {
                        if (preg_match($patternshortfuncName, $currentContent, $match)) {
                            $shortfuncName = strtolower(trim($match[1]));
                        }
                        $currentContent = $file->fgets();
                        $currentLine = $currentLine + 1;
                        $file->seek($currentLine);
                    }
                    if (!isset($fileObject->funcOpArray[$funcName])) {
                        $fileObject->funcOpArray[$funcName] = new FuncInfo($funcName, $phpfilename,  (int)trim($match[1]));
                    }else{
                        $fileObject->funcOpArray[$funcName]->opcodeCount = (int)trim($match[1]);
                    }
                    $file->seek($currentLine + 1 - 1); 
                    $opcodeCheckFlag = 1;
                    $numberOfOps = 1;
                    $CVFlag = 0;
                    continue;
                }
                if (!$CVFlag) {
                    $compiledVars = array();
                    preg_match($patternCvVar, $currentContent, $match);
                    $explode1 = explode(',', $match[1]);
                    if ($explode1[0] != 'none') {
                        $explode2 = array_map('trim', $explode1);
                        foreach ($explode2 as $item) {
                            $tmpexplode = explode('=', $item);
                            $tmpexplode = array_map('trim', $tmpexplode);
                            $tmpkey = substr($tmpexplode[1], 1);
                            $tmpvalue = $tmpexplode[0];
                            $compiledVars[$tmpkey] = $tmpvalue;
                        }
                        $fileObject->funcOpArray[$funcName]->compiledVars = $compiledVars;
                    }
                    $file->seek($currentLine + 3);
                    $CVFlag = 1;
                    continue;
                }
                if ($opcodeCheckFlag & trim($currentContent) != '') {
                    $CurrentNo = $this->opcodeParse($fileObject->funcOpArray[$funcName], $currentContent, $CurrentNo);
                    continue;
                }
                $fileObject->funcOpArray[$funcName]->shortfuncName = $shortfuncName;
                $opcodeCheckFlag = 0;
                $functionCheckFlag = 0;
                $numberOfOps = 0;
                continue;
            }

            if ($propertiesCheckFlag == 1) {
                if (!strstr($currentContent, "End") and !strstr($currentContent, "Properties: NULL")) {
                    preg_match($patternPropertiesVar,$currentContent, $match);
                    if(!empty($match)){
                        $Key = $match[1];
                        $Value = $match[2];
                        $Type = $match[3];
                        switch ($Value) {
                            case '<true>':
                                $Value = true;
                                break;
                            case '<false>':
                                $Value = false;
                                break;
                            default:
                                break;
                        }
                        $propertiesArray[$Key] = [$Type, $Value];
                    }else if(!isset($tmpkeyLOF)){
                        $tmpkeyLOF = $currentContent;
                        $tmpkeyLOF = str_replace("\n", "", $tmpkeyLOF);
                    }else if(isset($tmpkeyLOF)){
                        $tmpkeyLOF .= $currentContent;
                        preg_match($patternPropertiesVar, $tmpkeyLOF, $match);
                        $Key = $match[1];
                        $Value = '\n';
                        $Type = $match[3];
                        $propertiesArray[$Key] = [$Type, $Value];
                        unset($tmpkeyLOF);
                    }
                    continue;
                }
                if(isset($fileObject->classOpArray[$className])){
                    $OriginClassName = $className;
                    $tmpClassObject = $fileObject->classOpArray[$className];
                    unset($fileObject->classOpArray[$className]);
                    $fileObject->classOpArray[$className][] = $tmpClassObject;
                    $className = "tmpClassanonymous";
                    $fileObject->classOpArray[$className] = new ClassInfo($OriginClassName, $propertiesArray);
                }else{
                    $fileObject->classOpArray[$className] = new ClassInfo($className, $propertiesArray);
                }
                $fileObject->classOpArray[$className]->parentClassName = $parentClassName;
                if($classTraitName){
                    $fileObject->classOpArray[$className]->classTraitName = $classTraitName;
                }
                
                $propertiesCheckFlag = 0;
                continue;
            }
            if ($classCheckFlag == 1 and $functionCheckFlag == 1) {
                if (!$numberOfOps) {
                    while (!preg_match($patternNumOps, $currentContent, $match)) {
                        $currentContent = $file->fgets();
                        $currentLine = $currentLine + 1;
                        $file->seek($currentLine);
                    }
                    if (!isset($fileObject->classOpArray[$className]->funcOpArray[$funcName])) {
                        $fileObject->classOpArray[$className]->funcOpArray[$funcName] = new FuncInfo($funcName, $phpfilename,  (int)trim($match[1]));
                    } else {
                        $fileObject->classOpArray[$className]->funcOpArray[$funcName]->opcodeCount = (int)trim($match[1]);
                    }
                    $file->seek($currentLine + 1 - 1); 
                    $opcodeCheckFlag = 1;
                    $numberOfOps = 1;
                    $CVFlag = 0;
                    continue;
                }
                if (!$CVFlag) {
                    $compiledVars = array();
                    preg_match($patternCvVar, $currentContent, $match);
                    $explode1 = explode(',', $match[1]);
                    if (trim($explode1[0]) != 'none') {
                        $explode2 = array_map('trim', $explode1);
                        foreach ($explode2 as $item) {
                            $tmpexplode = explode('=', $item);
                            $tmpexplode = array_map('trim', $tmpexplode);
                            $tmpkey = substr($tmpexplode[1], 1);
                            $tmpvalue = $tmpexplode[0];
                            $compiledVars[$tmpkey] = $tmpvalue;
                        }
                        $fileObject->classOpArray[$className]->funcOpArray[$funcName]->compiledVars = $compiledVars;
                    }
                    $file->seek($currentLine + 3);
                    $CVFlag = 1;
                    continue;
                }
                if ($opcodeCheckFlag & trim($currentContent) != '') {
                    $CurrentNo = $this->opcodeParse($fileObject->classOpArray[$className]->funcOpArray[$funcName], $currentContent, $CurrentNo);
                    continue;
                }
                $opcodeCheckFlag = 0;
                $functionCheckFlag = 0;
                $numberOfOps = 0;
                continue;
            }

            if (preg_match($patternClassEnd, $currentContent, $match)) {
                if($className == "tmpClassanonymous"){
                    $fileObject->classOpArray[$OriginClassName][] = $fileObject->classOpArray[$className];
                    unset($OriginClassName);
                    unset($fileObject->classOpArray[$className]);
                }
                $classCheckFlag = 0;
                continue;
            }
            
            if (strpos($currentContent, $patternMain) !== false) {
                debugEcho("[DEBUG] Deal main function!\n", $debugFlag);
                debugEcho("[DEBUG] Current line no: " . $currentLine . "\n", $debugFlag);
                $fileObject->filePath = $opcodefilename;
                $mainCheckFlag = 1;
            } else if (preg_match($patternFunction, $currentContent, $match)) {
                debugEcho("[DEBUG] Deal user-function: " . $match[1] . " !\n", $debugFlag);
                debugEcho("[DEBUG] Current line no: " . $currentLine . "\n", $debugFlag);
                $funcName = $match[1];
                $funcArgCheckFlag = 1;
            } else if (preg_match($patternClass, $currentContent, $match)) {
                debugEcho("[DEBUG] Deal Class: " . $match[1] . " !\n", $debugFlag);
                debugEcho("[DEBUG] Current line no: " . $currentLine . "\n", $debugFlag);
                $className = $match[1];
                $classCheckFlag = 1;
                $currentLine = $currentLine + 1;
                $file->seek($currentLine);
                $currentContent = $file->fgets();
                if(preg_match($patternParentClass, $currentContent, $match)){
                    $parentClassName = $match[1];
                }
            } else if (preg_match($patternClassTrait, $currentContent, $match)){
                $classTraitName[] = $match[1];
                $currentLine = $currentLine + 1;
                $file->seek($currentLine);
                $currentContent = $file->fgets();
                while (preg_match($patternClassTrait, $currentContent, $match)) {
                    $classTraitName[] = $match[1];
                    $currentLine = $currentLine + 1;
                    $file->seek($currentLine);
                    $currentContent = $file->fgets();
                }
            } else if (strpos($currentContent, $patternProperties) !== false) {
                debugEcho("[DEBUG] Properties in Class found:\n", $debugFlag);
                debugEcho("[DEBUG] Current line no: " . $currentLine . "\n", $debugFlag);
                $propertiesCheckFlag = 1;
            }
        }

        return $fileObject;
    }


    public function bbBuild(&$funcObject)
    {

        $opArray = $funcObject->opArray;
        $opcodeCount = $funcObject->opcodeCount;
        $flagArray = array_fill(0, $opcodeCount, 0);
        $jmpAddrQueue = array();
        $tmpBB = new BasicBlock(0);
        $flagArray[0] = 1;

        $entryNode = array_unique($funcObject->entryNode);

        $fastCallAddr = 0;     
        $finallyEndAddr = 0;   

        for ($i = 0; $i < $opcodeCount; $i++) {
            $opcodeNo = $opArray[$i]->opcodeNo;
            switch ($opcodeNo) 
            {
                case ZEND_JMP:
                    $jmpAddr = (int)trim($opArray[$i]->op1, '->');
                    $tmpBB->exit = $i;
                    if($opArray[$i+1]->opcodeNo == ZEND_CATCH){
                        $tmpBB->jumpInfo[] = $i + 1;
                        array_push($jmpAddrQueue, $i + 1);
                        $jmpAddr = $i + 1;
                    }else{
                        $tmpBB->jumpInfo[] = $jmpAddr; 
                    }
                    $funcObject->bbArray[] = $tmpBB;   
                                                       
                    $tmpBB = new BasicBlock($jmpAddr);
                    $flagArray[$jmpAddr]++;            
                    $i = $jmpAddr-1;  
                    break;
                case ZEND_JMPZ:
                case ZEND_JMPNZ:
                case ZEND_JMPZ_EX:
                case ZEND_JMPNZ_EX:
                case ZEND_FE_RESET_R:
                case ZEND_FE_RESET_RW:
                case ZEND_JMP_SET:
                case ZEND_COALESCE:
                case ZEND_ASSERT_CHECK:
                case ZEND_JMP_NULL:
                    $jmpAddr = (int)trim($opArray[$i]->op2, '->');
                    $tmpBB->exit = $i;
                    $tmpBB->jumpInfo[] = $i + 1;        
                    $tmpBB->jumpInfo[] = $jmpAddr;      
                    $funcObject->bbArray[] = $tmpBB;    
                    array_push($jmpAddrQueue, $jmpAddr);
                    $tmpBB = new BasicBlock($i + 1);
                    $flagArray[$i + 1]++;           
                    break;
                case ZEND_FE_FETCH_R:
                case ZEND_FE_FETCH_RW:
                    $jmpAddr = (int)trim($opArray[$i]->jmp_ext, '->');
                    $tmpBB->exit = $i;
                    $tmpBB->jumpInfo[] = $i + 1;        
                    $tmpBB->jumpInfo[] = $jmpAddr;      
                    $funcObject->bbArray[] = $tmpBB;    
                    array_push($jmpAddrQueue, $jmpAddr);
                    $tmpBB = new BasicBlock($i + 1);
                    $flagArray[$i + 1]++;             
                    break;


                case ZEND_FAST_CALL:
                    $jmpAddr = (int)trim($opArray[$i]->op1, '->');
                    $finallyEndAddr = $i+1;

                    if (!empty($tmpBB)) {
                        $tmpBB->exit = $i;
                        $tmpBB->jumpInfo[] = $jmpAddr;
                        $funcObject->bbArray[] = $tmpBB;    
                    }
                    $tmpBB = new BasicBlock($jmpAddr);
                    $flagArray[$jmpAddr]++;                   

                    $i = $jmpAddr - 1;                   
                    break;
                case ZEND_FAST_RET:
                    $tmpBB->exit = $i;                              
                    $tmpBB->jumpInfo[] = $finallyEndAddr;           
                    $funcObject->bbArray[] = $tmpBB;                
                    $tmpBB = new BasicBlock($finallyEndAddr);
                    $flagArray[$finallyEndAddr]++;                  
                    $i = $finallyEndAddr - 1;                       
                    break;

                case ZEND_RETURN:
                    $tmpBB->exit = $i;
                    $tmpBB->jumpInfo[] = "EXIT";
                    $funcObject->bbArray[] = $tmpBB;    
                    $i = $opcodeCount - 1;              
                    break;
                default:
                    $opcodeCount = $funcObject->opcodeCount;
                    if ($i + 1 == $opcodeCount) {
                        $tmpBB->exit = $i;
                        $tmpBB->jumpInfo[] = "EXIT";
                        $funcObject->bbArray[] = $tmpBB;    
                    }
                    if ($tmpBB->entry <= ($i-1) and in_array($i, $entryNode)) {
                        $tmpBB->exit = $i - 1;          
                        $tmpBB->jumpInfo[] = $i;            
                        $funcObject->bbArray[] = $tmpBB;    
                        $tmpBB = new BasicBlock($i);
                        $flagArray[$i]++;
                    }
                    break;
            }
            if ($flagArray[$i] == 2)
            {
                break;
            }
        }
        while ($jmpAddrQueue) {
            $jmpAddr = array_shift($jmpAddrQueue);         
            $jmpFlag = 0;                                  
            $tmpindex = $jmpAddr;                          
            $tmpBB = new BasicBlock($tmpindex);            
            while (!$jmpFlag) {
                if (!$flagArray[$tmpindex])                
                {
                    $opcodeNo = $opArray[$tmpindex]->opcodeNo;
                    switch ($opcodeNo) {
                        case ZEND_JMP:
                            $flagArray[$jmpAddr]++;           
                            $jmpAddr = (int)trim($opArray[$tmpindex]->op1, '->');
                            $tmpBB->exit = $tmpindex;
                            $tmpBB->jumpInfo[] = $jmpAddr;      
                            if ($opArray[$tmpindex + 1]->opcodeNo == ZEND_CATCH) {
                                $tmpBB->jumpInfo[] = $tmpindex + 1;
                                array_push($jmpAddrQueue, $tmpindex + 1);
                            }
                            $funcObject->bbArray[] = $tmpBB;    
                            $tmpBB = new BasicBlock($jmpAddr);
                            $tmpindex = $jmpAddr;              
                            break;
                        case ZEND_JMPZ:
                        case ZEND_JMPNZ:
                        case ZEND_JMPZ_EX:
                        case ZEND_JMPNZ_EX:
                        case ZEND_FE_RESET_R:
                        case ZEND_FE_RESET_RW:
                        case ZEND_JMP_SET:
                        case ZEND_COALESCE:
                        case ZEND_ASSERT_CHECK:
                        case ZEND_JMP_NULL:
                            $flagArray[$jmpAddr]++;              
                            $jmpAddr = (int)trim($opArray[$tmpindex]->op2,
                                '->'
                            );
                            $tmpBB->exit = $tmpindex;
                            $tmpBB->jumpInfo[] = $tmpindex + 1;        
                            $tmpBB->jumpInfo[] = $jmpAddr;             
                            $funcObject->bbArray[] = $tmpBB;           
                            array_push($jmpAddrQueue, $jmpAddr);
                            $jmpAddr = $tmpindex + 1;
                            $tmpBB = new BasicBlock($jmpAddr);
                            $tmpindex++;
                            break;
                        case ZEND_FE_FETCH_R:
                        case ZEND_FE_FETCH_RW:
                            $flagArray[$jmpAddr]++;                   
                            $jmpAddr = (int)trim($opArray[$tmpindex]->jmp_ext, '->');
                            $tmpBB->exit = $tmpindex;
                            $tmpBB->jumpInfo[] = $tmpindex + 1;       
                            $tmpBB->jumpInfo[] = $jmpAddr;            
                            $funcObject->bbArray[] = $tmpBB;          
                            array_push($jmpAddrQueue, $jmpAddr);
                            $jmpAddr = $tmpindex + 1;
                            $tmpBB = new BasicBlock($jmpAddr);
                            $tmpindex++;
                            break;


                        case ZEND_FAST_CALL:
                            $jmpAddr = (int)trim($opArray[$tmpindex]->op1, '->');
                            $finallyEndAddr = (int)trim($opArray[$tmpindex + 1]->op1, '->');

                            $tmpBB = new BasicBlock($tmpindex);
                            $flagArray[$tmpindex]++;                 

                            $tmpindex = $jmpAddr;
                            
                            break;
                        case ZEND_FAST_RET:
                            $tmpBB->exit = $tmpindex;                      
                            $tmpBB->jumpInfo[] = $finallyEndAddr;          
                            $funcObject->bbArray[] = $tmpBB;               
                            $tmpBB = new BasicBlock($finallyEndAddr);
                            $flagArray[$finallyEndAddr]++;                
                            $tmpindex = $finallyEndAddr;                   
                            break;

                        default:
                            $opcodeCount = $funcObject->opcodeCount;
                            if ($tmpindex + 1 == $opcodeCount) {
                                $tmpBB->exit = $tmpindex;
                                $tmpBB->jumpInfo[] = "EXIT";
                                $funcObject->bbArray[] = $tmpBB;    
                                $jmpFlag = 1;                       
                            }
                            $tmpindex++;
                            if(in_array($tmpindex,$entryNode))
                            {
                                $jmpFlag = 1;
                                $tmpBB->exit = $tmpindex - 1;
                                $tmpBB->jumpInfo[] = $tmpindex;     
                                $funcObject->bbArray[] = $tmpBB;    
                            }
                            break;
                    }
                }
                else
                {
                    $jmpFlag = 1;
                    if($tmpBB->entry <= $tmpindex - 1)          
                    {
                        $tmpBB->exit = $tmpindex - 1;
                        $tmpBB->jumpInfo[] = $tmpindex;         
                        $funcObject->bbArray[] = $tmpBB;            
                    }

                }
            }
        }

        array_unshift($funcObject->bbArray[0]->jumpInfo, "ENTRY");   
    }


    public function funcCfgBuild(&$filefuncOpArray,&$filegraph)
    {
        $nodeLabelPattern = '{ op #%d-%d, line %s-%s | ';
        $opcodeLabelPattern = ' %s (%s , %s) ### ';
        $opcodeLabelPatternForRES = ' %s = %s (%s , %s) ### ';
        $opcodeLabelPatternForEXTJMP = ' %s (%s , %s), %s ### ';

        foreach ($filefuncOpArray as $funcObject) {
            $this->bbBuild($funcObject);
            $funcName = $funcObject->funcName;
            $funcgraph = $filegraph->subgraph('cluster_Function_' . md5(rand()));
            $funcgraph->set('label', 'Function: ' . $funcName);
            $funcgraph->attr('graph', ['rankdir' => 'LR']);
            $funcgraph->attr('node', ['shape' => 'record']);

            $opArray = $funcObject->opArray;
            $bbArray = $funcObject->bbArray;
            foreach ($bbArray as $basicBlock) {
                $bbEntry = $basicBlock->entry;
                $bbExit = $basicBlock->exit;
                $bbID = $funcName . "_" . (string)$bbEntry;
                $jumpInfo = $basicBlock->jumpInfo;

                $lineStart = $funcObject->opArray[$bbEntry]->lineNo;
                $lineEnd = $funcObject->opArray[$bbExit]->lineNo;
                $nodeLabel = sprintf($nodeLabelPattern, $bbEntry, $bbExit, $lineStart, $lineEnd);
                $outputLabel = $nodeLabel;

                for ($i = $bbEntry; $i <= $bbExit; $i++) {
                    $opcode = $opArray[$i]->opcodeName;
                    $op1 = $opArray[$i]->op1;
                    $op2 = $opArray[$i]->op2;
                    $result = $opArray[$i]->result;
                    $extjmp = $opArray[$i]->jmp_ext;
                    if (!$op1 and !$op2) {
                        continue;
                    }
                    $op1 = str_replace("->", " JMP ", $op1);
                    $op2 = str_replace("->", " JMP ", $op2);
                    $extjmp = str_replace("->", " JMP ", $extjmp);
                    if (!$result) {
                        if ($extjmp) {
                            $opcodeLabel = sprintf($opcodeLabelPatternForEXTJMP, $opcode, $op1, $op2, $extjmp);
                        } else {
                            $opcodeLabel = sprintf($opcodeLabelPattern, $opcode, $op1, $op2);
                        }
                    } else {
                        $opcodeLabel = sprintf($opcodeLabelPatternForRES, $result, $opcode, $op1, $op2);
                    }
                    $outputLabel = $outputLabel . $opcodeLabel;
                }
                $outputLabel = $outputLabel . '}';
                $funcgraph->node($bbID, ["label" => $outputLabel]);
                $funcObject->cfgInfo[$bbID] = $basicBlock;                 
                

                foreach ($jumpInfo as $targetBB) {
                    $tbbID = $funcName . "_" . (string)$targetBB;
                    if ($targetBB != "ENTRY") {
                        $funcgraph->edge(array($bbID, $tbbID));
                        $funcObject->cfgInfo[$bbID]->bbconnect[] = $tbbID;        
                    } else {
                        $funcgraph->edge(array($tbbID, $bbID));
                    }
                }
            }
        }
    }

    public function cfgBuild(&$fileObject, $ifSave=False)
    {
        $fileid = $fileObject->id;
        $filePath = $fileObject->filePath;
        $classOpArray = $fileObject->classOpArray;
        $filefuncOpArray = $fileObject->funcOpArray;

        $filePathInfo = pathinfo($filePath);
        $cfgSavePath = $filePathInfo['dirname'].DIRECTORY_SEPARATOR.$filePathInfo['filename'].'_cfg.dot';

        $cfgInfo = 'FileID: ' . $fileid . '. FilePath: ' . $filePath;
        $graph = new Graphviz\Digraph();
        $filegraph = $graph->subgraph('cluster_File');
        $filegraph->set('label', $cfgInfo);

        $this->funcCfgBuild($filefuncOpArray, $filegraph);

        foreach ($classOpArray as $classObject) {
            if(is_array($classObject)){
                $anonymousClassCount = 0;
                foreach ($classObject as $anonymousClass) {
                    $anonymousClassCount++;
                    $funcOpArray = $anonymousClass->funcOpArray;
                    $classgraph = $filegraph->subgraph('cluster_Class_' . md5(rand()));
                    $classgraph->set('label', 'Class: ' . $anonymousClass->className . (string)$anonymousClassCount);

                    $this->funcCfgBuild($funcOpArray, $classgraph);
                }
            }else{
                $funcOpArray = $classObject->funcOpArray;
                $classgraph = $filegraph->subgraph('cluster_Class_' . md5(rand()));
                $classgraph->set('label', 'Class: ' . $classObject->className);

                $this->funcCfgBuild($funcOpArray, $classgraph);
            }

        }

        $dotOutput = $graph->render();
        $dotOutput = str_replace("###", "\\l", $dotOutput);
        $dotOutput = str_replace("<array>", "\\<array\\>", $dotOutput);
        if($ifSave){
            file_put_contents($cfgSavePath,$dotOutput);
        }
    }

    public function cgBuild(&$fileObject, $ifSave=False)
    {

        $fileid = $fileObject->id;
        $filePath = $fileObject->filePath;
        $classOpArray = $fileObject->classOpArray;
        $filefuncOpArray = $fileObject->funcOpArray;

        $filePathInfo = pathinfo($filePath);
        $cgSavePath = $filePathInfo['dirname'] . DIRECTORY_SEPARATOR . $filePathInfo['filename'] . '_cg.dot';

        foreach ($filefuncOpArray as $funcobject) {
            if(!empty($funcobject->cgInfo)){
                foreach ($funcobject->cgInfo as $callgraphobject) {
                    $caller = $callgraphobject->caller;
                    $callee = array($callgraphobject->callee, $callgraphobject->callsiteAddr, $callgraphobject->parameters);
                    if (empty($fileObject->callGraphInfo[$caller])) {
                        $fileObject->callGraphInfo[$caller] = [];
                        array_push($fileObject->callGraphInfo[$caller], $callee);
                    } else {
                        array_push($fileObject->callGraphInfo[$caller], $callee);
                    }
                }
            }
        }

        foreach ($classOpArray as $classobject) {
            foreach($classobject->funcOpArray as $funcobject){
                if (!empty($funcobject->cgInfo)) {
                    foreach ($funcobject->cgInfo as $callgraphobject) {
                        $caller = $classobject->className . '::' . $callgraphobject->caller;
                        $callee = array($callgraphobject->callee, $callgraphobject->callsiteAddr, $callgraphobject->parameters);
                        if (empty($fileObject->callGraphInfo[$caller])) {
                            $fileObject->callGraphInfo[$caller] = [];
                            array_push($fileObject->callGraphInfo[$caller], $callee);
                        } else {
                            array_push($fileObject->callGraphInfo[$caller], $callee);
                        }
                    }
                }
            }
        }

        $calleeLabelPattern = '{ callsiteAddr : %d | %s } ';
        $cfgInfo = 'FileID: ' . $fileid . '. FilePath: ' . $filePath;
        $graph = new Graphviz\Digraph();
        $filegraph = $graph->subgraph('cluster_File');
        $filegraph->set('label', $cfgInfo);

        $mainFunc = $filefuncOpArray['main'];
        $mainCgObject = $mainFunc->cgInfo;

        
        $maingraph = $filegraph->subgraph('cluster_main_' . md5(rand()));
        $maingraph->set('label', '__main');
        $maingraph->attr('graph', ['rankdir' => 'LR']);
        $maingraph->attr('node', ['shape' => 'record']);
        $maingraph->node('main', ["label" => 'main_funcion']);

        foreach ($mainCgObject as $cgObject) {
            $maingraph->edge(array('main', $cgObject->callee));
        }

        foreach ($mainCgObject as $cgObject) {
            $callsite = sprintf($calleeLabelPattern, $cgObject->callsiteAddr, $cgObject->callee);
            $maingraph->node($cgObject->callee, ['label' => $callsite]);

            $calleeType = explode('::', $cgObject->callee);

            foreach ($fileObject->callGraphInfo as $funcname => $funccall) {
                if ($calleeType[1] == $funcname) {
                    $count = 0;
                    foreach ($funccall as $value) {
                        $count++;                                                                      
                        $calleename = $value[0] . (string)$count;                                      
                        $tmpcallsite = sprintf($calleeLabelPattern, $value[1], $value[0]);             
                        $maingraph->edge(array($calleeType[0]. "::" . $funcname, $calleename));
                        $maingraph->node($calleename, ['label' => $tmpcallsite]);
                    }
                }
            }

        }

        $dotOutput = $graph->render();

        if ($ifSave) {
            file_put_contents($cgSavePath, $dotOutput);
        }
    }


    public function searchBB($jmpAddr, $cfgbbArray)
    {
        foreach ($cfgbbArray as $key => $item) {
            if($item->entry == $jmpAddr){
                return $key;
            }
        }
    }


    public function get_output_dir($filedir)
    {
        $filedirinfo = pathinfo($filedir);
        $result[] = $filedirinfo['dirname'];
        $result[] = $filedirinfo['filename'];
        $result[] = $filedirinfo['dirname'] . '/' . $filedirinfo['filename'] . '.opcode';
        
        return $result;
    }

    function array_unique_fb($array2D)
    {
        foreach ($array2D as $v) {
            $v = join(",", $v);                               
            $temp[] = $v;
        }
        $temp = array_unique($temp);                          
        foreach ($temp as $k => $v) {
            $temp[$k] = explode(",", $v);                     
        }
        return $temp;
    }





    public function ifVar($type, $opvar)
    {
        if($opvar == "<array>"){
            return 1;
        }
        switch ($type) {
            case "IS_TMP_VAR":
            case "IS_CV":
            case "IS_VAR":
                return 1;
            default:
                return 0;
        }
    }

    public function &refCalc($superglobals, $localVar, $array, $object, $refSource, $refType, $refIndex)
    {
        switch ($refType) {
            case IS_LOCAL:
            case "IS_LOCAL":
                return $localVar[$refSource];
            case IS_ARRAY:
            case "IS_ARRAY":
                return $array[$refSource];
            case "ARRAYITEM":
            case "ARRAYITEM":
                if (is_null($refIndex)) {
                    return $array[$refSource];
                } else {
                    return $array[$refSource]->keyArray[$refIndex];
                }
                return $array[$refSource]->keyArray[$refIndex];
            case IS_OBJECT:
            case "IS_OBJECT":
                if(is_null($refIndex)){
                    return $object[$refSource];
                }else{
                    return $object[$refSource]->propertiesArray[$refIndex];
                }
            case "superglobal":
                return $superglobals->superGlobalArrays[$refSource];
            default:
                return 0;
        }
    }

    public function varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $opcode, $funcPara=0, $opcode2 = NULL, $outputType = 0)
    {
        if (isset($localVar[$opcode])) {
            if($funcPara and $localVar[$opcode]->internalFuncCallCheck()){
                if ($outputType) {
                    return 'IS_INTERFUNC';
                } else {
                    return IS_INTERFUNC;
                }
            }else{
                if ($outputType) {
                    return 'IS_LOCAL';
                } else {
                    return IS_LOCAL;
                }
            }
        } else if (isset($array[$opcode])) {
            if ($outputType) {
                return 'IS_ARRAY';
            } else {
                return IS_ARRAY;
            }
        } else if ($opcode == "<array>") {
            if ($outputType) {
                return 'IS_CONST_ARRAY';
            } else {
                return IS_CONST_ARRAY;
            }
        } else if (isset($object[$opcode])) {
            if ($outputType) {
                return 'IS_OBJECT';
            } else {
                return IS_OBJECT;
            }
        } else if (isset($superglobals->unbind[$opcode])) {
            if ($outputType) {
                return 'IS_GLOBAL_UNBIND';
            } else {
                return IS_GLOBAL_UNBIND;
            }
        } else if (!empty($cvVarNamelist)){
            $cvName = $this->getCVName($opcode, $cvVarNamelist);
            if($cvName == "GLOBALS" and !is_null($opcode2) and isset($superglobals->superGlobalArrays["GLOBALS"]->keyArray[$opcode2])){
                if ($outputType) {
                    return 'IS_GLOBAL';
                } else {
                    return IS_GLOBAL;
                }
            }else if(!empty($cvName) and isset($superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvName])){
                if ($outputType) {
                    return 'IS_GLOBAL';
                } else {
                    return IS_GLOBAL;
                }
            }else{
                if ($outputType) {
                    return 'IS_INIT';
                } else {
                    return IS_INIT;
                }
            }
        } else {
            if ($outputType) {
                return 'IS_INIT';
            } else {
                return IS_INIT;
            }
        }

    }



    public function varTypeChange($type){
        switch ($type) {
            case IS_LOCAL:
                return 'IS_LOCAL';
            case IS_ARRAY:
                return 'IS_ARRAY';
            case IS_OBJECT:
                return 'IS_OBJECT';
            default:
                return 'ERROR';
        }
    }



    public function typeToGlobal($refType)
    {
        return 'GLOBAL';
        switch ($refType) {
            case 901:
            case 'IS_LOCAL':
                return 'GLOBAL_LOCAL';
            case 902:
            case 'IS_ARRAY':
                return 'GLOBAL_ARRAY';
            case 'ARRAYITEM':
                return 'GLOBAL_ARRAYITEM';
            case 904:
            case 'IS_OBJECT':
                return 'GLOBAL_OBJECT';
            default:
                return 'ERROR';
        }
    }


    public function typeToLocal($refType)
    {
        switch ($refType) {
            case 'GLOBAL_LOCAL':
                return 'IS_LOCAL';
            case 'GLOBAL_ARRAY':
                return 'IS_ARRAY';
            case 'GLOBAL_ARRAYITEM':
                return 'ARRAYITEM';
            case 'GLOBAL_OBJECT':
                return 'IS_OBJECT';
            default:
                return 'ERROR';
        }
    }


    public function funcStaticVarDeal(&$funcInfo, $key, $value, $type)
    {
        switch ($type) {
            case "1":            
            case "4":            
            case "6":            
                $valueStruct = new ValueStruct($value);
                $funcInfo->staticVar[$key] = [$valueStruct, IS_LOCAL];
                break;
            default:
                break;
        }
    }


    public function funcStaticArrayVarDeal(&$funcInfo, $key, $arrayStruct, $type)
    {
        switch ($type) {
            case "7":             
                $funcInfo->staticVar[$key] = [$arrayStruct, IS_ARRAY];
                break;
            default:
                break;
        }
    }


    public function getCVName($cv, $cvlist)
    {
        if(empty($cvlist)){
            return null;
        }
        foreach ($cvlist as $key => $value) {
            if($value == $cv){
                return $key;
            }
        }
        return null;
    }


    public function funcRefArgDeal(&$funcCallState)
    {
        $funcInfoArgs = $funcCallState->funcInfo->funcArgs;
        $funcActualArgs = $funcCallState->funcparameters;               
        if(!empty($funcActualArgs)){
            $argsCount = count($funcActualArgs);
            for ($i = 0; $i < $argsCount; $i++) {
                if(!empty($funcInfoArgs[$i]) and $funcInfoArgs[$i]->is_variadic != 1){
                    if (!empty($funcCallState->funcparameters[$i][1])) {
                        $funcCallState->funcparameters[$i][1]->if_ref = $funcInfoArgs[$i]->is_ref;
                        $funcCallState->funcparameters[$i][2] = $funcInfoArgs[$i]->is_ref;
                    } else {
                        $funcCallState->funcparameters[$i][1] = new ValueStruct();
                        $funcCallState->funcparameters[$i][1]->if_ref = $funcInfoArgs[$i]->is_ref;
                        $funcCallState->funcparameters[$i][2] = $funcInfoArgs[$i]->is_ref;
                    }
                }else{
                    break;
                }

            }
            

        }

    }


    public function callParaDeal(&$funcCallState)
    {
        $funcParas = &$funcCallState->funcparameters;
        if(!empty($funcParas)){
            foreach ($funcParas as $paraItem) {
                if (is_object($paraItem[1]) && get_class($paraItem[1]) == "ArrayStruct") {
                    foreach ($paraItem[1]->keyArray as $arrayItem) {
                        if ($arrayItem->if_ref == "REF_VAR") {
                            $arrayItem->refType = $this->typeToGlobal($arrayItem->refType);
                        }
                    }
                }
            }
        }
        if ($funcCallState->methodName == '__call' or $funcCallState->methodName == '__callstatic') {
            $paraCount = count($funcParas);
            $tmp_array = new ArrayStruct();
            for ($i=1; $i < $paraCount; $i++) { 
                $tmp_array->keyArray[] = clone $funcParas[$i][1];
            }
            $tmp_funcParas[0] = $funcParas[0];
            $tmp_funcParas[1] = [902, $tmp_array, 0];
            $funcParas = $tmp_funcParas;
        }
    }


    public function funcGlobalVarReturn($globalState,&$localVar,&$array,&$object)
    {
        $localVar = $globalState->localVar;
        $array = $globalState->array;
        $object = $globalState->object;
    }


    public function retParaDeal(&$funcCallState)
    {
        $funcParas = &$funcCallState->funcparameters;
        if(!empty($funcParas)){
            foreach ($funcParas as $paraItem) {
                if (get_class($paraItem[1]) == "ArrayStruct") {
                    foreach ($paraItem[1]->keyArray as $arrayItem) {
                        if ($arrayItem->if_ref == "REF_VAR") {
                            $arrayItem->refType = $this->typeToLocal($arrayItem->refType);
                        }
                    }
                }
            }
        }

    }

    public function funcArgRefReturn(&$globalState, &$localVar, &$array, &$object, $refSource, $refType, $nullVarname = NULL)
    {
        switch($refType){
            case IS_INIT:
                $globalState->localVar[$nullVarname] = $localVar[$refSource];
                break;
            case IS_LOCAL:
                $globalState->localVar[$refSource] = $localVar[$refSource];
                break;
            case IS_ARRAY:
                $globalState->array[$refSource] = $array[$refSource];
                break;
            case IS_OBJECT:
                $globalState->object[$refSource] = $object[$refSource];
                break;
            default:
                break;
        }
    }

    public function funcArgRefReturnS(&$superglobals,&$localVar, &$array, &$object, $refVar)
    {
        $cvName = $refVar->cvName;
        $refSource = $refVar->refSource;
        $refType = $refVar->refType;
        if(isset($superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName])){
            switch ($refType) {
                case IS_INIT:
                case IS_LOCAL:
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName] = $localVar[$refSource];
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName]->cvName = $cvName;
                    break;
                case IS_ARRAY:
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName] = $array[$refSource];
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName]->cvName = $cvName;
                    break;
                case IS_OBJECT:
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName] = $object[$refSource];
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName]->cvName = $cvName;
                    break;
                default:
                    break;
            }
        }
    }

    public function varRecover($internalFuncCallChain, $newInterFuncName){
        $decodeFlag = 0;
        $callChains = $internalFuncCallChain->callChains;
        $reverseChains = $internalFuncCallChain->reverseChains;
        $sourceChains = $internalFuncCallChain->sourceChains;
        $count = count($callChains);
        foreach ($reverseChains[$count-1] as $item) {
            if($newInterFuncName == $item){
                return true;        
            }
        }
        return false;
    }




    public function internalFuncParamIndex(array $sinkFuncTaintParamLoc,array $funcparameters)
    {
        $tmp_array = [];
        foreach ($sinkFuncTaintParamLoc as $item) {
            if ($item == 0) {
                return $funcparameters;
            } elseif ($item == -1) {
                return [];
            } elseif ($item < 500) {
                if(isset($funcparameters[$item - 1])){
                    array_push($tmp_array, $funcparameters[$item - 1]);
                }
            } elseif ($item > 500) {
                $paramsCount = count($funcparameters);
                $numArray = range(1000 - $item, $paramsCount - 1);
                foreach ($numArray as $numitem) {
                    array_push($tmp_array, $funcparameters[$numitem]);
                }
            }
        }
        return $tmp_array;
    }

    public function sinkFuncParamCheck(Array $sinkFuncTaintParamLoc, $receivedVar){
        foreach ($sinkFuncTaintParamLoc as $item) {
            if($item == 0){
                foreach ($receivedVar as $varitem) {
                    if ($varitem[1]->if_tainted == 1) {
                        return [$varitem[1]->taintedSourceLine,$varitem[1]->taintedLine];
                    }
                }
            }elseif ($item == -1){
                return true;
            }
            elseif ($item < 500) {
                if($receivedVar[$item-1][1]->if_tainted == 1){
                    return [$receivedVar[$item - 1][1]->taintedSourceLine,$receivedVar[$item - 1][1]->taintedLine];
                }
            }elseif ($item > 500) {
                $paramsCount = count($receivedVar);
                $numArray = range(1000-$item,$paramsCount-1);
                foreach ($numArray as $numitem) {
                    if ($receivedVar[$numitem][1]->if_tainted == 1) {
                        return [$receivedVar[$numitem][1]->taintedSourceLine,$receivedVar[$numitem][1]->taintedLine];
                    }
                }
            }
        }
    }

    public function funcParaAndReturnPass(&$globalState, &$localVar, &$array, &$object, $opcode, $paraType, &$paraValue, $ifRef = 0)
    {
        if ($ifRef) {
            $paraValue->if_ref = 1;
            $paraValue->refSource = $opcode;
            $paraValue->refType = $paraType;
        }
        switch ($paraType) {
            case IS_INIT:
                $structName = get_class($paraValue);
                switch ($structName) {
                    case 'ObjectStruct':
                        $object[$opcode] = $paraValue;
                        break;
                    case 'ArrayStruct':
                        $array[$opcode] = $paraValue;
                        break;
                    case 'ValueStruct':
                        $localVar[$opcode] = $paraValue;
                        break;
                    default:
                        break;
                }
                break;
            case IS_INTERFUNC:
            case IS_LOCAL:
                $localVar[$opcode] = $paraValue;
                break;
            case IS_CONST_ARRAY:
            case IS_ARRAY:
                $array[$opcode] = $paraValue;
                break;
            case IS_OBJECT:
                $object[$opcode] = $paraValue;
                break;
            default:
                break;
        }
    }

    public function valueSet(&$localVar, &$array, &$object, $opValueSturct, $opType, $saveid)
    {
        switch ($opType) {
            case IS_INIT:
            case IS_LOCAL:
                $localVar[$saveid] = clone $opValueSturct;
                break;
            case IS_ARRAY:
                $array[$saveid] = clone $opValueSturct;
                break;

            case IS_OBJECT:
                break;
            default:
                break;
        }
    }

    public function valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $opVar, $opType, $cvVarNamelist=NULL)
    {
        if(!empty($cvVarNamelist)){
            $cvVarName = $this->getCVName($opVar, $cvVarNamelist);
        }else{
            $cvVarName = NULL;
        }
        switch ($opType) {
            case IS_INIT:
                foreach ($localVar as $localkey => $itemobject) {
                    if(isset($itemobject->cvName) and $itemobject->cvName == $cvVarName){
                        return $localVar[$localkey];
                    }
                }
                foreach ($array as $arraykey => $itemobject) {
                    if(isset($itemobject->cvName) and $itemobject->cvName == $cvVarName){
                        return $array[$arraykey];
                    }
                }
                foreach ($object as $objectkey => $itemobject) {
                    if(isset($itemobject->cvName) and $itemobject->cvName == $cvVarName){
                        return $object[$objectkey];
                    }
                }
            case IS_INTERFUNC:
            case IS_LOCAL:
                foreach ($localVar as $localkey => $itemobject) {
                    if (isset($itemobject->cvName) and $itemobject->cvName == $cvVarName) {
                        return $localVar[$localkey];
                    }
                }
                $paraValue = clone $this->varCalc($localVar, $opVar);
                $paraValue->cvName = $cvVarName;
                if ($paraValue->if_ref == "REF_VAR") {
                    return $this->refCalc($superglobals, $localVar, $array, $object, $paraValue->refSource, $paraValue->refType, $paraValue->refIndex);
                } else {
                    return $paraValue;
                }
            case IS_ARRAY:
                foreach ($array as $arraykey => $itemobject) {
                    if (isset($itemobject->cvName) and $itemobject->cvName == $cvVarName) {
                        return $array[$arraykey];
                    }
                }
                $paraValue = clone $array[$opVar];
                $paraValue->cvName = $cvVarName;
                if ($paraValue->if_ref == "REF_VAR") {
                    return $this->refCalc($superglobals, $localVar, $array, $object, $paraValue->refSource, $paraValue->refType, $paraValue->refIndex);
                } else {
                    return $array[$opVar];
                }
            case IS_CONST_ARRAY:
                $CONST_ARRAY = $this->constArrayCount($CONSTARRAYINFO[0], $CONSTARRAYINFO[1], $CONSTARRAYINFO[2]);
                $CONST_COUNT = count($CONST_ARRAY);
                $tmpCounter = 0;
                $tmparray = new ArrayStruct();
                foreach ($CONST_ARRAY as $key => $value) {
                    $tmparray->keyArray[$tmpCounter] = new ValueStruct($value);
                    $tmpCounter++;
                }
                return $tmparray;
            case IS_OBJECT:
                foreach ($object as $objectkey => $itemobject) {
                    if (isset($itemobject->cvName) and $itemobject->cvName == $cvVarName) {
                        return $object[$objectkey];
                    }
                }
                $paraValue = clone $object[$opVar];
                $paraValue->cvName = $cvVarName;
                if ($paraValue->if_ref == "REF_VAR") {
                    return $this->refCalc($superglobals, $localVar, $array, $object, $paraValue->refSource, $paraValue->refType, $paraValue->refIndex);
                } else {
                    return $object[$opVar];
                }
            case IS_GLOBAL:
                $cvVarName = $this->getCVName($opVar, $cvVarNamelist);
            case IS_GLOBAL_UNBIND:
                if ($opType == IS_GLOBAL_UNBIND) {
                    $cvVarName = $superglobals->unbind[$opVar];
                }
                $tmp_op2 = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName];
                return $tmp_op2;
            default:
                break;

        }
    }

    public function varClone(&$globalState, &$localVar, &$array, &$object, $opId, $opType, $cloneId)
    {
        switch ($opType) {
            case IS_INIT:
            case IS_INTERFUNC:
            case IS_LOCAL:
                $localVar[$cloneId] = clone $localVar[$opId];
                break;
            case IS_ARRAY:
                $array[$cloneId] = clone $array[$opId];
                break;
            case IS_OBJECT:
                $object[$cloneId] = clone $object[$opId];
                break;
            default:
                break;
        }
    }

    public function valueAssignRef($globalState, $localVar, $array, $object, $opVar, $opType)
    {
        switch ($opType) {
            case IS_LOCAL:
                return $this->varCalc($localVar, $opVar);
            case IS_ARRAY:
                return $array[$opVar];
            case IS_OBJECT:
                return $object[$opVar];
            default:
                break;
        }
    }

    public function localArraySearch($localVar, $array, $id, $cvName)
    {
        foreach ($localVar as $key => $value) {
            if($key == $id or $value->cvName == $cvName){
                return [IS_LOCAL, $value];
            }
        }
        foreach ($array as $key => $value) {
            if($key == $id or $value->cvName == $cvName){
                return [IS_ARRAY, $value];
            }
        }
    }




    public function LOCALSAVE(&$localSave, &$localVar, &$array, &$object){
        $localSave[] = ["localVar" => $localVar, "array" => $array, "object" => $object];
        $localVar = array();
        $array = array();
        $object = array();
    }

    public function LOCALLOAD(&$localSave, &$localVar, &$array, &$object){
        $saveLocal = array_pop($localSave);
        $localVar = $saveLocal["localVar"];
        $array = $saveLocal["array"];
        $object = $saveLocal["object"];
    }


    public function GLOBALSSAVE(&$superglobals, $localVar, $array, $object, $cvVarNamelist){
        foreach ($localVar as $key => $value) {
            if(isset($value->cvName)){
                $superglobals->superGlobalArrays['GLOBALS']->keyArray[$value->cvName] = $value;
            }
        }
        foreach ($array as $key => $value) {
            if (isset($value->cvName)) {
                $superglobals->superGlobalArrays['GLOBALS']->keyArray[$value->cvName] = $value;
            }
        }
        foreach ($object as $key => $value) {
            if (isset($value->cvName)) {
                $superglobals->superGlobalArrays['GLOBALS']->keyArray[$value->cvName] = $value;
            }
        }
        if(!is_null($cvVarNamelist)){
            foreach ($cvVarNamelist as $cvName => $tmpid) {
                if (!isset($superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName])) {
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName] = new ValueStruct();
                }
            }
        }
    }

    public function GLOBALSLOAD(&$superglobals, &$localVar, &$array, &$object, $type){
        foreach ($localVar as $key => &$itemvalueStruct) {
            if(!is_null($itemvalueStruct->cvName) and key_exists($itemvalueStruct->cvName, $superglobals->superGlobalArrays['GLOBALS']->keyArray)){
                if($type == 'include'){
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$itemvalueStruct->cvName] = $itemvalueStruct;
                }else if($type == 'func'){
                    $itemvalueStruct = $superglobals->superGlobalArrays['GLOBALS']->keyArray[$itemvalueStruct->cvName];
                }
            }
        }

        foreach ($array as $key => &$itemarrayStruct) {
            if (!is_null($itemarrayStruct->cvName) and key_exists($itemarrayStruct->cvName, $superglobals->superGlobalArrays['GLOBALS']->keyArray)) {
                if ($type == 'include') {
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$itemarrayStruct->cvName] = $itemarrayStruct;
                } else if ($type == 'func') {
                    $itemarrayStruct = $superglobals->superGlobalArrays['GLOBALS']->keyArray[$itemarrayStruct->cvName];
                }
            }
        }

        foreach ($object as $key => &$itemobjectStruct) {
            if (!is_null($itemobjectStruct->cvName) and key_exists($itemobjectStruct->cvName, $superglobals->superGlobalArrays['GLOBALS']->keyArray)) {
                if ($type == 'include') {
                    $superglobals->superGlobalArrays['GLOBALS']->keyArray[$itemobjectStruct->cvName] = $itemobjectStruct;
                } else if ($type == 'func') {
                    $itemobjectStruct = $superglobals->superGlobalArrays['GLOBALS']->keyArray[$itemobjectStruct->cvName];
                }
            }
        }
        unset($superglobals->unbind);
    }

    public function funcGLOBALBINDRET($superglobals, &$localVar, &$array, &$object)
    {
        if(!is_null($superglobals->unbind)){
            foreach ($superglobals->unbind as $id => $cvName) {
                foreach ($localVar as $key => &$valueStruct) {
                    if($cvName == $valueStruct->cvName){
                        $valueStruct = $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName];
                        break;
                    }
                }
                foreach ($array as $key => &$arrayStruct) {
                    if($cvName == $arrayStruct->cvName){
                        $arrayStruct = $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName];
                        break;
                    }
                }
                foreach ($object as $key => &$objectStruct) {
                    if($cvName == $objectStruct->cvName){
                        $objectStruct = $superglobals->superGlobalArrays['GLOBALS']->keyArray[$cvName];
                        break;
                    }
                }
            }
        }
    }



    public function varCalc($localVar, $opcode)
    {
        if (isset($localVar[$opcode])) {
            $opcode = $localVar[$opcode];
            while (isset($localVar[$opcode->value]) and !is_null($opcode->value)) {
                $opcode = $localVar[$opcode->value];
                if($opcode->value == $localVar[$opcode->value]->value){
                    break;
                }
            }
        }
        if(is_object($opcode)){
            return $opcode;
        }else{
            $localObject = new ValueStruct($opcode);
            return $localObject;
        }
    }

    public function localVarClear(&$localVar, $opcode, &$globalState = NULL){
        if (isset($localVar[$opcode])){
            unset($localVar[$opcode]);
        } else if(isset($globalState[$opcode])){
            unset($globalState[$opcode]);
        }
    }



    public function arrayVarClear(&$array, $opcode){
        if (isset($array[$opcode])){
            unset($array[$opcode]);
        }
    }

    public function VarClear($varType, &$localVar, &$array, &$object, $opcode){
        switch ($varType) {
            case IS_INIT:
            case IS_LOCAL:
                if (isset($array[$opcode])) {
                    unset($array[$opcode]);
                }
                if (isset($object[$opcode])) {
                    unset($object[$opcode]);
                }
                break;
            case IS_CONST_ARRAY:
            case IS_ARRAY:
                if (isset($localVar[$opcode])) {
                    unset($localVar[$opcode]);
                }
                if (isset($object[$opcode])) {
                    unset($object[$opcode]);
                }
                break;
            case IS_OBJECT:
                if (isset($array[$opcode])) {
                    unset($array[$opcode]);
                }
                if (isset($localVar[$opcode])) {
                    unset($localVar[$opcode]);
                }
                break;          
            default:
                break;
        }
    }


    public function arraySave(&$array, $arrayName, $ifArray = 0, $key = NULL, $value = NULL, $CONST_COUNT = NULL)
    {
        if(!isset($array[$arrayName])){
            $array[$arrayName] = new ArrayStruct();
        }
        if(!is_null($CONST_COUNT)){
            $ValueStruct = new ArrayStruct();
            for ($i = 0; $i < $CONST_COUNT; $i++) {
                $ValueStruct->keyArray[$i] = new ValueStruct(0);
            }
            $ValueStruct->indexCount = $CONST_COUNT;
            
        }else{
            $ValueStruct = new ValueStruct($value, 0, NULL, NULL, NULL, $ifArray,  0, NULL, NULL);
        }
        $array[$arrayName]->keyArray[$key] = $ValueStruct;

    }



    public function arraySaveStruct(&$array, $arrayName, $valueStruct, $ifArray = 0, $key = NULL, $keyStruct = NULL)
    {
        if (!isset($array[$arrayName])) {
            $array[$arrayName] = new ArrayStruct();
        }
        if($ifArray){
            $array[$arrayName]->keyArray[$key] = clone $array[$valueStruct->value];
        }else{
            $array[$arrayName]->keyArray[$key] = clone $valueStruct;
        }
        if(!is_null($keyStruct)){
            $array[$arrayName]->keyVarList[$key] = clone $keyStruct;
        }
    }



    
    public function array2ArrayStruct($array)
    {
        $result = new ArrayStruct();
        foreach ($array as $key => $value) {
            if(is_array($value)){
                $result2 = new ArrayStruct();
                foreach ($value as $key2 => $value2) {
                    if (is_array($value2)) {
                        foreach ($value2 as $key3 => $value3) {
                            $ifArray = 1;
                            if(is_object($value3)){
                                $result2[$key2]->keyArray[$key3] = $value3;
                            }else{
                                $result2[$key2]->keyArray[$key3] = new ValueStruct($value3, 0, NULL, NULL, NULL, $ifArray,  0, NULL, NULL);
                            }
                        }
                    }else{
                        if (is_object($value2)) {
                            $result2->keyArray[$key2] = $value2;
                        } else {
                            $result2->keyArray[$key2] = new ValueStruct($value2, 0, NULL, NULL, NULL, 0,  0, NULL, NULL);
                        }
                    }
                }
                $result->keyArray[$key] = $result2;
            }else{
                if (is_object($value)) {
                    $result->keyArray[$key] = $value;
                } else {
                    $result->keyArray[$key] = new ValueStruct($value, 0, NULL, NULL, NULL, 0,  0, NULL, NULL);
                }
            }
        }
        return $result;
    }




    
    public function objectSave(&$globalState, &$object, $objectName, $key = NULL, $ValueStruct = NULL, $thisObjectVarId = NULL)
    {
        if(empty($objectName) and isset($globalState->object[$thisObjectVarId])){
            $globalState->object[$thisObjectVarId]->propertiesArray[$key] = $ValueStruct;
        }else{
            if (!isset($object[$objectName])) {
                $object[$objectName] = new ObjectStruct();
            }
            $object[$objectName]->propertiesArray[$key] = $ValueStruct;
        }
    }



    
    public function arrayCalc($array, $arrayName, $key=NULL)
    {
        if(isset($array[$arrayName])){
            if ($array[$arrayName]->if_ref == "REF_VAR") {
                return $this->arrayCalc($array, $array[$arrayName]->refSource, $key);
            } else if (!empty($array[$arrayName]->keyArray[$key]->if_ref) && $array[$arrayName]->keyArray[$key]->if_ref == "REF_VAR") {
                if ($array[$arrayName]->keyArray[$key]->refType == "IS_LOCAL" or $array[$arrayName]->keyArray[$key]->refType == "GLOBAL_LOCAL") {
                    return array($arrayName, $key);
                }
                return $this->arrayCalc($array, $array[$arrayName]->keyArray[$key]->refSource, $array[$arrayName]->keyArray[$key]->refIndex);
            } else {
                return array($arrayName, $key);
            }
        }else{
            return array($arrayName, $key);
        }


    }


    
    public function &assign_opDeal(&$localVar, &$array, $op1, $op2, $extended_value, $dimFlag = 0, &$tmp_array = NULL)
    {
        if($dimFlag)
        {
            $tmp_array['OP_DATA'] = [$op1, $op2, $extended_value];
            return $tmp_array['OP_DATA'];
        }
        else
        {
            $tmp_op1 = $this->varCalc($localVar, $op1);
            $tmp_op2 = $this->varCalc($localVar, $op2);
            if(!isset($localVar[$op1])){
                $localVar[$op1] = $tmp_op2;
                return $localVar[$op1];
            }else{
                $localVar[$op1]->value = $this->calc($tmp_op1->value, $tmp_op2->value, $extended_value);
                return $localVar[$op1];
            }

        }
    }



    
    public function multiArrayHandleGet($tmp_array, $op1, $op2=NULL)
    {
        $tmp_op1 = $op1;
        if(!is_null($op2)){
            $tmp_op2 = $op2;
            $midItem = [$tmp_op2]; 
        }else{
            $midItem = [];
        }
        
        
        while(isset($tmp_array[$tmp_op1])){
            $tmp_op2 = $tmp_array[$tmp_op1][1];
            $tmp_op1 = $tmp_array[$tmp_op1][0];
            array_push($midItem, $tmp_op2);
        }

        $midItem = array_reverse($midItem);
        $multiArray = [$tmp_op1]; 
        $multiArray = array_merge($multiArray, $midItem);
        return $multiArray;
    }



    
    public function multiArrayWrite(&$local, &$array, $tmp_mulit_array, $op1)
    {
        $dimension = count($tmp_mulit_array);
        $arrayVar = $tmp_mulit_array[0];
        for ($i=1; $i < $dimension; $i++) { 
            if(is_object($arrayVar)){
                $tmparrayVar = $arrayVar;
            }else{
                $tmparrayVar = $array[$arrayVar];
                
            }
            $tmpkey = $tmp_mulit_array[$i];

            if($tmparrayVar->keyArray[$tmpkey]->if_ref == "REF_VAR"){
                if($tmparrayVar->keyArray[$tmpkey]->refType == "IS_LOCAL"){
                    $refSource = $tmparrayVar->keyArray[$tmpkey]->refSource;
                    $local[$refSource] = $op1; 
                }else if($tmparrayVar->keyArray[$tmpkey]->refType == "IS_ARRAY"){
                    $arrayVar = $tmparrayVar->keyArray[$tmpkey]->refSource;
                }else if($tmparrayVar->keyArray[$tmpkey]->refType == "ARRAYITEM"){
                    $refSource = $tmparrayVar->keyArray[$tmpkey]->refSource;
                    $tmpkey = $tmparrayVar->keyArray[$tmpkey]->refIndex;
                    $tmparrayVar = $array[$refSource];
                    if (get_class($tmparrayVar->keyArray[$tmpkey]) == "ArrayStruct") {
                        $arrayVar = $tmparrayVar->keyArray[$tmpkey]->value; 
                    } else {
                        $tmparrayVar->keyArray[$tmpkey]->valuePassFromLocal($local[$op1]);
                    }
                }
            }
            else{
                if(isset($tmparrayVar->keyArray[$tmpkey])){
                    break;
                }
                if(get_class($tmparrayVar->keyArray[$tmpkey]) == "ArrayStruct"){
                    $arrayVar = $tmparrayVar->keyArray[$tmpkey]; 
                }else{
                    $tmparrayVar->keyArray[$tmpkey]->valuePassFromLocal($local[$op1]);
                }
            }
        }

    }


    
    public function multiArrayRefAssign($cvVarNamelist, $superglobals, &$local, &$array, $tmp_mulit_array, $op2, $op2Key=NULL)
    {
        $dimension = count($tmp_mulit_array);
        $arrayVar = $tmp_mulit_array[0];

        for ($i = 1; $i < $dimension; $i++) {
            $tmparrayVar = $array[$arrayVar];
            $tmpkey = $tmp_mulit_array[$i];
            if ($tmparrayVar->keyArray[$tmpkey]->if_ref == "REF_VAR") {
                if ($tmparrayVar->keyArray[$tmpkey]->refType == "IS_LOCAL") {
                    $refSource = $tmparrayVar->keyArray[$tmpkey]->refSource;
                    $local[$refSource] = $op2; 
                } else if ($tmparrayVar->keyArray[$tmpkey]->refType == "IS_ARRAY") {
                    $arrayVar = $tmparrayVar->keyArray[$tmpkey]->refSource;
                } else if ($tmparrayVar->keyArray[$tmpkey]->refType == "ARRAYITEM") {
                    $refSource = $tmparrayVar->keyArray[$tmpkey]->refSource;
                    $tmpkey = $tmparrayVar->keyArray[$tmpkey]->refIndex;
                    $tmparrayVar = $array[$refSource];
                    if (get_class($tmparrayVar->keyArray[$tmpkey]) == "ArrayStruct") {
                        $arrayVar = $tmparrayVar->keyArray[$tmpkey]->value; 
                    } else {
                        if ($this->varClassify($cvVarNamelist, $superglobals, $local, $array, NULL, $op2) == IS_LOCAL) {
                            $tmparrayVar->keyArray[$tmpkey] = new ValueStruct();
                            $tmparrayVar->keyArray[$tmpkey]->refSave("REF_VAR", $op2, 'IS_LOCAL', 0);
                        } else if ($this->varClassify($cvVarNamelist, $superglobals, $local, $array, NULL, $op2) == IS_ARRAY) {
                            if(!is_null($op2Key)){
                                $tmparrayVar->keyArray[$tmpkey] = new ValueStruct();
                                $tmparrayVar->keyArray[$tmpkey]->refSave("REF_VAR", $op2, 'ARRAYITEM', 0 , $op2Key);
                            }else{
                                $tmparrayVar->keyArray[$tmpkey] = new ValueStruct();
                                $tmparrayVar->keyArray[$tmpkey]->refSave("REF_VAR", $op2, 'IS_ARRAY');
                            }
                        }
                    }
                }
            }
            else {
                if (get_class($tmparrayVar->keyArray[$tmpkey]) == "ArrayStruct") {
                    $arrayVar = $tmparrayVar->keyArray[$tmpkey]->value; 
                } else {
                    if ($this->varClassify($cvVarNamelist, $superglobals, $local, $array, NULL, $op2) == IS_LOCAL) {
                        $tmparrayVar->keyArray[$tmpkey] = new ValueStruct();
                        $tmparrayVar->keyArray[$tmpkey]->refSave("REF_VAR", $op2,'IS_LOCAL', 0);
                    } else if ($this->varClassify($cvVarNamelist, $superglobals, $local, $array, NULL, $op2) == IS_ARRAY) {
                        if (!is_null($op2Key)) {
                            $tmparrayVar->keyArray[$tmpkey] = new ValueStruct();
                            $tmparrayVar->keyArray[$tmpkey]->refSave("REF_VAR", $op2, 'ARRAYITEM', 0, $op2Key);
                        } else {
                            $tmparrayVar->keyArray[$tmpkey] = new ValueStruct();
                            $tmparrayVar->keyArray[$tmpkey]->refSave("REF_VAR", $op2, 'IS_ARRAY');
                        }
                    }
                }
            }
        }
    }



    
    public function magicMethodOpArrayAdd($opcodeNo,$opcodeName,$lineNo,$op1,$op1_type, $op2 = "", $op2_type = "", $result = "", $result_type = "", $extended_value = "", $fetch_flag = "", $jmp_ext = "")
    {
        return new Opcode($opcodeNo, $opcodeName, $lineNo, $op1, $op1_type, $op2, $op2_type, $result, $result_type, $extended_value, $fetch_flag, $jmp_ext);
    }


    
    public function file_get_contents_formTo($fileAddr, $start, $end)
    {
        $filecontents = "";
        $file = new SplFileObject($fileAddr);
        $file->seek($start-1);
        for ($i=$start; $i <= $end; $i++) {
            $filecontents .= $file->fgets();
            $file->next();
        }
        return $filecontents;
    }


    
    public function constArrayCount($fileAddr, $start, $end)
    {
        $patternArray1 = "/=[ ]?array\([^;]+[,]?\);/s";
        $patternArray2 = "/array\(([^;]+[,]?)\);/s";
        $patternBrackets1 = "/=[ ]?\[[^;]+[,]?\];/s";
        $patternBrackets2 = "/\[([^;]+[,]?)\];/s";
        $patternPara1 = "/array\s*\(\s*(.*?)\s*\)/";
        $patternPara2 = "/'(.*?)'/";
        $content = $this->file_get_contents_formTo($fileAddr, $start, $end);
        if (preg_match_all($patternArray1, $content, $match)) {
            foreach ($match[0] as $item) {
                if (preg_match_all($patternArray2, $item, $atom)) {
                    $result = explode(",", $atom[1][0]);
                    $tmparryCount = count($result);
                    if($tmparryCount > 10000){
                        return array();
                    }
                    foreach ($result as $key => &$value) {
                        $value = trim($value);
                        $value = trim($value,"'");
                        $value = trim($value,'"');
                    }
                    return $result;
                }
            }
        }
        if (preg_match_all($patternBrackets1, $content, $match)) {
            foreach ($match[0] as $item) {
                if (preg_match_all($patternBrackets2, $item, $atom)) {
                    $result = explode(",", $atom[1][0]);
                    $tmparryCount = count($result);
                    if ($tmparryCount > 10000) {
                        return array();
                    }
                    foreach ($result as $key => &$value) {
                        $value = trim($value);
                        $value = trim($value,"'");
                        $value = trim($value,'"');
                    }
                    return $result;
                }
            }
        }
        if (preg_match_all($patternPara1, $content, $match)) {
            foreach ($match[0] as $item) {
                if (preg_match_all($patternPara2, $item, $atom)) {
                    $result = $atom[1];
                    $tmparryCount = count($result);
                    if ($tmparryCount > 10000) {
                        return array();
                    }
                    foreach ($result as $key => &$value) {
                        $value = trim($value);
                        $value = trim($value,"'");
                        $value = trim($value,'"');
                    }
                    return $result;
                }
            }
        }
        return array();
    }



    
    public function calc($op1, $op2, $opcodeNo)
    {
        switch ($opcodeNo) {
            case ZEND_ADD:
                return $op1 + $op2;
            case ZEND_SUB:
                return $op1 - $op2;
            case ZEND_MUL:
                return $op1 * $op2;
            case ZEND_DIV:
                return $op1 / $op2;
            case ZEND_MOD:
                return $op1 % $op2;
            case ZEND_SL:
                return $op1 << $op2;
            case ZEND_SR:
                return $op1 >> $op2;
            case ZEND_CONCAT:
                return $op1 . $op2;
            case ZEND_BW_OR:
                return (int)$op1 | (int)$op2;
            case ZEND_BW_AND:
                return (int)$op1 & (int)$op2;
            case ZEND_BW_XOR:
                return (int)$op1 ^ (int)$op2;
            case ZEND_POW:
                return $op1 ** $op2;
            case ZEND_BOOL_XOR:
                return $op1 xor $op2;
            
            default:
                break;
        }
    }


    
    public function getOperator($opcodeNo)
    {
        switch ($opcodeNo) {
            case ZEND_ADD:
                return '+';
            case ZEND_SUB:
                return '-';
            case ZEND_MUL:
                return '*';
            case ZEND_DIV:
                return '/';
            case ZEND_MOD:
                return '%';
            case ZEND_SL:
                return '<<';
            case ZEND_SR:
                return '>>';
            case ZEND_BW_OR:
                return '|';
            case ZEND_BW_AND:
                return '&';
            case ZEND_BW_XOR:
                return '^';
            case ZEND_POW:
                return '**';
            case ZEND_BOOL_XOR:
                return 'xor';
            default:
                break;
        }
    }









    
    public function taint_propagate_op2(&$localVar, &$taintedChains, $op1Index, $op2Index, $currentLineNo)
    {
        $tmp_op2 = $this->varCalc($localVar, $op2Index);
        if ($tmp_op2->if_tainted) {
            $localVar[$op1Index]->taintInfoSave(1, $op2Index, $tmp_op2->taintedSourceLine, $tmp_op2->taintedLine);
            $taintedChains = $this->taintedChainAdd($taintedChains, $localVar[$op1Index]->taintedSourceLine, $localVar[$op1Index]->taintedLine, $currentLineNo);
            $localVar[$op1Index]->taintedLine = $currentLineNo;
        }
    }


    
    public function taint_propagate_op1_op2($localVar, &$taintedChains, $op1Index, $op2Index, $currentLineNo, $op1Var, $op2Var = NULL)
    {
        $result = array(0, array(), NULL, NULL);
        if (!is_null($op2Var) and get_class($op2Var) == 'ValueStruct') {
            if (!empty($op2Var->value) and isset($localVar[$op2Var->value])) {
                $tmp_op2 = $localVar[$op2Var];
            } else {
                $tmp_op2 = $op2Var;
            }
        } else {
            $tmp_op2 = 0;
        }

        if (!get_class($op1Var) == 'ValueStruct') {
            $tmp_op1 = $localVar[$op1Var];
        } else {
            $tmp_op1 = $op1Var;
        }

        if ($tmp_op1->if_tainted) {
            $result[0] = 1;
            array_push($result[1], $op1Index);
            $result[2] = $tmp_op1->taintedSourceLine;
            $result[3] = $currentLineNo;
            $taintedChains = $this->taintedChainAdd($taintedChains, $tmp_op1->taintedSourceLine, $tmp_op1->taintedLine, $currentLineNo);
        }
        if (isset($tmp_op2->if_tainted) and $tmp_op2->if_tainted) {
            $result[0] = 1;
            array_push($result[1], $op2Index);
            $result[2] = $tmp_op2->taintedSourceLine;
            $result[3] = $currentLineNo;
            $taintedChains = $this->taintedChainAdd($taintedChains, $tmp_op2->taintedSourceLine, $tmp_op2->taintedLine,$currentLineNo);
        }
        return $result;
    }



    
    public function taint_propagate_array_value(&$array, $arrayName, $key, $value)
    {
        if($value->if_tainted == 1){
            $array[$arrayName]->keyArray[$key]->if_tainted = 1;
            $array[$arrayName]->keyArray[$key]->taintedSource = $value->taintedSource;
            $array[$arrayName]->keyArray[$key]->taintedSourceLine = $value->taintedSourceLine;
            $array[$arrayName]->keyArray[$key]->taintedLine = $value->taintedLine;
            $array[$arrayName]->if_tainted = 1;
            $array[$arrayName]->taintedSource = $value->taintedSource;
            $array[$arrayName]->taintedSourceLine = $value->taintedSourceLine;
            $array[$arrayName]->taintedLine = $value->taintedLine;
        }
    }




    
    public function taintedChianNew(&$taintedChains, $sourceLine){
        $flag = 1;
        if(!empty($taintedChains)){
            foreach ($taintedChains as &$chainItem) {
                if ($chainItem[0] == $sourceLine) {
                    $flag = 0;
                    break;
                }
            }
        }
        if($flag){
            $taintedChains[] = array($sourceLine);
        }
    }


    
    public function taintedChainAdd($taintedChains, $sourceLine, $tailLineNo, $nextLineNo){
        $resultChains = [];
        foreach ($taintedChains as &$chainItem) {
            if ($chainItem[0] == $sourceLine and end($chainItem) == $tailLineNo) {
                array_push($chainItem, $nextLineNo);
                $chainItem = array_unique($chainItem);
                $chainItem = array_values($chainItem);  
            } else {
                $length = count($chainItem);
                for ($i = 0; $i < $length; $i++) {
                    if ($chainItem[$i] == $sourceLine) {
                        for ($j = $i; $j < $length; $j++) {
                            if ($chainItem[$j] == $tailLineNo) {
                                $new_chain = array_slice($chainItem, $i, $j - $i + 1);
                                array_push($new_chain, $nextLineNo);
                                $new_chain = array_unique($new_chain);
                                if (!in_array($new_chain, $taintedChains) and !in_array($new_chain, $resultChains)) {
                                    $resultChains[] = $new_chain;
                                }
                            }
                        }
                        break;
                    }
                }
            }
        }
        $resultChains = array_merge($taintedChains, $resultChains);
        return $resultChains;
    }


    
    public function taintedChainSearch(&$vulChains, $vulType, &$taintedChains, $sourceLine, $tailLine, $nextLineNo, $DebugFlag=False)
    {
        $tmpTaintedChains = [];
        foreach ($taintedChains as &$chainItem) {
            if($chainItem[0] == $sourceLine and end($chainItem) == $tailLine){
                array_push($chainItem, $nextLineNo);
                $chainItem = array_unique($chainItem);
                $length = count($chainItem);
                $this->taintedChainDisplay($chainItem, 0, $length-1, $DebugFlag);
                $vulChainItem = $chainItem;
                $vulChains[] = [$vulType, $vulChainItem];
            }else if($chainItem[0] == $sourceLine and $sourceLine == $tailLine){
                $tmpChainItem = [$sourceLine, $nextLineNo];
                if(!in_array($tmpChainItem, $tmpTaintedChains)){
                    array_push($tmpTaintedChains, $tmpChainItem);
                }
            }else{
                $length = count($chainItem);
                for ($i=0; $i < $length; $i++) { 
                    if ($chainItem[$i] == $sourceLine){
                        for ($j = $i; $j < $length; $j++) {
                            if ($chainItem[$j] == $tailLine) {
                                $new_chain = array_slice($chainItem, $i, $j - $i + 1);
                                array_push($new_chain, $nextLineNo);
                                $new_chain = array_unique($new_chain);
                                $new_chain_length = count($new_chain);
                                $this->taintedChainDisplay($new_chain, 0, $new_chain_length - 1, $DebugFlag);
                                $vulChainItem = $new_chain;
                                $vulChains[] = [$vulType, $new_chain];
                            }
                        }
                    }
                }
            }
        }
        if(!empty($tmpTaintedChains)){
            foreach ($tmpTaintedChains as $tmpchainItem) {
                $length = count($tmpchainItem);
                $this->taintedChainDisplay($tmpchainItem, 0, $length - 1, $DebugFlag);
                $vulChainItem = $tmpchainItem;
                $vulChains[] = [$vulType, $tmpchainItem];
            }
        }
    }


    
    public function taintedChainDisplay($chainItem, $start, $end, $DebugFlag=False){
        $chainStart = '[+] TaintedChain: ' . $chainItem[$start];
        for ($i=$start+1; $i <= $end; $i++) { 
            $chainStart = $chainStart. '->' .$chainItem[$i];
        }
        $chainStart = $chainStart . "\n";
        debugEcho($chainStart, $DebugFlag, 'red');
    }

    

    
    public function catchCheck($opArray){
        foreach ($opArray as $key => $value) {
            if($value->opcodeName == "CATCH"){
                return $key;
            }
        }
        return FALSE;
    }



    
    public function classInit($exceptionState, $includedFileObject, $ObjectOpArray, &$objectItem, $className)
    {
        if (is_string($ObjectOpArray) and $ObjectOpArray == "EXCEPTIONS") {
            $exceptionState->exceptionFlag = 1;
        } else if (is_array($ObjectOpArray)) {
            $objectItem->anonymousClassInfo = $ObjectOpArray;
        } else {
            $objectItem->propertyAssign($ObjectOpArray->properties_info);
        }
        if (!is_object($ObjectOpArray) and $ObjectOpArray == 'ERROR') {
            foreach ($includedFileObject as $obj) {
                $ObjectOpArray = $this->classSearch($className, $obj->classOpArray);
                if (is_object($ObjectOpArray)) {
                    $objectItem->funcOpArray = $ObjectOpArray->funcOpArray;
                }
            }
        } else if ($ObjectOpArray == 'INTERNAL') {
            $internalClassMethods = get_class_methods($className);
            foreach ($internalClassMethods as $method) {
                $objectItem->funcOpArray[$method] = new FuncInfo($method);
                $objectItem->funcOpArray[$method]->funcType = 'INTERNAL';
            }
        } else if (is_object($ObjectOpArray)) {
            $objectItem->funcOpArray = $ObjectOpArray->funcOpArray;
        }
    }

    public function classSearch($className, $ClassOpArray)
    {
        global $INTERNALCLASS;
        if (isset($ClassOpArray[$className])) {
            return $ClassOpArray[$className];
        } else if (in_array($className, $INTERNALCLASS)) {
            return $this->internalClassClassify($className);
        } else if ($className == "USERCONTROLLABLE"){
            return "USERCONTROLLABLE";
        }
        else {
            return "ERROR";
        }
    }


    
    public function classTraitSearch($className, $ClassOpArray)
    {
        if(isset($ClassOpArray[$className]) and isset($ClassOpArray[$className]->classTraitName)){
            return $ClassOpArray[$className]->classTraitName;
        }else{
            return [];
        }
    }


    
    public function classTraitMethodMatch($ClassOpArray, $classTraitNameArray, $methodName)
    {
        foreach ($classTraitNameArray as $traitNameItem) {
            if(isset($ClassOpArray[$traitNameItem])){
                $classObjectItem = $ClassOpArray[$traitNameItem];
                foreach ($classObjectItem->funcOpArray as $funcItem) {
                    if($methodName == $funcItem->funcName){
                        return [$traitNameItem,$funcItem];
                    }
                }
            }
        }
    }


    
    public function internalClassClassify($className){
        global $EXCEPTIONSCLASSLIST;
        if (in_array($className, $EXCEPTIONSCLASSLIST)) {
            return 'EXCEPTIONS';
        }else{
            return 'INTERNAL';
        }
    }



    
    public function methodSearch($methodName, $classOpArray)
    {
        foreach ($classOpArray as $classInfo) {
            foreach ($classInfo->funcOpArray as $funcInfo) {
                if(strtolower($methodName) == $funcInfo->funcName){
                    return $classInfo;
                }
            }
        }
        return "ERROR";
    }


    
    public function funcSearch($funcInitName, $FuncOpArray, $className = NULL)
    {
        global $INTERNALFUNCLIST;
        $funcInitName_lower = strtolower($funcInitName);
        if(isset($FuncOpArray[$funcInitName])){
            return $FuncOpArray[$funcInitName];
        }else if(isset($FuncOpArray[$funcInitName_lower])){
            return $FuncOpArray[$funcInitName_lower];
        }
        else if(in_array($funcInitName, $INTERNALFUNCLIST) or $this->internalFuncClassify($funcInitName, $className)){
            $result = $this->internalFuncClassify($funcInitName, $className);
            array_unshift($result,$funcInitName_lower);
            return $result;
        }else{
            return "ERROR";
        }
    }

    


    public function internalFuncClassify($funcInitName,$className=NULL){
        global $XSSRISKFUNCLIST;
        global $SQLRISKFUNCLIST;
        global $CERISKFUNCLIST;
        global $FDELRISKFUNCLIST;
        global $FIRISKFUNCLIST;
        global $FURISKFUNCLIST;
        global $INFOLEAKFUNCLIST;
        global $SAFEFUNCLIST;
        global $SETCOOKIEFUNCLIST;
        global $ENCODEFUNCLIST;
        global $DECODEFUNCLIST;
        global $TAINTFUNCLIST;
        global $ARRAYFUNCLIST;


        global $ARRAYMODELINGLFUNCLIST;
        global $DIYLFUNCLIST;
        global $OBFUNCLIST;
        global $FILESYSFUNCLIST;

        global $ERRORSETFUNCLIST;
        global $ERRORGETFUNCLIST;

        global $XSSRISKCLASSFUNCLIST;
        if (key_exists($funcInitName, $XSSRISKFUNCLIST)) {
            $result = $XSSRISKFUNCLIST[$funcInitName];
            array_unshift($result, "XSS");
            return $result;
        }
        if (key_exists($className, $XSSRISKCLASSFUNCLIST)) {
            $result = $XSSRISKCLASSFUNCLIST[$className][$funcInitName];
            array_unshift($result, "XSS");
            return $result;
        }
        if (key_exists($funcInitName, $ERRORSETFUNCLIST)) {
            $result = $ERRORSETFUNCLIST[$funcInitName];
            array_unshift($result, "ERRORSET");
            return $result;
        }
        if (key_exists($funcInitName, $ERRORGETFUNCLIST)) {
            $result = $ERRORGETFUNCLIST[$funcInitName];
            array_unshift($result, "ERRORGET");
            return $result;
        }
        if (key_exists($funcInitName, $SQLRISKFUNCLIST)) {
            $result = $SQLRISKFUNCLIST[$funcInitName];
            array_unshift($result, "SQLI");
            return $result;
        }
        if (key_exists($funcInitName, $CERISKFUNCLIST)) {
            $result = $CERISKFUNCLIST[$funcInitName];
            array_unshift($result, "CE");
            return $result;
        }
        if (key_exists($funcInitName, $DIYLFUNCLIST)) {
            $result = $DIYLFUNCLIST[$funcInitName];
            array_unshift($result, "DIY");
            return $result;
        }
        if (key_exists($funcInitName, $FDELRISKFUNCLIST)) {
            $result = $FDELRISKFUNCLIST[$funcInitName];
            array_unshift($result, "AFD");
            return $result;
        }
        if (key_exists($funcInitName, $FIRISKFUNCLIST)) {
            $result = $FIRISKFUNCLIST[$funcInitName];
            array_unshift($result, "FI");
            return $result;
        }
        if (key_exists($funcInitName, $FURISKFUNCLIST)) {
            $result = $FURISKFUNCLIST[$funcInitName];
            array_unshift($result, "UFU");
            return $result;
        }
        if (key_exists($funcInitName, $INFOLEAKFUNCLIST)) {
            $result = $INFOLEAKFUNCLIST[$funcInitName];
            array_unshift($result, "SDE");
            return $result;
        }
        if (in_array($funcInitName, $OBFUNCLIST)) {
            return ["OB",[],[]];
        }
        if (in_array($funcInitName, $FILESYSFUNCLIST)) {
            return ["FILESYS",[],[]];
        }
        if (in_array($funcInitName, $SAFEFUNCLIST)) {
            return ["SAFE",[],[]];
        }
        if (in_array($funcInitName, $SETCOOKIEFUNCLIST)) {
            return ["SETCOOKIE",[],[]];
        }
        if (key_exists($funcInitName, $ENCODEFUNCLIST)) {
            $result = $ENCODEFUNCLIST[$funcInitName];
            array_unshift($result, "ENCODE");
            return $result;
        }
        if (key_exists($funcInitName, $DECODEFUNCLIST)) {
            $result = $DECODEFUNCLIST[$funcInitName];
            array_unshift($result, "DECODE");
            return $result;
        }
        if (key_exists($funcInitName, $TAINTFUNCLIST)) {
            $result = $TAINTFUNCLIST[$funcInitName];
            array_unshift($result, "TAINT");
            return $result;
        }
        if (key_exists($funcInitName, $ARRAYMODELINGLFUNCLIST)) {
            $result = $ARRAYMODELINGLFUNCLIST[$funcInitName];
            array_unshift($result, "ARRAYMODELING");
            return $result;
        }
        if (key_exists($funcInitName, $ARRAYFUNCLIST)) {
            $result = $ARRAYFUNCLIST[$funcInitName];
            array_unshift($result, "ARRAY");
            return $result;
        }
    }




    
    public function shortNameSearch(&$FileFuncOpArray, $shortName)
    {
        foreach ($FileFuncOpArray as $key => $item) {
            if($item->shortfuncName == $shortName){
                $FileFuncOpArray[$shortName] = clone $FileFuncOpArray[$key];
                $FileFuncOpArray[$shortName]->funcName = $shortName;
                break;
            }
        }
    }
    

    public function fileSysFuncDeal(&$includePaths, &$currentWorkPath, $funcCallState, &$localVar, &$array, &$object, &$result, &$superglobals)
    {
        $funcName = $funcCallState->funcInfo[0];
        switch ($funcName) {
            case 'get_include_path':
                $tmp_result = new ValueStruct(get_include_path());
                $this->funcParaAndReturnPass($funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_result);
                break;
            case 'set_include_path':
                $includePaths = $funcCallState->funcparameters[0][1]->value;
                set_include_path($includePaths);
                break;
            case 'chdir':
                $currentWorkPath = urldecode($funcCallState->funcparameters[0][1]->value);
                break;
            case 'define':
                $CONSTNAME = $funcCallState->funcparameters[0][1]->value;
                $CONSTVALUE = urldecode($funcCallState->funcparameters[1][1]->value);
                $localVar[$CONSTNAME] = new ValueStruct($CONSTVALUE);
                $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$CONSTNAME] = new ValueStruct($CONSTVALUE);
                break;
            case 'dirname':
                $tmp_result = new ValueStruct(dirname(urldecode($funcCallState->funcparameters[0][1]->value)));
                $this->funcParaAndReturnPass($funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_result);
                break;
            case 'basename':
                $tmp_result = new ValueStruct(basename(urldecode($funcCallState->funcparameters[0][1]->value)));
                $this->funcParaAndReturnPass($funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_result);
                break;
            
            default:
                break;
        }
    }


    

    public function arrayModelingFuncDeal($compiledVars, &$localVar, &$array, $funcName, $sinkParameters, $func_param_array = NULL, $DebugFlag = false)
    {
        $result = new ArrayStruct();
        switch ($funcName) {
            case 'array_keys':
                foreach ($sinkParameters as $item) {
                    foreach ($item[1]->keyArray as $key => $value) {
                        $result->keyArray[] = new ValueStruct($key);
                    }
                }
                return $result;
            case 'parse_str':
                $paraCount = count($func_param_array);
                if($paraCount == 1){
                    debugEcho("[DEBUG]Possible Vulnerable FOUND!\n", $DebugFlag);
                    return "POSSIBLE_VUL";
                }else if($paraCount == 2){
                    parse_str(urldecode($func_param_array[0][1]->value), $output);
                    return $output;
                }   
            case 'compact':
                $var_name = $sinkParameters[0][1]->value;
                if(isset($compiledVars[$var_name])){
                    $cvVarId = $compiledVars[$var_name];
                }else if(isset($sinkParameters[0][3])){
                    $cvVarId = $sinkParameters[0][3];
                }
                
                $result = clone $func_param_array[1][1];
                $result->keyArray[$var_name] = $localVar[$cvVarId];
                return $result;
            case 'extract':
                foreach ($sinkParameters[0][1]->keyArray as $key => $value) {
                    $cvVarId = $compiledVars[$key];
                    if(!is_object($value)){
                        $localVar[$cvVarId] = new ValueStruct($value);
                    }else{
                        $localVar[$cvVarId] = $value;
                    }
                }
                return NULL;
            case 'sort':
                $tmp_array = array();
                $tmp2_array = array();
                foreach ($sinkParameters[0][1]->keyArray as $value) {
                    $tmp_array[$value->value] = $value;
                }
                ksort($tmp_array);
                foreach ($tmp_array as $value) {
                    $tmp2_array[] = $value;
                }
                $tmp_VarValue = $this->array2ArrayStruct($tmp2_array);
                $array[$sinkParameters[0][3]] = $tmp_VarValue;
                return NULL;
            case 'array_pad':
                $oldCount = count($func_param_array[0][1]->keyArray);
                $newAddCount = $func_param_array[1][1]->value - $oldCount;
                for ($i=0; $i < $newAddCount; $i++) {
                    $func_param_array[0][1]->keyArray[] = $func_param_array[2][1];
                }
                $result = $func_param_array[0][1];
                return $result;
            case 'in_array':
                foreach ($func_param_array[1][1]->keyArray as $key => $valueStruct) {
                    if($func_param_array[0][1]->value == $valueStruct->value){
                        return new ValueStruct(1);
                    }
                }
                return new ValueStruct(0);
            case 'preg_match':
            case 'preg_match_all':
                if($func_param_array[1][1]->if_tainted){
                    $tmpid = $func_param_array[2][3];
                    $array[$tmpid] = new ArrayStruct();
                    $array[$tmpid]->keyArray[0] = new ValueStruct();
                    $array[$tmpid]->keyArray[0]->taintInfoSave($func_param_array[1][1]->if_tainted, $func_param_array[1][1]->taintedSource, $func_param_array[1][1]->taintedSourceLine, $func_param_array[1][1]->taintedLine);
                }
                return 'break';
                break;
            default:
                break;
        }
    }


    

    public function obFuncModelingDeal($funcCallState, &$obState, &$opArray, &$thisopId, &$opcodeCount, &$methodMagicChangeBBCount){
        $funcName = $funcCallState->funcInfo[0];
        switch ($funcName) {
            case 'ob_start':
                $obState->obFlag = 1;
                break;
            case 'ob_clean':
                unset($obState->obOpArray);
                break;
            case 'ob_end_clean':
                $obState->obFlag = 0;
                unset($obState->obOpArray);
                break;
            case 'ob_flush':
                $setOpArray = $obState->obOpArray;
                $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                $methodMagicChangeBBCount = count($setOpArray);
                unset($obState->obOpArray);
                break;
            case 'ob_end_flush':
                $setOpArray = $obState->obOpArray;
                $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                $methodMagicChangeBBCount = count($setOpArray);
                unset($obState->obOpArray);                
                $obState->obFlag = 0;
                break;
            case 'ob_get_contents':
                break;
            case 'ob_get_clean':
            case 'ob_get_flush':
                $setOpArray = $obState->obOpArray;
                $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                $methodMagicChangeBBCount = count($setOpArray);
                $obState->obNotOutPutFlag = 0;
                unset($obState->obOpArray);                
                $obState->obFlag = 0;
                break;
            default:
                break;
        }
    }


    

    public function obFuncEndDefault(&$obState, &$opArray, &$thisopId, &$opcodeCount, &$methodMagicChangeBBCount){
        $setOpArray = $obState->obOpArray;
        $opPreArray = array_slice($opArray, 0, $thisopId + 1);
        $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
        $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
        $methodMagicChangeBBCount = count($setOpArray);
        $obState->obNotOutPutFlag = 0;
        unset($obState->obOpArray);
        $obState->obFlag = 0;
    }

    


    
    public function internalClassMethodDeal($funcCallState, &$localVar, &$array, &$object, &$result)
    {
        $className = $funcCallState->className;
        switch ($className) {
            case 'PDO':
            case 'PDOStatement':
                $this->PDOFuncModelingDeal($funcCallState, $localVar, $array, $object, $result);
                break;
            default:
                break;
        }
    }


    public function PDOFuncModelingDeal($funcCallState, &$localVar, &$array, &$object, &$result)
    {
        $methodName = $funcCallState->methodName;
        switch ($methodName) {
            case 'prepare':
                $object[$result] = new ObjectStruct("PDOStatement");
                $object[$result]->internalMethodInit();
                $object[$result]->propertiesArray['queryString'] = $funcCallState->funcparameters[0][1]->value;
                break;
            case 'bindparam':
                $subject = $object[$funcCallState->thisObjectVarId]->propertiesArray['queryString'];
                $search = urldecode($funcCallState->funcparameters[0][1]->value);
                $replace = urldecode($funcCallState->funcparameters[1][1]->value);
                $object[$funcCallState->thisObjectVarId]->propertiesArray['queryString'] = str_replace($search, $replace, $subject);
                break;
            case 'execute':
                break;
            
            default:
                break;
        }
    }



    public function opcodeAnalysis(&$opArray, $thisopId, $opcode, $nextlineNo, $phpFilePath, &$state, &$classOpArray, &$thisFileFuncOpArray, $thisFuncName, &$includedFileObject, &$includeFileChain, $DebugFlag = False, $funcCheckFlag = False)
    {
        $opcodeNo = $opcode->opcodeNo;
        $opcodeCount = count($opArray);
        $op1 = $opcode->op1;
        $op2 = $opcode->op2;
        $op1_type = $opcode->op1_type;
        $op2_type = $opcode->op2_type;
        $result = $opcode->result;
        $result_type = $opcode->result_type;
        $fetch_flag = $opcode->fetch_flag;
        $lineNo = $opcode->lineNo;
        $extended_value = $opcode->extended_value;
        $jmp_ext = $opcode->jmp_ext;

        $tmpfilepathinfo = pathinfo($phpFilePath);
        $processFlag = "([". realpath($phpFilePath). "]." . $thisFuncName . ")_Line_";
        $localVar = &$state->localVar;
        $array = &$state->array;
        $tmp_array = &$state->tmp_array;
        $tmp_object = &$state->tmp_object;
        $tmp_class_prop = &$state->tmp_class_prop;
        $tmp_ref_object = &$state->tmp_ref_object;
        $tmp_mulit_array = &$state->tmp_mulit_array;
        $methodMagicFlag = &$state->methodMagicFlag;
        $methodMagicChangeBBCount = &$state->methodMagicChangeBBCount;
        $exceptionState = &$state->exceptionState;
        $errorState = &$state->errorState;
        $yieldState = &$state->yieldState;
        $obState = &$state->obState;
        $throwCount = &$state->throwCount;
        $forCount = &$state->forCount;
        $exitFlag = &$state->exitFlag;
        $jumpFlag = &$state->jumpFlag;
        $jumplist = &$state->jumplist;
        $jumpState = &$state->jumpState;
        $jumpStateFuncName = &$state->jumpStateFuncName;
        $jumpStatefileName = &$state->jumpStatefileName;
        $funcTypeForCheck = &$state->funcTypeForCheck;

        $tmp_funcCallState = &$state->tmp_funcCallState;
        $programCallState = &$state->programCallState;
        $callStateIndex = &$state->callStateIndex;
        $object = &$state->object;
        $jmpAddr = &$state->jmpAddr;
        $foreachCount = &$state->foreachCount;
        $foreachIndex = &$state->foreachIndex;
        $assign_DIM_OP_flag = &$state->assign_DIM_OP_flag;

        if(empty($state->includePaths)){
            $includePaths = &$state->includePaths;
        }else{
            $state->includePaths = get_include_path();
            $includePaths = &$state->includePaths;
        }

        if(isset($thisFileFuncOpArray[$thisFuncName])){
            $cvVarNamelist = $thisFileFuncOpArray[$thisFuncName]->compiledVars;
        }else{
            if(empty($state->tmp_funcCallState->funcInfo)){
                $cvVarNamelist = null;
            }else{
                $cvVarNamelist = $state->tmp_funcCallState->funcInfo->compiledVars;
            }
        }

        if(!empty($tmp_funcCallState->thisObjectVarId)){
            $thisObjectVarId = $tmp_funcCallState->thisObjectVarId;
        }
        else{
            $thisObjectVarId = NULL;
        }

        if(!empty($tmp_funcCallState->thisObjectVarcvName)){
            $thisObjectVarcvName = $tmp_funcCallState->thisObjectVarcvName;
        }
        else{
            $thisObjectVarcvName = NULL;
        }

        if(!is_null($state->tmp_funcCallState)){
            $callerName = $state->tmp_funcCallState->callerName;
        }

        if(empty($state->globalState) and !is_null($state->tmp_funcCallState)){
            $globalState = &$state->tmp_funcCallState->globalState;
            $state->globalState = $globalState;
        }else{
            $globalState = &$state->globalState;
        }


        if (is_null($state->tmp_funcCallState)) {
            $superglobals = &$state->superglobals;
        } elseif (isset($state->tmp_funcCallState->superglobals)) {
            $superglobals = &$state->tmp_funcCallState->superglobals;
            $state->superglobals = $superglobals;
            unset($state->tmp_funcCallState->superglobals);
        } else{
            $superglobals = &$state->superglobals;
        }

        $localSave = &$state->localSave;



        if(empty($state->taintedChains) and !is_null($state->tmp_funcCallState)){
            $taintedChains = &$state->tmp_funcCallState->taintedChains;
            $state->taintedChains = &$taintedChains;
        }else{
            $taintedChains = &$state->taintedChains;
        }

        if (empty($state->vulChains) and !is_null($state->tmp_funcCallState)) {
            $vulChains = &$state->tmp_funcCallState->vulChains;
            $state->vulChains = &$vulChains;
        } else {
            $vulChains = &$state->vulChains;
        }

        if(empty($state->currentFilePath)){
            $state->currentFilePath = $phpFilePath;
        }
        $currentFilePath = &$state->currentFilePath;
        if (empty($state->currentWorkPath)) {
            $state->currentWorkPath = $phpFilePath;
        }
        $currentWorkPath = &$state->currentWorkPath;

        $CONSTARRAYINFO = [$phpFilePath, $lineNo, $lineNo];

        
        switch ($opcodeNo) {

            
            case ZEND_CATCH:
            case ZEND_NEW:
                if($fetch_flag == "self"){
                    $op1 = $state->tmp_funcCallState->className;

                }
                $tmp_op1 = $this->varCalc($localVar, $op1);
                if($tmp_op1->if_tainted == 1){
                    $object[$result] = new ObjectStruct("USERCONTROLLABLE");
                    $newObjectOpArray = $this->classSearch("USERCONTROLLABLE", $classOpArray);
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++;
                    $tmp_funcCallState = new FuncCallState();
                    $tmp_funcCallState->classMethodInit(2, NULL, NULL);
                    $tmp_funcCallState->thisObjectVarId = $result;
                    break;
                }else{
                    $object[$result] = new ObjectStruct($tmp_op1->value);
                    $newObjectOpArray = $this->classSearch($tmp_op1->value, $classOpArray);
                }
                

                if(is_string($newObjectOpArray) and $newObjectOpArray == "EXCEPTIONS"){
                    $exceptionState->exceptionFlag = 1;
                }else if(is_array($newObjectOpArray)){
                    $object[$result]->anonymousClassInfo = $newObjectOpArray;
                }
                if (!is_object($newObjectOpArray) and $newObjectOpArray == 'ERROR') {
                    foreach ($includedFileObject as $obj) {
                        $newObjectOpArray = $this->classSearch($tmp_op1->value, $obj->classOpArray);
                        if (is_object($newObjectOpArray)) {
                            $object[$result]->funcOpArray = $newObjectOpArray->funcOpArray;
                        }
                    }
                }else if($newObjectOpArray == 'INTERNAL'){
                    $internalClassMethods = get_class_methods($tmp_op1->value);
                    foreach ($internalClassMethods as $method) {
                        $method = strtolower($method);  
                        $object[$result]->funcOpArray[$method] = new FuncInfo($method);
                        $object[$result]->funcOpArray[$method]->funcType = 'INTERNAL'; 
                    }
                } 
                else if(is_object($newObjectOpArray)){
                    $object[$result]->propertyAssign($newObjectOpArray->properties_info);
                    $object[$result]->funcOpArray = $newObjectOpArray->funcOpArray;
                }

                if(isset($object[$result]->funcOpArray["__construct"])){
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++;
                    $initFuncOpArray = $object[$result]->funcOpArray["__construct"];
                    $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                    $tmp_funcCallState->classMethodInit(1, $tmp_op1->value, "__construct");
                    $tmp_funcCallState->thisObjectVarId = $result;
                }else{
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++;
                    $tmp_funcCallState = new FuncCallState();
                    if($exceptionState->exceptionFlag){
                        $tmp_funcCallState->exceptionFlag = 1;
                    }
                }
                break;
            case ZEND_DECLARE_ANON_CLASS:
                $anonymousNameArr = explode("%00",$op1);
                $className = urldecode($anonymousNameArr[0]);
                $localVar[$result] = new ValueStruct($className);
                break;



                
            case ZEND_QM_ASSIGN:
                $type = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                switch ($type) {
                    case IS_INIT:
                    case IS_LOCAL:
                        $tmp_op1 = $this->varCalc($localVar, $op1);
                        $tmp_result = $this->varCalc($localVar, $result);
                        if ($tmp_result->if_ref == "REF_VAR") {
                            $this->arrayVarClear($array, $result);
                            $this->VarClear($type, $localVar, $array, $object, $result);
                            $result = $this->refCalc($superglobals, $localVar, $array, $object, $tmp_result->refSource, $tmp_result->refType, $tmp_result->refIndex);
                            $result->value = $tmp_op1->value;
                            $result->if_tainted = $tmp_op1->if_tainted;
                            $result->taintedSource = $tmp_op1->taintedSource;
                            $result->taintedSourceLine = $tmp_op1->taintedSourceLine;
                            $result->taintedLine = $tmp_op1->taintedLine;
                        } else {
                            $localVar[$result] = clone $tmp_op1;
                            $this->taint_propagate_op2($localVar, $taintedChains, $result, $op1, $processFlag . (string)$lineNo);
                            $this->VarClear($type, $localVar, $array, $object, $result);
                        }

                        break;
                    case IS_CONST_ARRAY:
                        $CONST_ARRAY = $this->constArrayCount($phpFilePath, $lineNo, $lineNo);
                        $CONST_COUNT = count($CONST_ARRAY);
                        $array[$result] = new ArrayStruct();
                        for ($i = 0; $i < $CONST_COUNT; $i++) {
                            $array[$result]->keyArray[$i] = new ValueStruct(0);
                        }
                        $array[$result]->indexCount = $CONST_COUNT;
                        $this->VarClear($type, $localVar, $array, $object, $result);
                        break;
                    case IS_ARRAY:
                        $array[$result] = clone $array[$op1];
                        $this->VarClear($type, $localVar, $array, $object, $result);
                        break;
                    case IS_OBJECT:
                        $object[$result] = clone $object[$op1];
                        $this->VarClear($type, $localVar, $array, $object, $result);
                    default:
                        break;
                }
                break;
            case ZEND_CLONE:        
            case ZEND_ASSIGN:
                if($opcodeNo == ZEND_CLONE){
                    $op2 = $op1;
                    $op1 = $result;
                }
                if($op2_type == "IS_CONST"){
                    $op2 = urldecode($op2);
                }
                $op1type = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                if($op1type == IS_GLOBAL_UNBIND){
                    $op1GlobalcvId = $op1;
                    $op1GlobalcvName = $superglobals->unbind[$op1];
                    $globalFlag = 1;
                }else if($op1type == IS_GLOBAL){
                    $op1GlobalcvName = $this->getCVName($op1,$cvVarNamelist);
                    $globalFlag = 1;
                }
                else{
                    $globalFlag = 0;
                }


                $type = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2); 
                switch ($type) {
                    case IS_INIT:
                    case IS_LOCAL:
                        $tmp_op2 = $this->varCalc($localVar, $op2);
                        $tmp_op1 = $this->varCalc($localVar, $op1);
                        if($globalFlag){
                            $localVar[$op1] = new ValueStruct();
                            $localVar[$op1]->valuePassFromLocal($tmp_op2);
                            $this->taint_propagate_op2($localVar, $taintedChains, $op1, $op2, $processFlag . (string)$lineNo);
                            $localVar[$op1]->cvName = $op1GlobalcvName;
                            $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$op1GlobalcvName] =
                            $localVar[$op1];
                            break;
                        }
                        if($tmp_op1->if_ref == "REF_VAR"){
                            $this->arrayVarClear($array, $op1);
                            $this->VarClear($type, $localVar, $array, $object, $op1);
                            $tmp_op1 = $this->refCalc($superglobals,$localVar,$array,$object,$tmp_op1->refSource, $tmp_op1->refType, $tmp_op1->refIndex);
                            $tmp_op1->value = $tmp_op2->value;
                            $tmp_op1->if_tainted = $tmp_op2->if_tainted;
                            $tmp_op1->taintedSource = $tmp_op2->taintedSource;
                            $tmp_op1->taintedSourceLine = $tmp_op2->taintedSourceLine;
                            $tmp_op1->taintedLine = $tmp_op2->taintedLine;
                        }
                        else{
                            $localVar[$op1] = new ValueStruct();
                            $localVar[$op1]->valuePassFromLocal($tmp_op2);
                            $this->taint_propagate_op2($localVar, $taintedChains, $op1, $op2, $processFlag . (string)$lineNo);
                            $this->VarClear($type, $localVar, $array, $object, $op1);
                        }
                        if ($op1_type == "IS_CV") {
                            $localVar[$op1]->cvName = $this->getCVName($op1, $cvVarNamelist);
                        }

                        break;
                    case IS_CONST_ARRAY:
                        $CONST_ARRAY = $this->constArrayCount($phpFilePath, $lineNo, $lineNo);
                        $CONST_COUNT = count($CONST_ARRAY);
                        $tmpCounter = 0;
                        $array[$op1] = new ArrayStruct();
                        foreach ($CONST_ARRAY as $key => $value) {
                            $array[$op1]->keyArray[$tmpCounter] = new ValueStruct($value);
                            $tmpCounter++;
                        }
                        $array[$op1]->indexCount = $CONST_COUNT;
                        if ($op1_type == "IS_CV") {
                            $array[$op1]->cvName = $this->getCVName($op1, $cvVarNamelist);
                        }
                        $this->VarClear($type, $localVar, $array, $object, $op1);
                        break;
                    case IS_ARRAY:
                        if ($globalFlag) {
                            $array[$op1] = clone $array[$op2];
                            $array[$op1]->cvName = $op1GlobalcvName;
                            $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$op1GlobalcvName] = $array[$op1];
                            break;
                        }
                        $array[$op1] = clone $array[$op2];      
                        if ($op1_type == "IS_CV") {
                            $array[$op1]->cvName = $this->getCVName($op1, $cvVarNamelist);
                        }
                        $this->VarClear($type, $localVar, $array, $object, $op1);
                        break;
                    case IS_OBJECT:
                        if ($opcodeNo == ZEND_CLONE) {
                            $object[$op1] = clone $object[$op2];
                            $object[$op1]->if_objectCV = 0;
                        }
                        else if ($globalFlag) {
                            $object[$op1] = clone $object[$op2];
                            $object[$op1]->cvName = $op1GlobalcvName;
                            $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$op1GlobalcvName] = $object[$op1];
                            if($op2_type != "IS_CV"){
                                $object[$op1]->if_objectCV = 1;
                            }else{
                                $object[$op1]->if_objectCV = 0;
                            }
                            break;
                        }
                        else{
                            $object[$op1] = $object[$op2];
                            $object[$op1]->if_ref = "REF_VAR"; 
                            $object[$op1]->refSource = $op2;   
                            $object[$op1]->refType = IS_OBJECT;
                        }
                        if($op1_type == "IS_CV"){
                            $object[$op1]->cvName = $this->getCVName($op1, $cvVarNamelist);
                        }
                        $this->VarClear($type, $localVar, $array, $object, $op1);
                        break;
                    case IS_GLOBAL:
                        $cvVarName = $this->getCVName($op2, $cvVarNamelist);
                    case IS_GLOBAL_UNBIND:
                        if($type == IS_GLOBAL_UNBIND){
                            $cvVarName = $superglobals->unbind[$op2];
                        }
                        $tmp_op2 = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName];
                        switch (get_class($tmp_op2)) {
                            case 'ObjectStruct':
                                $object[$op1] = $tmp_op2;
                                break;
                            case 'ArrayStruct':
                                $array[$op1] = $tmp_op2;
                                break;
                            case 'ValueStruct':
                                $localVar[$op1] = $tmp_op2;
                                break;
                            default:
                                break;
                        }
                        break;
                    default:
                        break;
                }
                break;
            
            case ZEND_ASSIGN_STATIC_PROP:
                if($op2_type == "IS_CONST"){
                    $thisClassName = $op2;
                }else if(empty($op2)){
                    $thisClassName = $state->tmp_funcCallState->className;
                }else {
                    $thisClassName = $localVar[$op2]->value;
                }
                $tmp_class_prop = [$thisClassName, $op1];
                break;
            
            case ZEND_ASSIGN_STATIC_PROP_REF:
                $tmp_class_prop = [$op2, $op1, "REF_VAR"];
                break;
            case ZEND_FETCH_STATIC_PROP_R:
                if(!empty($op2)){
                    $tmp_local = $classOpArray[$op2]->properties_info[$op1];
                }else{
                    if(empty($classOpArray[$thisObjectVarId])){
                        $thisClassName = $state->tmp_funcCallState->className;
                        $tmp_local = $classOpArray[$thisClassName]->properties_info[$op1];
                    }else{
                        $tmp_local = $classOpArray[$thisObjectVarId]->properties_info[$op1];
                    }
                    
                }
                if(is_array($tmp_local)){
                    $localVar[$result] = new ValueStruct();
                } else if (get_class($tmp_local) == 'ObjectStruct') {
                    $object[$result] = $tmp_local;
                } else if($tmp_local->if_ref != "REF_VAR"){
                    $localVar[$result] = $tmp_local;
                } else{
                    $localVar[$result] = $this->refCalc($superglobals, $localVar, $array, $object, $tmp_local->refSource, $tmp_local->refType, $tmp_local->refIndex);
                }

                break;

            case ZEND_ASSIGN_OBJ_REF:
                $tmp_ref_object = [$op1, $op2];
                break;
            
            case ZEND_INSTANCEOF:
                if($op2_type == "IS_UNUSED"){
                    if(!empty($object[$op1])){
                        debugEcho("[DEBUG]if self?", $DebugFlag);
                        $localVar[$result] = new ValueStruct(1);
                    }else{
                        $localVar[$result] = new ValueStruct(0);
                    }
                }else{
                    if(!$this->ifVar($op1_type, $op1)){
                        debugEcho("[DEBUG]instanceof name is a const.", $DebugFlag);
                    }else{
                        debugEcho("[DEBUG]instanceof name is a var.", $DebugFlag);
                    }
                }
                break;

            case ZEND_ADD:
            case ZEND_SUB:
            case ZEND_MUL:
            case ZEND_DIV:
            case ZEND_MOD:
            case ZEND_SL:
            case ZEND_SR:
            case ZEND_BW_OR:
            case ZEND_BW_AND:
            case ZEND_BW_XOR:
            case ZEND_POW:
            case ZEND_BOOL_XOR:
                $calcPattern = '(%s)%s(%s)';
                if($op1_type != 'IS_CONST'){
                    $tmp_op1 = $this->varCalc($localVar, $op1);
                } else {
                    $tmp_op1 = new ValueStruct($op1);
                }

                if($op2_type != 'IS_CONST'){
                    $tmp_op2 = $this->varCalc($localVar, $op2);
                }else{
                    $tmp_op2 = new ValueStruct($op2);
                }

                if (is_numeric($tmp_op1->value) && is_numeric($tmp_op2->value)) {
                    $localVar[$result] = new ValueStruct($this->calc($tmp_op1->value, $tmp_op2->value, $opcodeNo));
                }else{
                    $tmpvalue = sprintf($calcPattern, (string)$tmp_op1->value, $this->getOperator($opcodeNo), (string)$tmp_op2->value);
                    $taintedInfo = $this->taint_propagate_op1_op2($localVar, $taintedChains, $op1, $op2, $processFlag . (string)$lineNo, $tmp_op1, $tmp_op2);
                    $localVar[$result] = new ValueStruct($tmpvalue, 0, NULL, NULL, NULL, 0,$taintedInfo[0], $taintedInfo[1], $taintedInfo[2], $taintedInfo[3]);

                }

                
                break;


                
            case ZEND_FETCH_DIM_W:
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                $op2VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2, $op2VarType, $cvVarNamelist);
                if(is_null($array[$op1]) or get_class($array[$op1]) != 'ArrayStruct'){
                    break;
                }
                if(!isset($array[$op1]->keyArray[$op2VarValue->value])){
                    $array[$op1]->keyArray[$op2VarValue->value] = new ArrayStruct();
                }
                $array[$result] = &$array[$op1]->keyArray[$op2VarValue->value];
                
                $tmp_array[$result] = [$op1, $op2];
                break;


                
            case ZEND_ASSIGN_DIM:
                if (isset($tmp_array[$op1])) {
                    $tmp_mulit_array = $this->multiArrayHandleGet($tmp_array, $op1, $op2);
                }
                else if ($op2_type == "IS_UNUSED") {
                    if (isset($array[$op1]->indexCount)) {
                        $tmp_array['OP_DATA'] = [$op1, $array[$op1]->indexCount];
                    } else {
                        $tmp_array['OP_DATA'] = [$op1, 0];
                    }
                } else {
                    $tmp_array['OP_DATA'] = [$op1, $op2];
                }
                break;

            case ZEND_FETCH_FUNC_ARG:      
            case ZEND_FETCH_RW:
            case ZEND_FETCH_W:
            case ZEND_FETCH_R:
                if($fetch_flag == 'global'){
                    if($opcodeNo == ZEND_FETCH_W or $opcodeNo == ZEND_FETCH_RW){
                        $array[$result] = $superglobals->superGlobalArrays[$op1];
                    }else{
                        $array[$result] = clone $superglobals->superGlobalArrays[$op1];
                    }
                    if (in_array($op1, $this->TAINTSOURCE)) {
                        $array[$result]->if_tainted = 1;
                        $array[$result]->taintedSource[] = 'TAINTSOURCE';
                        $array[$result]->taintedSource[] = $op1;
                        $array[$result]->taintedSourceLine = $processFlag . (string)$lineNo;
                        $array[$result]->taintedLine = $processFlag . (string)$lineNo;
                        $this->taintedChianNew($taintedChains, $array[$result]->taintedLine); 
                        if($thisFuncName != 'main'){
                            foreach ($taintedChains as &$chainItem) {
                                if(current($chainItem) == $array[$result]->taintedLine){
                                    array_unshift($chainItem, $tmp_funcCallState->callPoint);
                                    $array[$result]->taintedSourceLine = $tmp_funcCallState->callPoint;
                                }
                            }
                        }
                    }

                }else if($fetch_flag == 'local'){
                    $tmp_op1 = $localVar[$op1];
                    if($tmp_op1->if_tainted == 1){
                        $localVar[$result] = new ValueStruct();
                        $localVar[$result]->if_tainted = 1;
                        $localVar[$result]->taintedSource = $tmp_op1->taintedSource;
                        $localVar[$result]->taintedSourceLine = $tmp_op1->taintedSourceLine;
                        $localVar[$result]->taintedLine = $processFlag . (string)$lineNo;
                        $taintedChains = $this->taintedChainAdd($taintedChains, $tmp_op1->taintedSourceLine, $tmp_op1->taintedLine, $processFlag . (string)$lineNo);
                    }else{
                        $tmp_var_id = $cvVarNamelist[$tmp_op1->value];
                        $localVar[$result] = $localVar[$tmp_var_id];
                    }

                }
                break;
            case ZEND_FETCH_DIM_FUNC_ARG:
            case ZEND_FETCH_DIM_R:
                $type = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 0, $op2);
                switch ($type) {
                    case IS_INIT:
                    case IS_LOCAL:
                        $tmp_op1 = $this->varCalc($localVar, $op1);
                        $tmp_op2 = $this->varCalc($localVar, $op2);
                        if($tmp_op1->value == "GLOBALS"){
                            $cvId = $thisFileFuncOpArray[$callerName]->compiledVars[$tmp_op2->value];
                            $localVar[$result] = $tmp_funcCallState->globalState->localVar[$cvId];
                            break;
                        }else{
                            $tmp_result = '$' . $tmp_op1->value . '[' . (string)$tmp_op2->value . ']';
                            $taintedInfo = $this->taint_propagate_op1_op2($localVar, $taintedChains, $op1, $op2, $processFlag.(string)$lineNo, $tmp_op1);
                            $localVar[$result] = new ValueStruct($tmp_result, 0, NULL, NULL, NULL, 0, $taintedInfo[0], $taintedInfo[1], $taintedInfo[2], $taintedInfo[3]);
                            break;
                        }
                    case IS_GLOBAL:
                        $cvVarName = $this->getCVName($op1, $cvVarNamelist);
                        $localVar[$result] = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName]->keyArray[$op2];
                        break;
                    case IS_GLOBAL_UNBIND:
                        $cvVarName = $this->getCVName($op1, $cvVarNamelist);
                        $tmp_op1 = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName];
                    case IS_CONST_ARRAY:
                    case IS_ARRAY:
                        $tmp_op2 = $this->varCalc($localVar, $op2);
                        if(isset($array[$op1])){
                            if($tmp_op2->value == "TAINTSOURCE" and $array[$op1]->if_tainted){ 
                                $localVar[$result] = new ValueStruct("TAINTSOURCE", 0, NULL, NULL, NULL, 0, 1, ["TAINTSOURCE"], $array[$op1]->taintedSourceLine, $array[$op1]->taintedLine);
                                break;
                            }else if(isset($array[$op1]->keyArray[$tmp_op2->value])){  
                                if(is_object($array[$op1]->keyArray[$tmp_op2->value])){
                                    $localVar[$result] = clone $array[$op1]->keyArray[$tmp_op2->value];
                                }
                                break;
                            }else if($array[$op1]->if_tainted and $array[$op1]->taintedSource[0] == "TAINTSOURCE" and count($array[$op1]->keyArray) == 0){
                                if(isset($array[$op1]->taintedSource[1]) and $array[$op1]->taintedSource[1] == "_FILES"){
                                    $array[$op1]->keyArray[$tmp_op2->value] = new ArrayStruct();
                                    $array[$op1]->keyArray[$tmp_op2->value]->taintInfoSave(1, ["TAINTSOURCE"], $array[$op1]->taintedSourceLine, $array[$op1]->taintedLine);
                                    $array[$result] = clone $array[$op1]->keyArray[$tmp_op2->value];
                                    break;
                                }else{
                                    $array[$op1]->keyArray[$tmp_op2->value] = new ValueStruct("TAINTSOURCE", 0, NULL, NULL, NULL, 0, 1, ["TAINTSOURCE"], $array[$op1]->taintedSourceLine, $array[$op1]->taintedLine);
                                    $localVar[$result] = clone $array[$op1]->keyArray[$tmp_op2->value];
                                    break;
                                }
                            }
                        }
                        $tmp = $this->arrayCalc($array, $op1, $tmp_op2->value);
                        $op1 = $tmp[0];
                        $tmp_op2->value = $tmp[1];
                        if($tmp_op2->if_tainted == 1){
                            $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                            $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                            if($op1VarValue->if_tainted == 1){
                                if ($op1VarType == 902) {
                                    $tmp_result = '$array(' . $op1 . ')[' . (string)$tmp_op2->value . ']';
                                } else if ($op1VarType == 901) {
                                    $tmp_result = '$' . $op1VarValue->value . '[' . (string)$tmp_op2->value . ']';
                                }
                                $taintedInfo = $this->taint_propagate_op1_op2($localVar, $taintedChains, $op1, $op2, $processFlag . (string)$lineNo, $tmp_op2);
                                $localVar[$result] = new ValueStruct($tmp_result, 0, NULL, NULL, NULL, 0, $taintedInfo[0], $taintedInfo[1], $taintedInfo[2], $taintedInfo[3]);
                            }
                        }else{
                            if (is_null($tmp_op2->value) && $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_ARRAY) {
                                $array[$result] = clone $array[$op1];
                            } else if (!isset($array[$op1]->keyArray[$tmp_op2->value])) {
                                if(isset($tmp_op1)){
                                    $localVar[$result] = $tmp_op1->keyArray[$tmp_op2->value];
                                }else{
                                    $tmp_op1 = $this->varCalc($localVar, $op1);
                                    $tmp_result = '$' . $tmp_op1->value . '[' . (string)$tmp_op2->value . ']';
                                    $taintedInfo = $this->taint_propagate_op1_op2($localVar, $taintedChains, $op1, $op2, $processFlag . (string)$lineNo, $tmp_op1);
                                    $localVar[$result] = new ValueStruct($tmp_result, 0, NULL, NULL, NULL, 0, $taintedInfo[0], $taintedInfo[1], $taintedInfo[2], $taintedInfo[3]);
                                }
                            } else if (get_class($array[$op1]->keyArray[$tmp_op2->value]) == "ArrayStruct") {
                                $array[$result] = clone $array[$op1]->keyArray[$tmp_op2->value];
                            } else if ($array[$op1]->keyArray[$tmp_op2->value]->if_ref == "REF_VAR") {
                                $tmp_arrayItem = $array[$op1]->keyArray[$tmp_op2->value];
                                $localVar[$result] = $this->refCalc($superglobals, $localVar, $array, $object, $tmp_arrayItem->refSource, $tmp_arrayItem->refType, $tmp_arrayItem->refIndex);
                            } else {
                                $valueSturct = $array[$op1]->keyArray[$tmp_op2->value];
                                $localVar[$result] = new ValueStruct();
                                $localVar[$result]->localSaveArrayItem($valueSturct);
                            }
                        }

                        break;
                    default:
                        break;
                }
                break;

            case ZEND_GET_CALLED_CLASS:
                $calledClassName = $thisObjectVarId;
                $localVar[$result] = new ValueStruct($calledClassName);
                break;

            case ZEND_FETCH_CLASS:
                if(!empty($object[$op2])){
                    $fetchClassName = $object[$op2]->className;
                    $localVar[$result] = new ValueStruct($fetchClassName);
                }else{
                    $localVar[$result] = clone $localVar[$op2];
                }

                break;
            case ZEND_FETCH_CLASS_CONSTANT:
                if ($op2_type == "IS_CONST") {
                    $thisClassName = $op2;
                } else {
                    $thisClassName = $localVar[$op2]->value;
                }
                $localVar[$result] = new ValueStruct("CLASS_CONST_VALUE");
                break;

            case ZEND_FETCH_OBJ_R:
                if(empty($op1)){
                    $cvName = $state->tmp_funcCallState->thisObjectVarcvName;
                    $localVar[$result] = $state->superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvName]->propertiesArray[$op2];
                }else{
                    if(isset($object[$op1]) and isset($object[$op1]->propertiesArray[$op2])){
                        $localVar[$result] = $object[$op1]->propertiesArray[$op2];
                    }
                    
                }

                if(!empty($object[$op1])){
                    foreach ($object[$op1]->funcOpArray as $key => $value) {
                        if ($key == "__get") {
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__get", "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(65, "SEND_VAL", $lineNo, $op2, "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                            $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                            $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                            $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                        }
                    }
                }

                break;
            case ZEND_ISSET_ISEMPTY_PROP_OBJ:
                if(!empty($object[$op1]->propertiesArray[$op2])){
                    $localVar[$result] = new ValueStruct(1);
                }
                if (!empty($object[$op1])) {
                    foreach ($object[$op1]->funcOpArray as $key => $value) {
                        if ($key == "__isset") {
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__isset", "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(65, "SEND_VAL", $lineNo, $op2, "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                            $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                            $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                            $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                        }
                    }
                }
                break;
            case ZEND_UNSET_OBJ:
                if (!empty($object[$op1])) {
                    foreach ($object[$op1]->funcOpArray as $key => $value) {
                        if ($key == "__unset") {
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__unset", "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(65, "SEND_VAL", $lineNo, $op2, "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                            $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                            $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                            $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                        }
                    }
                }else{
                    unset($object[$op1]->propertiesArray[$op2]);
                }
                break;

            case ZEND_FETCH_IS:
                $array[$result] = clone $superglobals->superGlobalArrays[$op1];
                if (in_array($op1, $this->FETCHISSOURCE)) {
                    $array[$result]->if_tainted = 1;
                    $array[$result]->taintedSource[] = 'TAINTSOURCE';
                    $array[$result]->taintedSource[] = $op1;
                    $array[$result]->taintedSourceLine = $processFlag . (string)$lineNo;
                    $array[$result]->taintedLine = $processFlag . (string)$lineNo;
                    $this->taintedChianNew($taintedChains, $array[$result]->taintedLine); 
                }
                break;
            case ZEND_ARRAY_KEY_EXISTS:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                if(isset($array[$op2])){
                    if(isset($array[$op2]->keyArray[$op1VarValue->value]) or $array[$op2]->if_tainted == 1){
                        $localVar[$result] = new ValueStruct(1);
                    }else{
                        $localVar[$result] = new ValueStruct(0);
                    }
                }else{
                    $localVar[$result] = new ValueStruct(0);
                }
                break;

            case ZEND_IS_SMALLER_OR_EQUAL:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                $tmp_op2 = $this->varCalc($localVar, $op2);
                if ((int)$tmp_op1->value <= (int)$tmp_op2->value) {
                    $localVar[$result] = new ValueStruct(1);
                    if(!isset($forCount[$thisopId])){
                        $forCount[$thisopId]['forPos'] = 1;
                    }else{
                        $forCount[$thisopId]['forPos']++;
                    }
                    if($forCount[$thisopId]['forPos'] > 5){
                        $localVar[$result] = new ValueStruct(0);
                        $forCount[$thisopId]['forPos'] = 0;
                    }
                    $forCount[$thisopId]['forNeg'] = 0;
                } else {
                    $localVar[$result] = new ValueStruct(0);
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['forNeg'] = 1;
                    } else {
                        $forCount[$thisopId]['forNeg']++;
                    }
                    if ($forCount[$thisopId]['forNeg'] > 5) {
                        $localVar[$result] = new ValueStruct(1);
                        $forCount[$thisopId]['forNeg'] = 0;
                    }
                    $forCount[$thisopId]['forPos'] = 0;
                }
                break;

            case ZEND_IS_SMALLER:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                $tmp_op2 = $this->varCalc($localVar, $op2);
                if ((int)$tmp_op1->value < (int)$tmp_op2->value) {
                    $localVar[$result] = new ValueStruct(1);
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['forPos'] = 1;
                    } else {
                        $forCount[$thisopId]['forPos']++;
                    }
                    if ($forCount[$thisopId]['forPos'] > 5) {
                        $localVar[$result] = new ValueStruct(0);
                        $forCount[$thisopId]['forPos'] = 0;
                    }
                    $forCount[$thisopId]['forNeg'] = 0;
                } else {
                    $localVar[$result] = new ValueStruct(0);
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['forNeg'] = 1;
                    } else {
                        $forCount[$thisopId]['forNeg']++;
                    }
                    if ($forCount[$thisopId]['forNeg'] > 5) {
                        $localVar[$result] = new ValueStruct(1);
                        $forCount[$thisopId]['forNeg'] = 0;
                    }
                    $forCount[$thisopId]['forPos'] = 0;
                }
                break;

            case ZEND_IS_EQUAL:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                $tmp_op2 = $this->varCalc($localVar, $op2);
                if ($tmp_op1->value == $tmp_op2->value) {
                    $localVar[$result] = new ValueStruct(1);
                } else if($tmp_op1->if_tainted or $tmp_op2->if_tainted){
                    $localVar[$result] = new ValueStruct(1);
                }  
                else {
                    $localVar[$result] = new ValueStruct(0);
                }
                if ($op2_type == "IS_CONST") {
                }
                break;

            case ZEND_IS_NOT_EQUAL:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                $op2VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2, $op2VarType, $cvVarNamelist);
                if($op1VarValue->value != $op2VarValue->value or $op1VarValue->if_tainted == 1){
                    $localVar[$result] = new ValueStruct(1);
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['neq'] = 1;
                    } else {
                        $forCount[$thisopId]['neq']++;
                    }
                    if ($forCount[$thisopId]['neq'] > 5) {
                        $localVar[$result] = new ValueStruct(0);
                        $forCount[$thisopId]['neq'] = 0;
                    }
                }else{
                    $localVar[$result] = new ValueStruct(0);
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['eq'] = 1;
                    } else {
                        $forCount[$thisopId]['eq']++;
                    }
                    if ($forCount[$thisopId]['eq'] > 5) {
                        $localVar[$result] = new ValueStruct(1);
                        $forCount[$thisopId]['eq'] = 0;
                    }
                }
                break;
            case ZEND_ISSET_ISEMPTY_CV:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                if($op1VarType == IS_INIT){
                    $localVar[$result] = new ValueStruct(0);
                }else{
                    $localVar[$result] = new ValueStruct(1);
                }
                break;
            
            case ZEND_ISSET_ISEMPTY_DIM_OBJ:
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                $op2VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2, $op2VarType, $cvVarNamelist);
                if(isset($array[$op1]->keyArray[$op2VarValue->value])){
                    $localVar[$result] = new ValueStruct(1);
                }else if(isset($array[$op1]) and $array[$op1]->if_tainted){
                    if(isset($array[$op1]->taintedSource[1]) and $array[$op1]->taintedSource[1] == '_COOKIE'){
                        $superglobals->superGlobalArrays['_COOKIE']->keyArray[$op2VarValue->value] = new ValueStruct("TAINTSOURCE", 0, NULL, NULL, NULL, 0, 1, ["TAINTSOURCE"], $array[$op1]->taintedSourceLine, $array[$op1]->taintedLine);
                    }
                    $localVar[$result] = new ValueStruct(1);
                }else{
                    $localVar[$result] = new ValueStruct(0);
                }
                break;
            
            

            case ZEND_INIT_ARRAY:
            case ZEND_ADD_ARRAY_ELEMENT:
                $tmp_op1 = new ValueStruct($op1);
                $tmp_op2 = $op2;
                $taint_flag = 0;
                $index_flag = 0;
                $array_flag = 0;

                if($op2_type == "IS_UNUSED"){
                    $index_flag = 1;
                }


                
                if($this->ifVar($op1_type, $op1)){
                    if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_LOCAL) {
                        $tmp_op1 = $this->varCalc($localVar, $op1);
                        $taint_op1 = $tmp_op1;
                        $taint_flag = 1;        
                    }else if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_ARRAY) {
                        $array_flag = 1;
                    }

                    if($this->ifVar($op2_type, $op2)){
                        if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2) == IS_LOCAL) {
                            $tmp_op2 = $this->varCalc($localVar, $op2);
                            $tmp_op2 = $tmp_op2->value; 
                        }
                        if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2) == IS_ARRAY) {
                            $array_flag = 1;
                        }
                    }
                }else if($this->ifVar($op2_type, $op2)){
                    if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2) == IS_LOCAL) {
                        $tmp_op2 = $this->varCalc($localVar, $op2);
                        if($tmp_op2->if_tainted){
                            $tmprandnum = rand(1000, 10000);
                            $taintedKey = "TAINTED" . (string)$tmprandnum;
                            $taintedChains = $this->taintedChainAdd($taintedChains, $tmp_op2->taintedSourceLine, $tmp_op2->taintedLine, $processFlag . (string)$lineNo);
                            $tmp_op2->taintedLine = $processFlag . (string)$lineNo;
                            $this->arraySaveStruct($array, $result, $tmp_op1, $array_flag, $taintedKey, $tmp_op2);
                            break;
                        }else{
                            $tmp_op2 = $tmp_op2->value; 
                        }

                    }
                }


                
                if($index_flag){
                    if(!isset($array[$result]->indexCount)){
                        $this->arraySaveStruct($array, $result, $tmp_op1,$array_flag, 0);
                        $array[$result]->indexCount = 1;
                    }else{
                        $this->arraySaveStruct($array, $result, $tmp_op1,$array_flag, $array[$result]->indexCount);
                        $array[$result]->indexCount++;
                    }
                    
                }else{
                    $this->arraySaveStruct($array, $result, $tmp_op1, $array_flag, $tmp_op2);
                }


                
                if($taint_flag){
                    if(!empty($tmp_op2)){
                        $taintedChains = $this->taintedChainAdd($taintedChains, $taint_op1->taintedSourceLine, $taint_op1->taintedSourceLine, $processFlag . (string)$lineNo);
                        $taint_op1->taintedLine = $processFlag . (string)$lineNo;
                        $this->taint_propagate_array_value($array, $result, $tmp_op2, $taint_op1);
                    }else{
                        $taintedChains = $this->taintedChainAdd($taintedChains, $taint_op1->taintedSourceLine, $taint_op1->taintedSourceLine, $processFlag . (string)$lineNo);
                        $taint_op1->taintedLine = $processFlag . (string)$lineNo;
                        $this->taint_propagate_array_value($array, $result, $array[$result]->indexCount-1, $taint_op1);
                    }

                }


                break;


            case ZEND_ASSIGN_OBJ:
                $tmp_object = [$op1, $op2];
                if(!empty($object[$op1])){
                    foreach ($object[$op1]->funcOpArray as $key => $value) {
                        if ($key == "__set") {
                            $methodMagicFlag = __set;
                        }
                    }
                }

                break;


            case ZEND_OP_DATA:
                if($assign_DIM_OP_flag){
                    $assDIMOP_op1 = $tmp_array['OP_DATA'][0];
                    $assDIMOP_op2 = $tmp_array['OP_DATA'][1];
                    $extended_value = $tmp_array['OP_DATA'][2];

                    $tmp_op_data = $this->varCalc($localVar, $op1);
                    $tmp_dim_op_key = $this->varCalc($localVar, $assDIMOP_op1);
                    $tmp_dim_op_value = $this->varCalc($localVar, $assDIMOP_op2);
                    $tmp_dim_op_array = $this->arrayCalc($array, $tmp_dim_op_key->value, $tmp_dim_op_value->value);

                    $tmp_dim_op_key = $tmp_dim_op_array[0];
                    $tmp_dim_op_index = $tmp_dim_op_array[1];
                    $calc_op1_value = $array[$tmp_dim_op_key]->keyArray[$tmp_dim_op_index]->value;
                    $array[$tmp_dim_op_key]->keyArray[$tmp_dim_op_index]->value = $this->calc($calc_op1_value, $tmp_op_data->value,$extended_value);
                    if($tmp_op_data->if_tainted == 1){
                        $array[$tmp_dim_op_key]->keyArray[$tmp_dim_op_index]->if_tainted = $tmp_op_data->if_tainted;
                        $array[$tmp_dim_op_key]->keyArray[$tmp_dim_op_index]->taintedSource = $tmp_op_data->taintedSource;
                        $array[$tmp_dim_op_key]->keyArray[$tmp_dim_op_index]->taintedSourceLine = $tmp_op_data->taintedSourceLine;
                        $array[$tmp_dim_op_key]->keyArray[$tmp_dim_op_index]->taintedLine = $tmp_op_data->taintedLine;
                    }

                    unset($state->tmp_object['OP_DATA']);
                    $assign_DIM_OP_flag = 0;
                    break;
                }
                if(!empty($tmp_array['OP_DATA'])){
                    $assDIM_op1 = $tmp_array['OP_DATA'][0];
                    $assDIM_op2 = $tmp_array['OP_DATA'][1];
                    if (isset($array[$assDIM_op1])) {
                        if($array[$assDIM_op1]->if_ref == "REF_VAR" and $array[$assDIM_op1]->refType != 'superglobal'){
                            $assDIM_op1 = $array[$assDIM_op1]->refSource;
                        }else if(isset($array[$assDIM_op1]->keyArray[$assDIM_op2])){
                            if($array[$assDIM_op1]->keyArray[$assDIM_op2]->if_ref == "REF_VAR"){
                                $tmpArray = $array[$assDIM_op1]->keyArray[$assDIM_op2];
                                $assDIM_op1 = $tmpArray->refSource;
                                if($tmpArray->refType == "ARRAYITEM"){
                                    $assDIM_op2 = $tmpArray->refIndex;
                                }
                            }
                        }
                    }
                    if (!is_numeric($assDIM_op2)) {
                        $assDIM_op2 = $this->varCalc($localVar, $assDIM_op2);
                        $assDIM_op2 = $assDIM_op2->value;
                    }
                    if (is_numeric($assDIM_op2)) {
                        $num_flag = 1;
                    } else {
                        $num_flag = 0;
                    }

                    $assDIMVarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $assDIM_op1, 1);
                    switch ($assDIMVarType) {
                        case IS_LOCAL:
                            $assDIMVVarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $assDIM_op1, $assDIMVarType, $cvVarNamelist);
                            $assDIM_op1 = $assDIMVVarValue->value;
                            break;
                        case IS_ARRAY:
                            break;
                        default:
                            break;
                    }
                    

                    if ($this->ifVar($op1_type, $op1)) {
                        if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_LOCAL) {
                            $tmp_op1 = $this->varCalc($localVar, $op1);
                            $array[$assDIM_op1]->keyArray[$assDIM_op2] = $tmp_op1;
                            if($array[$assDIM_op1]->if_ref == "REF_VAR"){
                                $refObject = $this->refCalc($superglobals, $localVar, $array, $object, $array[$assDIM_op1]->refSource, $array[$assDIM_op1]->refType, $array[$assDIM_op1]->refIndex);
                                $refObject->keyArray[$assDIM_op2] = $tmp_op1;
                            }
                            $this->arraySaveStruct($array, $assDIM_op1, $tmp_op1, 0, $assDIM_op2);
                            $this->taint_propagate_array_value($array, $assDIM_op1, $assDIM_op2, $tmp_op1);
                            if ($num_flag) {
                                $array[$assDIM_op1]->indexCount = $assDIM_op2 + 1;
                            }
                        } else if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_ARRAY) {
                            $this->arraySave($array, $assDIM_op1, 1, $assDIM_op2, $op1->value);
                            $this->taint_propagate_array_value($array, $assDIM_op1, $assDIM_op2, $op1);
                        }
                    } else {
                        if($op1 == "<array>"){
                            $CONST_ARRAY = $this->constArrayCount($phpFilePath, $lineNo, $lineNo);
                            $CONST_COUNT = count($CONST_ARRAY);
                            if(empty($CONST_COUNT)){
                                $CONST_COUNT = 0;
                            }
                            $this->arraySave($array, $assDIM_op1, 0, $assDIM_op2, $op1, $CONST_COUNT);
                        }else{
                            $this->arraySave($array, $assDIM_op1, 0, $assDIM_op2, $op1);
                        }
                        if ($num_flag) {
                            if (isset($array[$assDIM_op1]->indexCount)) {
                                if ($assDIM_op2 > $array[$assDIM_op1]->indexCount) {
                                    $array[$assDIM_op1]->indexCount = $assDIM_op2 + 1;
                                } else if ($assDIM_op2 == $array[$assDIM_op1]->indexCount) {
                                    $array[$assDIM_op1]->indexCount = $array[$assDIM_op1]->indexCount + 1;
                                }
                            } else {
                                $array[$assDIM_op1]->indexCount = 1;
                            }
                        }
                    }
                    unset($state->tmp_object['OP_DATA']);
                }
                else if(!empty($tmp_mulit_array)){
                    $this->multiArrayWrite($localVar, $array, $tmp_mulit_array, $op1);
                    unset($state->tmp_mulit_array);
                }
                else if(!empty($tmp_object)){
                    $ObjName = $tmp_object[0];
                    $propertyKey = $tmp_object[1];
                    if ($this->ifVar($op1_type, $op1)) {
                        if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_LOCAL) {
                            $propertryValue = $this->varCalc($localVar, $op1);
                            $this->objectSave($globalState, $object, $ObjName, $propertyKey, $propertryValue, $thisObjectVarId);
                            
                        } else if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_ARRAY) {
                            $propertryValue = $array[$op1];
                            $this->objectSave($globalState, $object, $ObjName, $propertyKey,$propertryValue, $thisObjectVarId);
                            
                        }
                    } else {
                        $propertryValue = new ValueStruct($op1);
                        $this->objectSave($globalState, $object, $ObjName, $propertyKey, $propertryValue, $thisObjectVarId);
                        
                    }
                    switch ($methodMagicFlag) {
                        case __set:
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $ObjName, "IS_CV", "__set", "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(65, "SEND_VAL", $lineNo, $propertyKey, "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(117, "SEND_VAR", $lineNo, $op1, "IS_CV");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                            $opPreArray = array_slice($opArray,0,$thisopId+1);
                            $opSufArray = array_slice($opArray, $thisopId+1, $opcodeCount);
                            $opArray = array_merge($opPreArray,$setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                            break;
                        
                        default:
                            break;
                    }

                    unset($state->tmp_object);
                    unset($state->methodMagicFlag);
                }
                else if(!empty($tmp_class_prop)){
                    $thisClassName = $tmp_class_prop[0];
                    $staticPropName = $tmp_class_prop[1];
                    if ($this->ifVar($op1_type, $op1)) {
                        $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                        $propValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                    } else {
                        $propValue = new ValueStruct($op1);
                    }
                    $classOpArray[$thisClassName]->properties_info[$staticPropName] = clone $propValue;
                    if(!empty($tmp_class_prop[2]) and $tmp_class_prop[2] == "REF_VAR"){
                        $refType = $this->varTypeChange($op1VarType);
                        $classOpArray[$thisClassName]->properties_info[$staticPropName]->refSave("REF_VAR", $op1, $refType);
                    }
                    unset($state->tmp_class_prop);
                }
                else if(!empty($tmp_ref_object)){
                    $objPoint = $tmp_ref_object[0];
                    $propertyName = $tmp_ref_object[1];
                    if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_LOCAL) {
                        $object[$objPoint]->propertiesArray[$propertyName] = new ValueStruct();
                        $object[$objPoint]->propertiesArray[$propertyName]->refSave('REF_VAR',$op1,'IS_LOCAL',0);
                    } else if ($this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1) == IS_ARRAY) {
                        $object[$objPoint]->propertiesArray[$propertyName] = new ArrayStruct();
                        $object[$objPoint]->propertiesArray[$propertyName]->refSave('REF_VAR', $op1, 'IS_ARRAY');
                    }
                    unset($state->tmp_ref_object);
                }

                break;


                


            case ZEND_MAKE_REF:
                $tmp_array[$result] = $tmp_array[$op1];
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                switch ($op1VarType) {
                    case IS_ARRAY:
                        $array[$result] = &$array[$op1];
                        break;
                    case IS_INIT:
                    case IS_LOCAL:
                        $localVar[$result] = &$localVar[$op1];
                        break;
                    default:
                        break;
                }
                break;

            case ZEND_ASSIGN_REF:

                
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                switch ($op2VarType) {
                    case IS_ARRAY:
                        if(isset($tmp_array[$op1])){
                            $arrayID = $tmp_array[$op1][0];
                            $arraykeyID = $tmp_array[$op1][1];
                            $array[$arrayID]->keyArray[$arraykeyID] = &$array[$op2];
                        }else{
                            $array[$op1] = &$array[$op2];
                        }

                        break;
                    case IS_INIT:
                    case IS_LOCAL:
                        if (isset($tmp_array[$op1])) {
                            $arrayID = $tmp_array[$op1][0];
                            $arraykeyID = $tmp_array[$op1][1];
                            $array[$arrayID]->keyArray[$arraykeyID] = &$localVar[$op2];
                        } else {
                            $localVar[$op1] = &$localVar[$op2];
                        }
                        break;
                    case IS_OBJECT:
                        if (isset($tmp_array[$op1])) {
                            $arrayID = $tmp_array[$op1][0];
                            $arraykeyID = $tmp_array[$op1][1];
                            $array[$arrayID]->keyArray[$arraykeyID] = &$object[$op2];
                        } else {
                            $object[$op1] = &$object[$op2];
                        }
                        break;
                    case IS_GLOBAL:
                        $cvVarName = $this->getCVName($op2, $cvVarNamelist);
                    case IS_GLOBAL_UNBIND:
                        if ($op2VarType == IS_GLOBAL_UNBIND) {
                            $cvVarName = $superglobals->unbind[$op2];
                        }
                        $tmp_op2 = &$superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName];
                        switch (get_class($tmp_op2)) {
                            case 'ObjectStruct':
                                $object[$op1] = &$tmp_op2;
                                break;
                            case 'ArrayStruct':
                                $array[$op1] = &$tmp_op2;
                                break;
                            case 'ValueStruct':
                                $localVar[$op1] = &$tmp_op2;
                                break;
                            default:
                                break;
                        }
                        break;
                    default:
                        break;
                }
                break;

                

            case ZEND_RECV_INIT:
            case ZEND_RECV:
                if ($funcCheckFlag) {
                    $localVar[$result] = new ValueStruct();
                    $array[$result] = new ArrayStruct();
                    $localVar[$result]->taintInfoSave(1, 'TAINTSOURCE', $processFlag . (string)$lineNo, $processFlag . (string)$lineNo);
                    $array[$result]->taintInfoSave(1, 'TAINTSOURCE', $processFlag . (string)$lineNo, $processFlag . (string)$lineNo);
                    $this->taintedChianNew($taintedChains, $processFlag . (string)$lineNo);
                    break;
                }
                if (empty($thisObjectVarId)) {
                    $funcparas = $thisFileFuncOpArray[$thisFuncName]->funcArgs[$tmp_funcCallState->parametersCount];
                } else {
                    $scopeClassName = $tmp_funcCallState->funcInfo->scopeClassName;

                    if (is_array($classOpArray[$scopeClassName])) {
                        $anonymousClassIndex = $tmp_funcCallState->anonymousClassIndex;
                        $funcparas = $classOpArray[$scopeClassName][$anonymousClassIndex]->funcOpArray[$thisFuncName]->funcArgs[$tmp_funcCallState->parametersCount];
                    } else {
                        $funcparas = $classOpArray[$scopeClassName]->funcOpArray[$thisFuncName]->funcArgs[$tmp_funcCallState->parametersCount];
                    }
                }
                if($opcodeNo == ZEND_RECV_INIT){
                    if(!isset($tmp_funcCallState->funcparameters[$tmp_funcCallState->parametersCount])){
                        $type = IS_LOCAL;
                        $value = new ValueStruct($op2);
                        $if_ref = 0;
                        $tmp_funcCallState->parametersCount++;
                        $this->funcParaAndReturnPass($globalState, $localVar, $array, $object, $result, $type, $value, $if_ref);
                        break;
                    }
                }
                $functype = $funcparas->type;
                $paras = $tmp_funcCallState->funcparameters[$tmp_funcCallState->parametersCount];
                $type = $paras[0];
                $value = $paras[1];
                $if_ref = $paras[2];
                $varGlobalId = $paras[3];

                if ($functype == "string" and $type == 904) {
                    $magicCallFlag = 0;
                    foreach ($value->funcOpArray as $key => $tmpvalue) {
                        if ($key == "__tostring") {
                            $magicCallFlag = 1;
                        }
                    }
                    if ($magicCallFlag) {
                        $tmpid = rand(1000, 10000);
                        $tmpVarid = "$" . (string)$tmpid;
                        $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $varGlobalId, "IS_CV", "__tostring", "IS_CONST", "", "IS_UNUSED", "", "global", "");
                        $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", 0, "", "IS_UNUSED", "", "IS_UNUSED", $tmpVarid, "IS_VAR", "", "global", "");
                        $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                        $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                        $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                        $methodMagicChangeBBCount = count($setOpArray);
                    }
                }
                $tmp_funcCallState->parametersCount++;
                $this->funcParaAndReturnPass($globalState, $localVar, $array, $object, $result, $type, $value, $if_ref);
                break;
                if($funcCheckFlag){
                    $localVar[$result] = new ValueStruct();
                    $array[$result] = new ArrayStruct();
                    $localVar[$result]->taintInfoSave(1, 'TAINTSOURCE', $processFlag . (string)$lineNo, $processFlag . (string)$lineNo);
                    $array[$result]->taintInfoSave(1, 'TAINTSOURCE', $processFlag . (string)$lineNo, $processFlag . (string)$lineNo);
                    $this->taintedChianNew($taintedChains, $processFlag . (string)$lineNo); 
                }else{
                    if (empty($thisObjectVarId)) {
                        $funcparas = $thisFileFuncOpArray[$thisFuncName]->funcArgs[$tmp_funcCallState->parametersCount];
                    } else {
                        $scopeClassName = $tmp_funcCallState->funcInfo->scopeClassName;

                        if (is_array($classOpArray[$scopeClassName])) {
                            $anonymousClassIndex = $tmp_funcCallState->anonymousClassIndex;
                            $funcparas = $classOpArray[$scopeClassName][$anonymousClassIndex]->funcOpArray[$thisFuncName]->funcArgs[$tmp_funcCallState->parametersCount];
                        } else {
                            $funcparas = $classOpArray[$scopeClassName]->funcOpArray[$thisFuncName]->funcArgs[$tmp_funcCallState->parametersCount];
                        }
                    }

                    $functype = $funcparas->type;
                    $paras = $tmp_funcCallState->funcparameters[$tmp_funcCallState->parametersCount];
                    $type = $paras[0];
                    $value = $paras[1];
                    $if_ref = $paras[2];
                    $varGlobalId = $paras[3];

                    if ($functype == "string" and $type == 904) {
                        $magicCallFlag = 0;
                        foreach ($value->funcOpArray as $key => $tmpvalue) {
                            if ($key == "__tostring") {
                                $magicCallFlag = 1;
                            }
                        }
                        if ($magicCallFlag) {
                            $tmpid = rand(1000, 10000);
                            $tmpVarid = "$" . (string)$tmpid;
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $varGlobalId, "IS_CV", "__tostring", "IS_CONST", "", "IS_UNUSED", "", "global", "");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", 0, "", "IS_UNUSED", "", "IS_UNUSED", $tmpVarid, "IS_VAR", "", "global", "");
                            $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                            $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                            $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                        }
                    }
                    $tmp_funcCallState->parametersCount++;
                    $this->funcParaAndReturnPass($globalState, $localVar, $array, $object, $result, $type, $value, $if_ref);
                }
                break;
            case ZEND_RECV_VARIADIC:
                $parasCount = count($tmp_funcCallState->funcparameters);
                $currentIndex = $tmp_funcCallState->parametersCount;
                $arrayIndex = 0;
                $array[$result] = new ArrayStruct();
                for ($i= $currentIndex; $i < $parasCount; $i++) { 
                    $array[$result]->keyArray[] = clone  $tmp_funcCallState->funcparameters[$i][1];
                    $arrayIndex ++;
                }
                $array[$result]->indexCount = $arrayIndex;
                break;
            case ZEND_FUNC_GET_ARGS:
                $parasCount = count($tmp_funcCallState->funcparameters);
                $array[$result] = new ArrayStruct();
                for ($i = 0; $i < $parasCount; $i++) {
                    $array[$result]->keyArray[$i] = clone  $tmp_funcCallState->funcparameters[$i][1];
                    if($tmp_funcCallState->funcparameters[$i][1]->if_tainted){
                        $taintedChains = $this->taintedChainAdd($taintedChains,$tmp_funcCallState->funcparameters[$i][1]->taintedSourceLine, $tmp_funcCallState->funcparameters[$i][1]->taintedLine,  $processFlag . (string)$lineNo);
                        $tmp_funcCallState->funcparameters[$i][1]->taintedLine = $processFlag . (string)$lineNo;
                        $this->taint_propagate_array_value($array, $result, $i, $tmp_funcCallState->funcparameters[$i][1]);
                    }

                }
                break;
            case ZEND_FUNC_NUM_ARGS:
                $funcNumArgs = count($tmp_funcCallState->funcparameters);
                $localVar[$result] = new ValueStruct($funcNumArgs);
                break;
            case ZEND_INCLUDE_OR_EVAL:
                debugEcho("[DEBUG]INCLUDE_OR_EVAL\n",$DebugFlag);
                if($op2 == 'EVAL'){
                    if (!$this->ifVar($op1_type, $op1)) {
                        $op1VarValue = urldecode($op1);
                    } else {
                        $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                        $op1Var = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                        if ($op1Var->if_tainted == 1) {
                            $taint_source_Line = $op1Var->taintedSourceLine;
                            $taintedLine = $op1Var->taintedLine;
                            $thisLineNo = $processFlag . (string)$lineNo;
                            debugEcho("[DEBUG] CE FOUND!\n", $DebugFlag, 'red');
                            $this->taintedChainSearch($vulChains, "CE", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo, $DebugFlag);
                        }
                    }
                    break;
                }

                if (!$this->ifVar($op1_type, $op1)) {
                    $op1VarValue = urldecode($op1);
                } else {
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                    $op1Var = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                    if($op1Var->if_tainted == 1){
                        $taint_source_Line = $op1Var->taintedSourceLine;
                        $taintedLine = $op1Var->taintedLine;
                        $thisLineNo = $processFlag . (string)$lineNo;
                        debugEcho("[DEBUG] FI FOUND!\n", $DebugFlag, 'red');
                        $this->taintedChainSearch($vulChains, "FI", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo, $DebugFlag);
                        break;
                    }else{
                        $op1VarValue = urldecode($op1Var->value);
                    }
                }
                if(!empty($currentWorkPath)){
                    $PROJECTPATHINFO = pathinfo($currentWorkPath);
                    $tmpPath = $currentWorkPath;
                }else{
                    $PROJECTPATHINFO = pathinfo($currentFilePath);
                    $tmpPath = $currentFilePath;
                }
                if (!isset($PROJECTPATHINFO['extension'])) {
                    $PROJECTPATH = $tmpPath;
                } else {
                    $PROJECTPATH = dirname($tmpPath);
                }
                $PATH = pathinfo($op1VarValue);


                $tmpfilename = $PATH['filename'];
                $tmpfilepath = $PATH["dirname"] . '/' . $PATH["basename"];
                if(file_exists($tmpfilepath)){
                    $includedFileName = $tmpfilepath;
                    $includedOpcodeFileName = $PATH["dirname"] . '/' . $tmpfilename . ".opcode";
                }else{
                    $includedFileName = $PROJECTPATH . '/' . $PATH["dirname"] . '/' . $PATH["basename"];
                    $includedOpcodeFileName = $PROJECTPATH . '/' . $PATH["dirname"] . '/' . $tmpfilename . ".opcode";
                }
                if(!file_exists($includedFileName)){
                    $PROJECTPATH = pathinfo($phpFilePath);
                    $includedFileName = $PROJECTPATH['dirname'] . '/' . $PATH["dirname"] . '/' . $PATH["basename"];
                    $includedOpcodeFileName = $PROJECTPATH['dirname'] . '/' . $PATH["dirname"] . '/' . $tmpfilename . ".opcode";
                }
                if(!file_exists($includedFileName)){
                    $includePathArray = explode(";", $includePaths);
                    foreach ($includePathArray as $includePath) {
                        $filePathTry = $includePath. $tmpfilepath;
                        if(file_exists($PROJECTPATHINFO['dirname'].'/'.$filePathTry)){
                            $includedFileName = $PROJECTPATHINFO['dirname'] . '/' . $filePathTry;
                            $tmppathinfo = pathinfo($includedFileName);
                            $includedOpcodeFileName = $tmppathinfo['dirname'] . '/' . $tmppathinfo["filename"] . ".opcode";
                        }
                    }
                }
                $includeFileChain->analyzeFile($currentFilePath, $includedFileName, $lineNo);
                $includedObj = new OpcodeParse();
                $checkFlag = 0;
                if(!empty($includedFileObject)){
                    foreach ($includedFileObject as $item) {
                        if ($item->phpFilePath == $includedFileName) {
                            $checkFlag = 1;
                            break;
                        }
                    }
                    if (!$checkFlag) {
                        $tmpFileObject = $includedObj->parse($includedOpcodeFileName, $includedFileName);
                        $includedFileObject[] = $tmpFileObject;
                        @$includedObj->cfgBuild($tmpFileObject);
                    }else{
                        break;
                    }
                }else{
                    $tmpFileObject = $includedObj->parse($includedOpcodeFileName, $includedFileName);
                    $includedFileObject[] = $tmpFileObject;
                    @$includedObj->cfgBuild($tmpFileObject);
                }

                
                $this->GLOBALSSAVE($superglobals, $localVar, $array,$object, $cvVarNamelist);
                $this->LOCALSAVE($localSave, $localVar, $array, $object);
                $currentFilePathSave = $state->currentFilePath;   
                @$state = $includedObj->dataFlowAnalysis($state, $tmpFileObject->funcOpArray['main'], $tmpFileObject,$includedFileObject, $includeFileChain, $DebugFlag);
                $currentFilePath = $currentFilePathSave;    
                $this->GLOBALSLOAD($superglobals, $localVar, $array, $object,'include');
                $this->LOCALLOAD($localSave, $localVar, $array, $object);

                break;

            case ZEND_INIT_FCALL:
                $funcInitName = $op2;
                $initFuncOpArray = $this->funcSearch($funcInitName, $thisFileFuncOpArray);
                $programCallState[$callStateIndex] = $tmp_funcCallState;
                $callStateIndex++; 
                $tmp_funcCallState = new FuncCallState($initFuncOpArray);   
                break;
            case ZEND_INIT_FCALL_BY_NAME:
                $funcInitName = $op2;
                if(!empty($includedFileObject)){
                    foreach ($includedFileObject as $obj) {
                        $initFuncOpArray = $this->funcSearch($funcInitName, $obj->funcOpArray);
                        if (is_object($initFuncOpArray) or is_array($initFuncOpArray)) {
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++; 
                            $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                            break;
                        }
                    }
                    if($initFuncOpArray == "ERROR"){
                        $programCallState[$callStateIndex] = $tmp_funcCallState;
                        $callStateIndex++;
                        $tmp_funcCallState = new FuncCallState('ERROR');
                    }
                }else{
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++; 
                    $initFuncOpArray = $this->funcSearch($funcInitName, $thisFileFuncOpArray);
                    $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                }

                break;
            case ZEND_INIT_NS_FCALL_BY_NAME:
                $funcInitName = $op2;
                $initFuncOpArray = $this->funcSearch($funcInitName, $thisFileFuncOpArray);
                if (is_object($initFuncOpArray)) {
                    $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                }else{
                    $urldecodefuncName = urldecode($funcInitName);
                    $explodefuncName = explode('\\', $urldecodefuncName);
                    $tmp_array_funcName = array_slice($explodefuncName, 1);
                    $implode_funcName = implode("", $tmp_array_funcName); 
                    foreach ($includedFileObject as $obj) {
                        $initFuncOpArray = $this->funcSearch($implode_funcName, $obj->funcOpArray);
                        if (is_object($initFuncOpArray)) {
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++; 
                            $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                        }
                    }
                }
                break;
            case ZEND_INIT_METHOD_CALL:
                $methodFlag = 0;
                $magicCallFlag = 0;
                $globalVarFlag = 0;
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                $op2VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2,$op2VarType, $cvVarNamelist);
                $funcName = $op2VarValue->value;
                $oldcallStateIndex = $callStateIndex;

                if($this->getCVName($op1, $cvVarNamelist)){
                    $cvVarName = $this->getCVName($op1, $cvVarNamelist);
                    if(isset($superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName])){
                        if(get_class($superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName]) == "ObjectStruct"){
                            $object[$op1] = &$superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName];
                        }
                    }
                }else{
                    foreach ($tmp_funcCallState->funcparameters as $key => $value) {
                        if($op1 == $value[3]){
                            $cvVarName = $value[1]->cvName;
                            break;
                        }
                    }
                }

                if($op2VarValue->if_tainted == 1){
                    foreach ($object[$op1]->funcOpArray as $funcOpArrayItem) {
                        $tmpObj = new OpcodeParse();
                        $tmpFilePath = $funcOpArrayItem->phpFilePath;
                        $tmpPath = pathinfo($tmpFilePath);
                        $tmpOpcodePath = $tmpPath['dirname'] . "/" . $tmpPath['filename'] . ".opcode";
                        $tmpFilePath =  $tmpPath['dirname'] . "/" . $tmpPath['filename'] . ".php";
                        $checkFlag = 0;
                        if (!empty($includedFileObject)) {
                            foreach ($includedFileObject as $item) {
                                if ($item->phpFilePath == $tmpFilePath) {
                                    $checkFlag = 1;
                                    $tmpfileObject = $item;
                                    break;
                                }
                            }
                            if (!$checkFlag) {
                                $tmpfileObject = $tmpObj->parse($tmpOpcodePath, $tmpFilePath);
                            }
                        } else {
                            $tmpfileObject = $tmpObj->parse($tmpOpcodePath, $tmpFilePath);
                        }
                        $funcMer = array_merge($tmpfileObject->funcOpArray, $thisFileFuncOpArray);
                        $classMer = array_merge($tmpfileObject->classOpArray, $classOpArray);
                        $tmpfileObject->funcOpArray = $funcMer;
                        $tmpfileObject->classOpArray = $classMer;
                        @$tmpMethodState = $this->dataFlowAnalysis(NULL, $funcOpArrayItem, $tmpfileObject,$includedFileObject, $includeFileChain, $DebugFlag, true);
                        $funcCheckReuslt[] = $tmpMethodState->funcTypeForCheck;
                        if($tmpMethodState->funcTypeForCheck){
                            $tmpFuncType = $tmpMethodState->funcTypeForCheck[1];
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++;
                            $tmp_funcCallState = new FuncCallState(["USERFUNC", $tmpFuncType, [0], []]);
                            $tmp_funcCallState->classMethodInit(1, $object[$op1]->className, "USERCONTROLFUNC");
                            $tmp_funcCallState->thisObjectVarId = $op1;
                            $tmp_funcCallState->thisObjectVarcvName = $cvVarName;
                        }
                    }
                    if(empty($tmp_funcCallState)){
                        $programCallState[$callStateIndex] = $tmp_funcCallState;
                        $callStateIndex++; 
                        $tmp_funcCallState = new FuncCallState('ERROR');
                    }
                }

                foreach ($object as $objectkey => $itemobject) {
                    if(isset($itemobject->cvName) and $itemobject->cvName == $cvVarName and $itemobject->className != "USERCONTROLLABLE"){
                        $object[$op1] = clone $object[$objectkey];
                        break;
                    }
                }

                if(!empty($fetch_flag) and $fetch_flag == 'global'){
                    
                    $globalVarFlag = 1;
                    foreach ($state->superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName]->funcOpArray as $key => $value) {
                        if ($key == strtolower($funcName)) {
                            $methodFlag = 1;
                        } else if ($key == "__call") {
                            $magicCallFlag = 1;
                        }
                    }
                }else if (!empty($object[$op1])) {
                    if(!empty($object[$op1]->funcOpArray)){
                        foreach ($object[$op1]->funcOpArray as $key => $value) {
                            if ($key == strtolower($funcName)) {
                                $methodFlag = 1;
                                break;
                            } else if ($key == "__call") {
                                $magicCallFlag = 1;
                                break;
                            }
                        }
                        if(!$methodFlag and !$magicCallFlag){
                            $className = $object[$op1]->className;
                            $classTraitNameArray = $this->classTraitSearch($className, $classOpArray);
                            if (!empty($classTraitNameArray)) {
                                $funcInitName = strtolower($funcName);
                                $tmpArray = $this->classTraitMethodMatch($classOpArray, $classTraitNameArray, $funcInitName);
                                $actualClassName = $tmpArray[0];
                                $initFuncOpArray = $tmpArray[1];
                                $programCallState[$callStateIndex] = $tmp_funcCallState;
                                $callStateIndex++;
                                $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                                $tmp_funcCallState->classMethodInit(1, $actualClassName, $funcInitName);
                                $tmp_funcCallState->thisObjectVarId = $op1;
                                $tmp_funcCallState->thisObjectVarcvName = $cvVarName;
                            }
                        }
                    }else{
                        $className = $object[$op1]->className;
                        $classTraitNameArray = $this->classTraitSearch($className, $classOpArray);
                        if(!empty($classTraitNameArray)){
                            $funcInitName = strtolower($funcName);
                            $tmpArray = $this->classTraitMethodMatch($classOpArray, $classTraitNameArray, $funcInitName);
                            $actualClassName = $tmpArray[0];
                            $initFuncOpArray = $tmpArray[1];
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++;
                            $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                            $tmp_funcCallState->classMethodInit(1, $actualClassName, $funcInitName);
                            $tmp_funcCallState->thisObjectVarId = $op1;
                            $tmp_funcCallState->thisObjectVarcvName = $cvVarName;
                        }else{
                            if (!$this->ifVar($op2_type, $op2)) {
                                $op2VarValue = new ValueStruct($op2);
                            } else {
                                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                                $op2VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2,$op2VarType, $cvVarNamelist);
                            }
                            $funcName = $op2VarValue->value;
                            if ($className == "class@anonymous") {
                                $anonymousClassIndex = 0;
                                foreach ($object[$op1]->anonymousClassInfo as $anonymousClassItem) {
                                    foreach ($anonymousClassItem->funcOpArray as $annoymousClassFuncItem) {
                                        if($annoymousClassFuncItem->funcName == $funcName){
                                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                                            $callStateIndex++;
                                            $tmp_funcCallState = new FuncCallState($annoymousClassFuncItem);
                                            $tmp_funcCallState->classMethodInit(1, $className, $funcName);
                                            $tmp_funcCallState->thisObjectVarId = $op1;
                                            $tmp_funcCallState->anonymousClassIndex = $anonymousClassIndex;
                                            break;
                                        }
                                    }
                                    $anonymousClassIndex++;
                                }
                            }
                            else if($className == "USERCONTROLLABLE"){
                                $dymClassInfo = $this->methodSearch($funcName, $classOpArray);
                                $object[$op1]->className = $dymClassInfo->className;
                                $tmpObjectOpArray = $this->classSearch($dymClassInfo->className, $classOpArray);
                                $this->classInit($exceptionState, $includedFileObject, $tmpObjectOpArray, $object[$op1], $dymClassInfo->className);
                                if($object[$op1]->dymFuncCallFlag == 1 and isset($dymClassInfo->funcOpArray['__construct'])){
                                    $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__construct", "IS_CONST");
                                    $paraCount = count($dymClassInfo->funcOpArray['__construct']->funcArgs);
                                    for ($i=0; $i < $paraCount; $i++) {
                                        $setOpArray[] = $this->magicMethodOpArrayAdd(66, "SEND_VAR_EX", $lineNo, $object[$op1]->dymFuncParameters[$i][3], "IS_CV");
                                    }
                                    $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                                    $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                                    $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                                    $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                                    $methodMagicChangeBBCount = count($setOpArray);
                                }
                                $funcInitName = strtolower($funcName);
                                $initFuncOpArray = $object[$op1]->funcOpArray[$funcInitName];
                                $programCallState[$callStateIndex] = $tmp_funcCallState;
                                $callStateIndex++;
                                $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                                $tmp_funcCallState->classMethodInit(1, $dymClassInfo->className, $funcInitName);
                                $tmp_funcCallState->thisObjectVarId = $op1;
                                $tmp_funcCallState->thisObjectVarcvName = $cvVarName;
                            }
                            else{
                                $initFuncOpArray = $this->funcSearch($funcName, $thisFileFuncOpArray, $className);
                                $programCallState[$callStateIndex] = $tmp_funcCallState;
                                $callStateIndex++;
                                $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                                $tmp_funcCallState->classMethodInit(1, $className, $funcName);
                                $tmp_funcCallState->thisObjectVarId = $op1;
                                $tmp_funcCallState->thisObjectVarcvName = $cvVarName;
                            }
                        }
                    }

                }
                if($methodFlag){
                    $funcInitName = strtolower($funcName);
                    if(!$globalVarFlag){
                        $className = $object[$op1]->className;
                        $initFuncOpArray = $object[$op1]->funcOpArray[$funcInitName];
                    }else{
                        $className = $state->superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName]->className;
                        $initFuncOpArray = $state->superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName]->funcOpArray[$funcInitName];
                    }
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++; 
                    $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                    $tmp_funcCallState->classMethodInit(1, $className, $funcInitName);
                    $tmp_funcCallState->thisObjectVarId = $op1;
                    $tmp_funcCallState->thisObjectVarcvName = $cvVarName;
                }else if($magicCallFlag){
                    $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__call", "IS_CONST");
                    $setOpArray[] = $this->magicMethodOpArrayAdd(65, "SEND_VAL", $lineNo, $op2, "IS_CONST");
                    $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                    $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                    $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                    $methodMagicChangeBBCount = count($setOpArray);
                }
                else if($oldcallStateIndex == $callStateIndex){
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++;
                    $tmp_funcCallState = NULL;
                }
                break;
            case ZEND_INIT_STATIC_METHOD_CALL:
                if($this->ifVar($op1_type, $op1)){
                    $className = $localVar[$op1]->value;
                }else if(!empty($op1)) {
                    $className = $op1;
                }else{
                    if(empty($classOpArray[$thisObjectVarId])){
                        $thisObjectVarId = $state->tmp_funcCallState->className;
                    }
                    if($fetch_flag == 'static'){
                        $className = $thisObjectVarId;
                    }else if($fetch_flag == 'self'){
                        $className = $classOpArray[$thisObjectVarId]->funcOpArray[$thisFuncName]->scopeClassName;
                    }
                }
                if($this->ifVar($op2_type, $op2)){
                    $funcInitName = strtolower($localVar[$op2]->value);
                }else{
                    $funcInitName = strtolower($op2);
                }

                $methodFlag = 0;
                $magicCallFlag = 0;
                $methodincludeFlag = 0;
                $magicincludeCallFlag = 0;
                if (!empty($classOpArray[$className])) {
                    if (array_key_exists($funcInitName, $classOpArray[$className]->funcOpArray)) {
                        $methodFlag = 1;
                    } else if (isset($classOpArray[$className]->funcOpArray["__callstatic"])) {
                        $magicCallFlag = 1;
                    }
                }else{
                    foreach ($includedFileObject as $fileInfoItem) {
                        if($methodincludeFlag or $magicincludeCallFlag){
                            break;
                        }
                        else if (!empty($fileInfoItem->classOpArray)) {
                            foreach ($fileInfoItem->classOpArray as $classItem) {
                                if($classItem->className == $className){
                                    if (array_key_exists($funcInitName, $classItem->funcOpArray)) {
                                        $methodincludeFlag = 1;
                                        $classOpArray = array_merge($fileInfoItem->classOpArray, $classOpArray);
                                        break;
                                    } else if (isset($classItem->funcOpArray["__callstatic"])) {
                                        $magicincludeCallFlag = 1;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($methodFlag or $methodincludeFlag) {
                    $initFuncOpArray = $classOpArray[$className]->funcOpArray[$funcInitName];
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++;     
                    $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                    $tmp_funcCallState->classMethodInit(1, $className, $funcInitName);
                    $tmp_funcCallState->thisObjectVarId = $op1;
                    $tmp_funcCallState->thisObjectVarcvName = $this->getCVName($op1, $cvVarNamelist);
                } else if ($magicCallFlag or $magicincludeCallFlag) {
                    $setOpArray[] = $this->magicMethodOpArrayAdd(113, "INIT_STATIC_METHOD_CALL", $lineNo, $className, "IS_CONST", "__callstatic", "IS_CONST");
                    $setOpArray[] = $this->magicMethodOpArrayAdd(65, "SEND_VAL", $lineNo, $funcInitName, "IS_CONST");
                    $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                    $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                    $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                    $methodMagicChangeBBCount = count($setOpArray);
                } else{
                    $programCallState[$callStateIndex] = $tmp_funcCallState;
                    $callStateIndex++;
                    $tmp_funcCallState = new FuncCallState('ERROR');
                }
                break;
            case ZEND_INIT_USER_CALL:
                if ($this->ifVar($op2_type, $op2)) {
                    $op2VarType = IS_LOCAL;
                    $op2Var = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2,$op2VarType, $cvVarNamelist);
                    $funcInitName = $op2Var->value;
                    $initFuncOpArray = $this->funcSearch($funcInitName, $thisFileFuncOpArray);
                    if(!empty($includedFileObject) and !is_object($initFuncOpArray) and $initFuncOpArray == 'ERROR'){
                        foreach ($includedFileObject as $obj) {
                            $initFuncOpArray = $this->funcSearch($funcInitName, $obj->funcOpArray);
                            if (is_object($initFuncOpArray)) {
                                $programCallState[$callStateIndex] = $tmp_funcCallState;
                                $callStateIndex++; 
                                $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                            }
                        }
                        if(is_null($tmp_funcCallState)){
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++; 
                            $tmp_funcCallState = new FuncCallState('ERROR');
                        }
                    }else{
                        $programCallState[$callStateIndex] = $tmp_funcCallState;
                        $callStateIndex++; 
                        $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                    }
                } else {
                    $funcInitName = $op2;
                    foreach ($includedFileObject as $obj) {
                        $initFuncOpArray = $this->funcSearch($funcInitName, $obj->funcOpArray);
                        if (is_object($initFuncOpArray)) {
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++; 
                            $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                        }
                    }
                }
                break;
            case ZEND_INIT_DYNAMIC_CALL:
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2);
                if($op2VarType == IS_LOCAL){
                    $dymfuncStruct = $this->varCalc($localVar, $op2);
                    $dymfuncName = $dymfuncStruct->value;
                    $initFuncOpArray = $this->funcSearch($dymfuncName, $thisFileFuncOpArray);
                    if ($dymfuncStruct->if_tainted == 1){
                        $programCallState[$callStateIndex] = $tmp_funcCallState;
                        $callStateIndex++; 
                        $tmp_funcCallState = new FuncCallState(["USERFUNC", "POSSIBLE_CE", [-1], []]);
                    }else if (!is_object($initFuncOpArray) and $initFuncOpArray == 'ERROR') {
                        foreach ($includedFileObject as $obj) {
                            $initFuncOpArray = $this->funcSearch($dymfuncName, $obj->funcOpArray);
                            if (is_object($initFuncOpArray)) {
                                $programCallState[$callStateIndex] = $tmp_funcCallState;
                                $callStateIndex++; 
                                $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                            }
                        }
                        if (is_null($tmp_funcCallState)) {
                            $programCallState[$callStateIndex] = $tmp_funcCallState;
                            $callStateIndex++; 
                            $tmp_funcCallState = new FuncCallState('ERROR');
                        }
                    } else {
                        $programCallState[$callStateIndex] = $tmp_funcCallState;
                        $callStateIndex++; 
                        $tmp_funcCallState = new FuncCallState($initFuncOpArray);
                    }
                    break;
                }
                else if($op2VarType == IS_ARRAY){
                    $tmpObjectId = $array[$op2]->keyArray[0]->value;
                    $tmpFuncNameStruct = $array[$op2]->keyArray[1];
                    if ($tmpFuncNameStruct->if_tainted == 1) {
                        $programCallState[$callStateIndex] = $tmp_funcCallState;
                        $callStateIndex++;
                        $tmp_funcCallState = new FuncCallState(["USERFUNC", "POSSIBLE_CE", [-1], []]);
                    }else{
                        $tmpFuncName = $tmpFuncNameStruct->value;
                        $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $tmpObjectId, "IS_CV", $tmpFuncName, "IS_CONST");
                        $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                        $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                        $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                        $methodMagicChangeBBCount = count($setOpArray);
                    }
                    break;
                }
                else if($op2VarType == IS_OBJECT){
                    foreach ($object[$op2]->funcOpArray as $key => $value) {
                        if ($key == "__invoke") {
                            $magicCallFlag = 1;
                        }
                    }
                    if($magicCallFlag){
                        $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op2, "IS_CV", "__invoke", "IS_CONST");
                        $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                        $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                        $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                        $methodMagicChangeBBCount = count($setOpArray);
                    }
                    break;
                }
                $programCallState[$callStateIndex] = $tmp_funcCallState;
                $callStateIndex++;
                $tmp_funcCallState = new FuncCallState('ERROR');
                break;

            case ZEND_SEND_VAL:
                if (!$this->ifVar($op1_type, $op1)) {
                    $varValue = new ValueStruct($op1);
                    $tmp_funcCallState->funcparameters[] = [IS_LOCAL, $varValue, 0, NULL];
                }else{
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                    $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                    $tmp_funcCallState->funcparameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                    if (isset($op1VarValue->if_tainted) and $op1VarValue->if_tainted == 1) {
                        $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine, $op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                        $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                    }
                }
                break;
            case ZEND_SEND_FUNC_ARG:       
            case ZEND_SEND_VAR:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                $tmp_funcCallState->funcparameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                if (isset($op1VarValue->if_tainted) and $op1VarValue->if_tainted == 1) {
                    $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine,$op1VarValue->taintedLine, $processFlag . (string)$lineNo."_FCALL");
                    $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                }
                break;
            case ZEND_SEND_VAL_EX:
                if (!$this->ifVar($op1_type, $op1)) {
                    $varValue = new ValueStruct($op1);
                    if(is_null($tmp_funcCallState)){
                        break;
                    }
                    if (empty($tmp_funcCallState->funcInfo) and $tmp_funcCallState->ifClassMethod == 2) {
                        $randNum = '!' . (string)rand(1000,9999);       
                        $object[$tmp_funcCallState->thisObjectVarId]->dymFuncCallFlag = 1;
                        $object[$tmp_funcCallState->thisObjectVarId]->dymFuncParameters[] = [IS_LOCAL, $varValue, 0, $randNum];
                        $local[$randNum] = $varValue;
                    } else {
                        $tmp_funcCallState->funcparameters[] = [IS_LOCAL, $varValue, 0, NULL];
                    }
                    
                    if ($exceptionState->exceptionFlag) {
                        $exceptionState->exceptionMessageVar = $varValue;
                    }
                } else {
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                    $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                    if (is_null($tmp_funcCallState)) {
                        break;
                    }
                    if (empty($tmp_funcCallState->funcInfo) and $tmp_funcCallState->ifClassMethod == 2) {
                        $object[$tmp_funcCallState->thisObjectVarId]->dymFuncCallFlag = 1;
                        $object[$tmp_funcCallState->thisObjectVarId]->dymFuncParameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                    } else {
                        $tmp_funcCallState->funcparameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                    }
                    if ($exceptionState->exceptionFlag) {
                        $exceptionState->exceptionMessageVar = $op1VarValue;
                    }
                    if (isset($op1VarValue->if_tainted) and $op1VarValue->if_tainted == 1) {
                        $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine, $op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                        $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                    }
                }

                break;
            case ZEND_SEND_VAR_EX:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                if(is_null($tmp_funcCallState)){
                    break;
                }
                if (empty($tmp_funcCallState->funcInfo) and $tmp_funcCallState->ifClassMethod == 2) {
                    $object[$tmp_funcCallState->thisObjectVarId]->dymFuncCallFlag = 1;
                    $object[$tmp_funcCallState->thisObjectVarId]->dymFuncParameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                }else{
                    $tmp_funcCallState->funcparameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                }
                if ($exceptionState->exceptionFlag) {
                    $exceptionState->exceptionMessageVar = $op1VarValue;
                }
                if (isset($op1VarValue->if_tainted) and $op1VarValue->if_tainted == 1) {
                    $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine, $op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                    $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                }
                break;
            case ZEND_SEND_REF:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                $tmp_funcCallState->funcparameters[] = [$op1VarType, $op1VarValue, 1, $op1];
                if (isset($op1VarValue->if_tainted) and $op1VarValue->if_tainted == 1) {
                    $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine,$op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                    $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                }
                break;
            case ZEND_SEND_ARRAY:
                $op1VarType = IS_ARRAY;
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                foreach ($op1VarValue->keyArray as $arrayItem) {
                    $tmp_funcCallState->funcparameters[] = [IS_LOCAL, $arrayItem, $arrayItem->if_tainted, $op1];
                    if (isset($arrayItem->if_tainted) and $arrayItem->if_tainted == 1) {
                        $taintedChains = $this->taintedChainAdd($taintedChains, $arrayItem->taintedSourceLine,$op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                        $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                        $arrayItem->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                    }
                }
                break;
            case ZEND_SEND_USER:
                if($this->ifVar($op1_type, $op1)){
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                    $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                    $tmp_funcCallState->funcparameters[] = [$op1VarType, $op1VarValue, 0, $op1];
                    if (isset($op1VarValue->if_tainted) and $op1VarValue->if_tainted == 1) {
                        $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine,$op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                        $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                    }
                }else{
                    $varValue = new ValueStruct($op1);
                    $tmp_funcCallState->funcparameters[] = [IS_LOCAL, $varValue, 0, NULL];
                }
                break;

            case ZEND_SEND_UNPACK:
                $op1VarType = IS_ARRAY;
                $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1,$op1VarType, $cvVarNamelist);
                foreach ($op1VarValue->keyArray as $arrayItem) {
                    $tmp_funcCallState->funcparameters[] = [IS_LOCAL, $arrayItem, 0, $op1];
                    if (isset($arrayItem->if_tainted) and $arrayItem->if_tainted == 1) {
                        $taintedChains = $this->taintedChainAdd($taintedChains, $arrayItem->taintedSourceLine,$op1VarValue->taintedLine, $processFlag . (string) $lineNo . "_FCALL");
                        $op1VarValue->taintedLine = $processFlag . (string) $lineNo . "_FCALL";
                    }
                }
                break;

            case ZEND_DO_FCALL:
                if(is_null($tmp_funcCallState)){
                    $callStateIndex--;
                    $tmp_funcCallState = $programCallState[$callStateIndex];
                    unset($programCallState[$callStateIndex]);
                    break;
                }
                if(is_array($tmp_funcCallState->funcInfo)){
                    debugEcho("[DEBUG]internelFunc was called\n", $DebugFlag);
                    switch ($tmp_funcCallState->funcInfo[1]) {
                        case 'DIY':
                            $tmp_VarValue = new ValueStruct($tmp_funcCallState->funcInfo[3]);
                            $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_LOCAL, $tmp_VarValue);
                            break;
                        case 'FILESYS':
                            $this->fileSysFuncDeal($includePaths, $currentWorkPath, $tmp_funcCallState, $localVar, $array, $object, $result, $superglobals);
                            break;
                        case 'OB':
                            $this->obFuncModelingDeal($tmp_funcCallState, $obState, $opArray, $thisopId, $opcodeCount, $methodMagicChangeBBCount);
                            if($tmp_funcCallState->funcInfo[0] == 'ob_start' and !empty($tmp_funcCallState->funcparameters)){
                                $obState->obCallableFlag = 1;
                                $obState->obCallableFuncName = $tmp_funcCallState->funcparameters[0][1]->value;
                            }
                            if($tmp_funcCallState->funcInfo[0] == 'ob_get_contents' and $obState->obTaintedFlag){
                                debugEcho("[DEBUG]OB Possible XSS FOUND!\n",$DebugFlag, 'yellow');
                                $thisLineNo = $processFlag . (string)$lineNo;
                                $this->taintedChianNew($taintedChains, $thisLineNo);
                                $this->taintedChainSearch($vulChains, "XSS", $taintedChains, $thisLineNo, $thisLineNo, $thisLineNo,$DebugFlag);
                                $obState->obTaintedFlag = 0;
                                $tmp_result = new ValueStruct();
                                $tmp_result->taintInfoSave(1, "OB", $thisLineNo, $thisLineNo);
                                $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_result);
                            }
                            break;
                        case 'ARRAYMODELING':
                            debugEcho("[DEBUG]InternelFunc(ArrayModel) was called!\n", $DebugFlag);
                            $tmp_param_index_array = $tmp_funcCallState->funcInfo[2];
                            $interFuncName = $tmp_funcCallState->funcInfo[0];
                            $taint_param_array = $this->internalFuncParamIndex($tmp_param_index_array, $tmp_funcCallState->funcparameters);
                            $tmp_VarValue = $this->arrayModelingFuncDeal($cvVarNamelist, $localVar, $array, $interFuncName, $taint_param_array, $tmp_funcCallState->funcparameters, $DebugFlag);
                            if($tmp_funcCallState->funcInfo[0] == 'in_array'){
                                $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_LOCAL, $tmp_VarValue);
                                break;
                            }
                            if(is_string($tmp_VarValue)){
                                if($tmp_VarValue == 'break'){
                                    break;
                                }
                                foreach ($taint_param_array as $arrayItem) {
                                    if($arrayItem[1]->if_tainted == 1){
                                        $taint_source_Line = $arrayItem[1]->taintedSourceLine;
                                        $taintedLine = $arrayItem[1]->taintedLine;
                                        $thisLineNo = $processFlag . (string)$lineNo;
                                        $this->taintedChainSearch($vulChains, $tmp_VarValue, $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                                    }
                                }

                            }else if(is_array($tmp_VarValue)){
                                $tmp_VarValue = $this->array2ArrayStruct($tmp_VarValue);
                                $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_ARRAY, $tmp_VarValue);
                                foreach ($taint_param_array as $arrayItem) {
                                    if ($arrayItem[1]->if_tainted == 1) {
                                        debugEcho("[DEBUG]Possible Vulnerable FOUND!\n", $DebugFlag, 'yellow');
                                        $taint_source_Line = $arrayItem[1]->taintedSourceLine;
                                        $taintedLine = $arrayItem[1]->taintedLine;
                                        $thisLineNo = $processFlag . (string)$lineNo;
                                        $this->taintedChainSearch($vulChains, "POSSIBLE_VUL", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                                    }
                                }
                            }
                            else if(is_object($tmp_VarValue)){
                                $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_ARRAY, $tmp_VarValue);
                                foreach ($tmp_VarValue->keyArray as $arrayItem) {
                                    if ($arrayItem->if_tainted == 1) {
                                        debugEcho("[DEBUG]Possible Vulnerable FOUND!\n",$DebugFlag, 'yellow');
                                        $taint_source_Line = $arrayItem->taintedSourceLine;
                                        $taintedLine = $arrayItem->taintedLine;
                                        $thisLineNo = $processFlag . (string)$lineNo;
                                        $this->taintedChainSearch($vulChains, "POSSIBLE_VUL", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                                    }
                                }
                            }
                            break;
                        case 'ENCODE':
                        case 'DECODE':
                            debugEcho("[DEBUG]InternelFunc(Encode/Decode) was called\n", $DebugFlag);
                            $tmp_param_index = $tmp_funcCallState->funcInfo[2][0] - 1;
                            $interFuncName = $tmp_funcCallState->funcInfo[0];
                            $reverseFuncName = $tmp_funcCallState->funcInfo[3];
                            $taint_param = $tmp_funcCallState->funcparameters[$tmp_param_index];
                            if ($taint_param[0] != 907) {
                                $tmp_result = new ValueStruct();
                                $tmp_callChains = [$interFuncName];
                                $tmp_reverseChains = [$reverseFuncName];
                                $tmp_source = [$taint_param];
                                $tmp_result->internalFuncCallChain->callChains = $tmp_callChains;
                                $tmp_result->internalFuncCallChain->reverseChains = $tmp_reverseChains;
                                $tmp_result->internalFuncCallChain->sourceChains = $tmp_source;
                                $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_result);
                            } else {
                                $tmp_result = $taint_param[1];
                                if ($this->varRecover($tmp_result->internalFuncCallChain, $interFuncName)) {
                                    array_pop($tmp_result->internalFuncCallChain->callChains);
                                    array_pop($tmp_result->internalFuncCallChain->reverseChains);
                                    $tmp_var_reverse = array_pop($tmp_result->internalFuncCallChain->sourceChains);
                                    if (count($tmp_result->internalFuncCallChain->sourceChains) == 0) {
                                        $tmp_VarType = $tmp_var_reverse[0];
                                        $tmp_opNum = $tmp_var_reverse[3];
                                    } else {
                                        $tmp_VarType = $tmp_result->internalFuncCallChain->sourceChains[0][0];
                                        $tmp_opNum = $tmp_result->internalFuncCallChain->sourceChains[0][3];
                                    }
                                    $tmp_VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $tmp_opNum, $tmp_VarType, $cvVarNamelist);
                                    $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, $tmp_VarType, $tmp_VarValue);
                                } else {
                                    array_push($tmp_result->internalFuncCallChain->callChains, $interFuncName);
                                    array_push($tmp_result->internalFuncCallChain->reverseChains, $reverseFuncName);
                                    array_push($tmp_result->internalFuncCallChain->sourceChains, $taint_param);
                                    $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_result);
                                }
                            }
                            break;
                        case 'TAINT':                        
                            debugEcho("[DEBUG]InternelFunc(Taint) was called\n", $DebugFlag);
                            $tmp_param_index_array = $tmp_funcCallState->funcInfo[2];
                            $interFuncName = $tmp_funcCallState->funcInfo[0];
                            $taint_param_array = $this->internalFuncParamIndex($tmp_param_index_array, $tmp_funcCallState->funcparameters);
                            foreach ($taint_param_array as $taint_item) {
                                if($taint_item[1]->if_tainted){
                                    $tmp_VarValue = new ValueStruct();
                                    $tmp_VarValue->taintInfoSave($taint_item[1]->if_tainted, $taint_item[1]->taintedSource, $taint_item[1]->taintedSourceLine, $taint_item[1]->taintedLine);
                                    $taintedChains = $this->taintedChainAdd($taintedChains,$tmp_VarValue->taintedSourceLine, $tmp_VarValue->taintedLine,  $processFlag . (string)$lineNo);
                                    array_push($tmp_VarValue->taintedSource, $taint_item[3]);
                                    $tmp_VarValue->taintedLine = $processFlag . (string)$lineNo;
                                    $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_VarValue);
                                }else{
                                    $tmp_VarValue = new ValueStruct();
                                    $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_VarValue);
                                }
                            }
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [0, "TAINT"];
                            }
                            break;
                        case 'ARRAY':                       
                            debugEcho("[DEBUG]InternelFunc(Array Taint) was called\n", $DebugFlag);
                            $tmp_param_index_array = $tmp_funcCallState->funcInfo[2];
                            $interFuncName = $tmp_funcCallState->funcInfo[0];
                            $taint_param_array = $this->internalFuncParamIndex($tmp_param_index_array, $tmp_funcCallState->funcparameters);
                            foreach ($taint_param_array as $taint_item) {
                                if ($taint_item[1]->if_tainted) {
                                    $tmp_VarValue = new ValueStruct();
                                    $tmp_VarValue->taintInfoSave($taint_item[1]->if_tainted, $taint_item[1]->taintedSource, $taint_item[1]->taintedSourceLine,$taint_item[1]->taintedLine);
                                    $taintedChains = $this->taintedChainAdd($taintedChains,$tmp_VarValue->taintedSourceLine, $tmp_VarValue->taintedLine, $processFlag . (string)$lineNo);
                                    array_push($tmp_VarValue->taintedSource, $taint_item[3]);
                                    $tmp_VarValue->taintedLine = $processFlag . (string)$lineNo;
                                    if(empty($result)){
                                        $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $taint_param_array[0][3], IS_INTERFUNC, $tmp_VarValue);
                                    }else{
                                        $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_VarValue);
                                    }
                                } else {
                                    $tmp_VarValue = new ValueStruct();
                                    $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, IS_INTERFUNC, $tmp_VarValue);
                                }
                            }
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [0, "TAINT"];
                            }
                            break;
                        case 'SETCOOKIE':
                            if(isset($tmp_funcCallState->funcparameters[0][1]->value)){
                                if(isset($tmp_funcCallState->funcparameters[1][1])){
                                    $superglobals->superGlobalArrays["_COOKIE"]->keyArray[$tmp_funcCallState->funcparameters[0][1]->value] = $tmp_funcCallState->funcparameters[1][1];
                                }else{
                                    $superglobals->superGlobalArrays["_COOKIE"]->keyArray[$tmp_funcCallState->funcparameters[0][1]->value] = new ValueStruct(0);
                                }
                                $superglobals->superGlobalArrays["_COOKIE"]->keyArray[$tmp_funcCallState->funcparameters[0][1]->value]->taintedSource[] = "_COOKIE";
                            }
                            break;
                        case 'SAFE':                        
                            debugEcho("[DEBUG]InternelFunc(Safe) was called\n", $DebugFlag);
                            break;
                        case 'ERRORSET':
                            debugEcho("[DEBUG]InternelFunc(ErrorSet) was called\n", $DebugFlag);
                            $tmp_param_index_array = $tmp_funcCallState->funcInfo[2];
                            $taint_param_array = $this->internalFuncParamIndex($tmp_param_index_array, $tmp_funcCallState->funcparameters);
                            $errorState->errorFlag = 1;
                            $errorState->errorMessageVar = $taint_param_array;
                            break;
                        case 'ERRORGET':
                            debugEcho("[DEBUG]InternelFunc(ErrorGet) was called\n", $DebugFlag);
                            if($errorState->errorFlag == 1){
                                foreach ($errorState->errorMessageVar as $errorMessageVarItem) {
                                    if($errorMessageVarItem[1]->if_tainted == 1){
                                        if ($funcCheckFlag) {
                                            $funcTypeForCheck = [1, "XSS"];
                                        }else{
                                            debugEcho("[DEBUG]XSS FOUND!\n",$DebugFlag, 'red');
                                        }
                                        $taint_source_Line = $errorMessageVarItem[1]->taintedSourceLine;
                                        $taintedLine = $errorMessageVarItem[1]->taintedLine;
                                        $thisLineNo = $processFlag . (string)$lineNo;
                                        $this->taintedChainSearch($vulChains, "XSS", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                                    }
                                }
                            }
                            break;
                        default:
                            debugEcho("[DEBUG]InternelFunc(Sink) was called\n", $DebugFlag, 'red');
                            $sinkFuncType = $tmp_funcCallState->funcInfo[1];
                            if($sinkFuncType == 'XSS' and $obState->obFlag == 1){
                                $taint_param_array = $this->internalFuncParamIndex($tmp_funcCallState->funcInfo[2], $tmp_funcCallState->funcparameters);
                                if ($obState->obCallableFlag) {
                                    foreach ($taint_param_array as $taintItem) {
                                        if($taintItem[1]->if_tainted){
                                            $tmpid = rand(1000, 10000);
                                            $tmpVarid = "$" . (string) $tmpid;
                                            $this->varClone($globalState, $localVar, $array, $object, $taintItem[3], $taintItem[0], $tmpVarid);
                                            $obState->obOpArray[] = $this->magicMethodOpArrayAdd(61, "INIT_FCALL", $lineNo, "", "IS_UNUSED", $obState->obCallableFuncName, "IS_CONST");
                                            $obState->obOpArray[] = $this->magicMethodOpArrayAdd(117, "SEND_VAR", $lineNo, $tmpVarid, "IS_CV");
                                            $obState->obOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                                            break;
                                        }
                                    }
                                }else{
                                    $obState->obFuncCallState[] = $tmp_funcCallState;
                                    foreach ($taint_param_array as $taintItem) {
                                        if($taintItem[1]->if_tainted){
                                            $obState->obTaintedFlag = 1;
                                        }
                                    }
                                }
                                break;
                            }
                            if($tmp_funcCallState->funcInfo[2] == 1001){
                                $exceptionPara = $exceptionState->exceptionMessageVar;
                                if($exceptionPara->if_tainted == 1){
                                    $taint_source_Line = $exceptionPara->taintedSourceLine;
                                    $taintedLine = $exceptionPara->taintedLine;
                                }
                            }else{
                                $taintLineInfo = $this->sinkFuncParamCheck($tmp_funcCallState->funcInfo[2], $tmp_funcCallState->funcparameters);
                                if(!is_bool($taintLineInfo) and !empty($taintLineInfo)){
                                    $taint_source_Line = $taintLineInfo[0];
                                    $taintedLine = $taintLineInfo[1];
                                }else{
                                    $taint_source_Line = $taintLineInfo;
                                }
                            }

                            if($taint_source_Line){
                                if ($funcCheckFlag) {
                                    $funcTypeForCheck = [1, $sinkFuncType];
                                }else{
                                    debugEcho("[DEBUG] " . $sinkFuncType . " FOUND!\n", $DebugFlag, 'red');
                                }
                                $thisLineNo = $processFlag . (string)$lineNo;
                                if(is_bool($taint_source_Line)){
                                    debugEcho('[+] TaintedChain: ' . $thisLineNo . "\n", $DebugFlag);
                                    $vulChains[] = [$sinkFuncType, $thisLineNo];
                                }else{
                                    $this->taintedChainSearch($vulChains, $sinkFuncType, $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                                }
                                if ($tmp_funcCallState->funcInfo[0] == 'file_get_contents'){
                                    $localVar[$result] = new ValueStruct();
                                    $localVar[$result]->taintInfoSave(1, "SINKFUNC", $taint_source_Line, $taintedLine);
                                }

                            }
                            break;
                    }
                    if ($funcCheckFlag and empty($funcTypeForCheck)) {
                        $funcTypeForCheck = [0, "SAFE"];
                    }
                    $callStateIndex--;
                    $tmp_funcCallState = $programCallState[$callStateIndex];
                    unset($programCallState[$callStateIndex]);
                    break;
                }
                else if($tmp_funcCallState->funcInfo == "ERROR"){
                    $callStateIndex--;
                    $tmp_funcCallState = $programCallState[$callStateIndex];
                    unset($programCallState[$callStateIndex]);
                    if($result_type != "IS_UNUSED"){
                        $localVar[$result] = new ValueStruct(0);
                    }
                    break;
                }
                if($tmp_funcCallState->ifClassMethod){
                    $tmpObj = new OpcodeParse();
                    if(empty($tmp_funcCallState->funcInfo->phpFilePath)){
                        if($tmp_funcCallState->funcInfo->funcType == 'INTERNAL'){
                            $this->internalClassMethodDeal($tmp_funcCallState, $localVar, $array, $object, $result);
                            $callStateIndex--;
                            $tmp_funcCallState = $programCallState[$callStateIndex];
                            unset($programCallState[$callStateIndex]);
                            break;
                        }else{
                            $callStateIndex--;
                            $tmp_funcCallState = $programCallState[$callStateIndex];
                            unset($programCallState[$callStateIndex]);
                            break;
                        }
                    }
                    $tmpFilePath = $tmp_funcCallState->funcInfo->phpFilePath;
                    $tmpPath = pathinfo($tmpFilePath);
                    $tmpOpcodePath = $tmpPath['dirname'] . "/" . $tmpPath['filename'] . ".opcode";
                    $tmpFilePath =  $tmpPath['dirname'] . "/" . $tmpPath['filename'] . ".php";
                    $checkFlag = 0;
                    if (!empty($includedFileObject)) {
                        foreach ($includedFileObject as $item) {
                            if ($item->phpFilePath == $tmpFilePath) {
                                $checkFlag = 1;
                                $tmpfileObject = $item;
                                break;
                            }
                        }
                        if (!$checkFlag) {
                            $tmpfileObject = $tmpObj->parse($tmpOpcodePath, $tmpFilePath);
                        }
                    } else {
                        $tmpfileObject = $tmpObj->parse($tmpOpcodePath, $tmpFilePath);
                    }
                    $funcMer = array_merge($tmpfileObject->funcOpArray, $thisFileFuncOpArray);
                    $classMer = array_merge($tmpfileObject->classOpArray, $classOpArray);
                    $tmpfileObject->funcOpArray = $funcMer;
                    $tmpfileObject->classOpArray = $classMer;

                    $calleeClassName = $tmp_funcCallState->className;
                    $calleefuncName = $tmp_funcCallState->methodName;
                    $tmp_funcCallState->callPoint = $processFlag.(string)$lineNo;
                    if (empty($tmpfileObject->classOpArray[$calleeClassName]->funcOpArray[$calleefuncName]->cfgInfo)) {
                        @$tmpObj->cfgBuild($tmpfileObject);
                    }
                    $this->GLOBALSSAVE($superglobals, $localVar, $array,$object, $cvVarNamelist);
                    if($fetch_flag == 'global'){
                        $mergelocalVar = array_merge($globalState->localVar, $localVar);
                        $mergearray = array_merge($globalState->array, $array);
                        $mergeobject = array_merge($globalState->object, $object);
                        $tmp_funcCallState->globalState = new GlobalState($mergelocalVar, $mergearray, $mergeobject);
                    }else{
                        $tmp_funcCallState->globalState = new GlobalState($localVar, $array, $object);
                    }
                    $tmp_funcCallState->superglobals = $superglobals;
                    $tmp_funcCallState->taintedChains = $taintedChains;
                    $tmp_funcCallState->vulChains = $vulChains;
                    $this->funcRefArgDeal($tmp_funcCallState);
                    $this->callParaDeal($tmp_funcCallState);
                    if(is_array($tmpfileObject->classOpArray[$calleeClassName])){
                        $anonymousClassIndex = $tmp_funcCallState->anonymousClassIndex;
                        $tmpfileObject->classOpArray[$calleeClassName][$anonymousClassIndex]->funcOpArray[$calleefuncName]->funcCallState = $tmp_funcCallState;
                        @$tmpState = $this->dataFlowAnalysis(NULL, $tmpfileObject->classOpArray[$calleeClassName][$anonymousClassIndex]->funcOpArray[$calleefuncName],$tmpfileObject,$includedFileObject, $includeFileChain, $DebugFlag);
                    }else{
                        $tmpfileObject->classOpArray[$calleeClassName]->funcOpArray[$calleefuncName]->funcCallState = $tmp_funcCallState;
                        @$tmpState = $this->dataFlowAnalysis(NULL, $tmpfileObject->classOpArray[$calleeClassName]->funcOpArray[$calleefuncName],$tmpfileObject,$includedFileObject, $includeFileChain, $DebugFlag);
                    }
                    $state->exceptionState = $tmpState->exceptionState;
                    $exitFlag = $tmpState->exitFlag;
                    $state->taintedChains = $tmpState->taintedChains;
                    $state->vulChains = $tmpState->vulChains;
                    $thisFileFuncOpArray = $tmpfileObject->funcOpArray;
                    $classOpArray = $tmpfileObject->classOpArray;
                    $superglobals = $tmpState->superglobals;
                    $this->GLOBALSLOAD($superglobals, $localVar, $array, $object,'func');
                    if(!empty($tmp_funcCallState->returnVar)){
                        $returnVarType = $tmp_funcCallState->returnVar[0];
                        $returnVarValue = $tmp_funcCallState->returnVar[1];
                        $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, $returnVarType, $returnVarValue);
                    }else

                    $taintedChains = $tmp_funcCallState->taintedChains;
                    $callStateIndex--;
                    $tmp_funcCallState = $programCallState[$callStateIndex];
                    unset($programCallState[$callStateIndex]);
                }
                else{
                    $tmpObj = new OpcodeParse();
                    if(empty($tmp_funcCallState->funcInfo->phpFilePath)){
                        $callStateIndex--;
                        $tmp_funcCallState = $programCallState[$callStateIndex];
                        unset($programCallState[$callStateIndex]);
                        break;
                    }
                    $tmpFilePath = $tmp_funcCallState->funcInfo->phpFilePath;
                    $tmpPath = pathinfo($tmpFilePath);
                    $tmpOpcodePath = $tmpPath['dirname'] . "/" . $tmpPath['filename'] . ".opcode";
                    $tmpFilePath =  $tmpPath['dirname'] . "/" . $tmpPath['filename'] . ".php";
                    $checkFlag = 0;
                    if (!empty($includedFileObject)) {
                        foreach ($includedFileObject as $item) {
                            if ($item->phpFilePath == $tmpFilePath) {
                                $checkFlag = 1;
                                $tmpfileObject = $item;
                                break;
                            }
                        }
                        if (!$checkFlag) {
                            $tmpfileObject = $tmpObj->parse($tmpOpcodePath, $tmpFilePath);
                        }
                    } else {
                        $tmpfileObject = $tmpObj->parse($tmpOpcodePath, $tmpFilePath);
                    }
                    $funcMer = array_merge($tmpfileObject->funcOpArray, $thisFileFuncOpArray);
                    $classMer = array_merge($tmpfileObject->classOpArray, $classOpArray);
                    $tmpfileObject->funcOpArray = $funcMer;
                    $tmpfileObject->classOpArray = $classMer;

                    $calleefuncName = $tmp_funcCallState->funcInfo->funcName;
                    if (empty($tmpfileObject->funcOpArray[$calleefuncName]->cfgInfo)){
                        @$tmpObj->cfgBuild($tmpfileObject);
                    }
                    $this->GLOBALSSAVE($superglobals, $localVar, $array, $object, $cvVarNamelist);
                    if ($fetch_flag == 'global') {
                        $mergelocalVar = array_merge($globalState->localVar, $localVar);
                        $mergearray = array_merge($globalState->array, $array);
                        $mergeobject = array_merge($globalState->object, $object);
                        $tmp_funcCallState->globalState = new GlobalState($mergelocalVar, $mergearray, $mergeobject);
                    } else {
                        $tmp_funcCallState->globalState = new GlobalState($localVar, $array, $object);
                    }
                    $tmp_funcCallState->superglobals = $superglobals;
                    $tmp_funcCallState->taintedChains = $taintedChains;
                    $tmp_funcCallState->vulChains = $vulChains;
                    $tmp_funcCallState->callerName = $thisFuncName;
                    $tmp_funcCallState->callPoint = $processFlag . (string)$lineNo;
                    $this->funcRefArgDeal($tmp_funcCallState);
                    $this->callParaDeal($tmp_funcCallState);
                    $tmpfileObject->funcOpArray[$calleefuncName]->funcCallState = $tmp_funcCallState;
                    @$tmpState = $this->dataFlowAnalysis(NULL, $tmpfileObject->funcOpArray[$calleefuncName],$tmpfileObject,$includedFileObject, $includeFileChain, $DebugFlag);
                    $state->exceptionState = $tmpState->exceptionState;
                    $exitFlag = $tmpState->exitFlag;
                    $state->taintedChains = $tmpState->taintedChains;
                    $state->vulChains = $tmpState->vulChains;
                    $thisFileFuncOpArray = $tmpfileObject->funcOpArray;
                    $classOpArray = $tmpfileObject->classOpArray;
                    $superglobals = $tmpState->superglobals;
                    $this->GLOBALSLOAD($superglobals, $localVar, $array, $object, 'func');
                    if (!empty($tmp_funcCallState->returnVar)) {
                        $returnVarType = $tmp_funcCallState->returnVar[0];
                        $returnVarValue = $tmp_funcCallState->returnVar[1];
                        $this->funcParaAndReturnPass($tmp_funcCallState->globalState, $localVar, $array, $object, $result, $returnVarType, $returnVarValue);
                    }
                    $taintedChains = $tmp_funcCallState->taintedChains;
                    $callStateIndex--;
                    $tmp_funcCallState = $programCallState[$callStateIndex];
                    unset($programCallState[$callStateIndex]);
                }
                break;

            case ZEND_VERIFY_RETURN_TYPE:
                if(!empty($thisFileFuncOpArray[$thisFuncName])){
                    $funcRetType = $thisFileFuncOpArray[$thisFuncName]->funcRetType;
                    if ($funcRetType == 'string' and !empty($object[$op1])) {
                        $magicCallFlag = 0;
                        foreach ($object[$op1]->funcOpArray as $key => $tmpvalue) {
                            if ($key == "__tostring") {
                                $magicCallFlag = 1;
                            }
                        }
                        if ($magicCallFlag) {
                            $tmpid = rand(1000, 10000);
                            $tmpVarid = "$" . (string)$tmpid;
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__tostring", "IS_CONST", "", "IS_UNUSED", "", "", "");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", 0, "", "IS_UNUSED", "", "IS_UNUSED", $tmpVarid, "IS_VAR", "", "", "");
                            $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                            $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                            $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                            unset($setOpArray);
                        }
                    }
                }

                break;
            
            case ZEND_YIELD:
                $yieldState->yieldFlag = 1;
                if(!empty($yieldState->yieldArrayVar)){
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                    $op1ValueStruct = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                    $yieldState->yieldArrayVar->keyArray[] = clone $op1ValueStruct;
                }else{
                    $yieldState->yieldArrayVar = new ArrayStruct();
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                    $op1ValueStruct = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                    $yieldState->yieldArrayVar->keyArray[] = clone $op1ValueStruct;
                }
                break;
            case ZEND_GENERATOR_RETURN:
                if($yieldState->yieldFlag == 1){
                    $tmp_funcCallState->returnVar = [902, $yieldState->yieldArrayVar];
                    unset($yieldState);
                }
                break;

            case ZEND_RETURN_BY_REF:
            case ZEND_RETURN:
                if(is_null($tmp_funcCallState) or $tmp_funcCallState->returnCount==1){
                    break;
                }else{
                    $tmp_funcCallState->returnCount++;
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                    if ($opcodeNo == ZEND_RETURN_BY_REF) {
                        switch ($op1VarType) {
                            case IS_INIT:
                            case IS_LOCAL:
                                $localVar[$op1]->if_ref = "REF_VAR";
                                $op1VarValue = $localVar[$op1];
                                break;
                            case IS_ARRAY:
                                $op1VarValue = $array[$op1];
                                break;
                            case IS_OBJECT:
                                break;
                            default:
                                break;
                        }
                    }else{
                        if(isset($cvVarNamelist)){
                            $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                        }else{
                            $op1VarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType);
                        }

                    }
                    $tmp_funcCallState->returnVar = [$op1VarType, $op1VarValue];
                    if($op1VarValue->if_tainted){
                        $taintedChains = $this->taintedChainAdd($taintedChains, $op1VarValue->taintedSourceLine, $op1VarValue->taintedLine, $processFlag . (string)$lineNo);
                        $op1VarValue->taintedLine = $processFlag . (string)$lineNo;
                    }

                    if(!empty($tmp_funcCallState->funcparameters)){
                        foreach ($tmp_funcCallState->funcparameters as $key => &$para) {
                            if ($para[1]->if_ref == 1) {
                                $this->funcArgRefReturn($globalState, $localVar, $array, $object, $para[1]->refSource, $para[1]->refType, $para[1]->value);
                                $this->funcArgRefReturnS($superglobals,$localVar, $array, $object, $para[1]);
                            }
                        }
                    }

                    $this->retParaDeal($tmp_funcCallState);
                    $staticBind = $tmp_funcCallState->staticVar;
                    if(!empty($staticBind)){
                        foreach ($staticBind as $key => $value) {
                            $staticType = $tmp_funcCallState->funcInfo->staticVar[$key][1];
                            if ($staticType == IS_LOCAL) {
                                $tmp_funcCallState->funcInfo->staticVar[$key][0] = $localVar[$value[0]];
                            } else if ($staticType == IS_ARRAY) {
                                $tmp_funcCallState->funcInfo->staticVar[$key][0] = $array[$value[0]];
                            }
                        }
                    }
                    break;
                }

            case ZEND_THROW:
                $catchInfo = $this->catchCheck($opArray);
                if($catchInfo == FALSE){
                    $exitFlag = 1;
                }else{
                    $throwCount++;
                    if($throwCount > 5){
                        $exitFlag = 1;
                        $throwCount = 0;
                    }
                    $jmpAddr = $catchInfo;
                    
                }
                break;

            case ZEND_FETCH_OBJ_W: 
                if(empty($op1)){
                    $classcvName = $state->tmp_funcCallState->thisObjectVarcvName;
                    $tmpValue = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$classcvName]->propertiesArray[$op2][1];
                    $localVar[$result] = new ValueStruct($tmpValue, 0, $thisObjectVarId, "IS_OBJECT", $op2);
                }
                break;

            case ZEND_DECLARE_LAMBDA_FUNCTION:
                $localVar[$result] = new ValueStruct($op1);
                break;
            case ZEND_DECLARE_FUNCTION:
                $this->shortNameSearch($thisFileFuncOpArray, $op1);
                break;
            case ZEND_BIND_LEXICAL:
                $funcName = $localVar[$op1]->value;
                $bind_if_ref = $extended_value & 1;
                $bind_funcInfo = &$thisFileFuncOpArray[$funcName];
                $cvVarName = $this->getCVName($op2, $cvVarNamelist);

                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2);
                $bind_valueStruct = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2, $op2VarType, $cvVarNamelist);
                $bind_cv_id = $bind_funcInfo->compiledVars[$cvVarName];
                $bind_funcInfo->staticVar[$cvVarName][0] = clone $bind_valueStruct;
                $bind_funcInfo->staticVar[$cvVarName][1] = $op2VarType;
                if($bind_if_ref){
                    $bind_funcInfo->staticVar[$cvVarName][0]->refSave("REF_VAR", $op2, $this->typeToGlobal($op2VarType));
                }
                break;
            case ZEND_BIND_STATIC:
                if ($op1_type == "IS_CV") {
                    $cvVarName = $this->getCVName($op1, $tmp_funcCallState->funcInfo->compiledVars);
                }
                if (!empty($tmp_funcCallState->funcInfo->staticVar[$cvVarName][1])) {
                    $staticVarInfo = $tmp_funcCallState->funcInfo->staticVar[$cvVarName];
                    $staticType = $staticVarInfo[1];
                    if ($staticVarInfo[0]->if_ref and $staticVarInfo[0]->refType == "GLOBAL") {
                        $localVar[$op1] = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$staticVarInfo[0]->cvName];
                        $tmp_funcCallState->staticVar[$cvVarName] = [$op1, $staticType];
                        break;
                    }
                    if ($staticType == IS_LOCAL) {
                        $localVar[$op1] = $staticVarInfo[0];
                        $tmp_funcCallState->staticVar[$cvVarName] = [$op1, IS_LOCAL];
                    } else if ($staticType == IS_ARRAY) {
                        $array[$op1] = $staticVarInfo[0];
                        $tmp_funcCallState->staticVar[$cvVarName] = [$op1, IS_ARRAY];
                    }
                }

                break;
            case ZEND_BIND_GLOBAL:
                $superglobals->unbind[$op1] = $op2;
                break;



                
            case ZEND_BOOL:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                $valBool = boolval($tmp_op1->value);
                $localVar[$result] = new ValueStruct($valBool);
                break;

            case ZEND_BOOL_NOT:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                $valBool = boolval(!$tmp_op1->value);
                $localVar[$result] = new ValueStruct($valBool);
                if (!isset($forCount[$thisopId])) {
                    $forCount[$thisopId]['boolnot'] = 1;
                } else {
                    $forCount[$thisopId]['boolnot']++;
                }
                if ($forCount[$thisopId]['boolnot'] > 20) {
                    $localVar[$result] = new ValueStruct(!$valBool);
                    $forCount[$thisopId]['boolnot'] = 0;
                }
                break;

            case ZEND_PRE_INC:
                $perIncVar = &$localVar[$op1];
                if($perIncVar->if_ref == "REF_VAR"){
                    $perIncVar = &$this->refCalc($superglobals,$localVar,$array,$object,$perIncVar->refSource,$perIncVar->refType,$perIncVar->refIndex);
                }
                if(!is_null($perIncVar) and get_class($perIncVar) == 'ValueStruct'){
                    $perIncVar->value++;
                }
                break;
            case ZEND_PRE_DEC:
                $perIncVar = &$localVar[$op1];
                if ($perIncVar->if_ref == "REF_VAR") {
                    $perIncVar = &$this->refCalc($superglobals, $localVar, $array, $object, $perIncVar->refSource, $perIncVar->refType, $perIncVar->refIndex);
                }
                if (!is_null($perIncVar) and get_class($perIncVar) == 'ValueStruct') {
                    $perIncVar->value--;
                }
                break;
            case ZEND_POST_INC:
                break;
            case ZEND_POST_DEC:
                break;



                
            case ZEND_ASSIGN_OP:
                $tmp_local = $this->assign_opDeal($localVar, $array , $op1, $op2, $extended_value, 0);
                if ($op1_type == "IS_CV") {
                    $tmp_local->cvName = $this->getCVName($op1, $cvVarNamelist);
                }
                break;

            case ZEND_ASSIGN_DIM_OP:
                $this->assign_opDeal($localVar, $array, $op1, $op2, $extended_value, 1, $tmp_array);
                $assign_DIM_OP_flag = 1;
                break;

            case ZEND_ROPE_INIT:
            case ZEND_ROPE_ADD:
            case ZEND_ROPE_END:
            case ZEND_CONCAT:
            case ZEND_FAST_CONCAT:
                $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                $op1Var = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $op1VarType, $cvVarNamelist);
                $op2VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op2, 1);
                $op2Var = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op2, $op2VarType, $cvVarNamelist);
                $localVar[$result] = new ValueStruct(urldecode($op1Var->value). urldecode($op2Var->value));
                $taintedInfo = $this->taint_propagate_op1_op2($localVar, $taintedChains, $op1, $op2, $processFlag . (string)$lineNo, $op1Var, $op2Var);
                $localVar[$result]->taintInfoSave($taintedInfo[0], $taintedInfo[1], $taintedInfo[2], $taintedInfo[3]);
                break;

            
            case ZEND_CAST:
                switch ($extended_value) {
                    case 6:
                        $magicCallFlag = 0;
                        if(isset($object[$op1])){
                            foreach ($object[$op1]->funcOpArray as $key => $value) {
                                if ($key == "__tostring") {
                                    $magicCallFlag = 1;
                                }
                            }
                            if ($magicCallFlag) {
                                $tmpid = rand(1000, 10000);
                                $tmpVarid = "$" . (string)$tmpid;
                                $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__tostring", "IS_CONST");
                                $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", 0, "", "IS_UNUSED", "", "IS_UNUSED", $tmpVarid, "IS_VAR");
                                $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                                $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                                $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                                $methodMagicChangeBBCount = count($setOpArray);
                            }
                        }else{
                            $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                            $this->varClone($globalState, $localVar, $array, $object, $op1, $op1VarType, $result);
                        }
                        break;
                    case 7:
                        if(!empty($object[$op1])){
                            $tmp_propertiesArray = $object[$op1]->propertiesArray;
                            $tmp_array = New ArrayStruct();
                            foreach ($tmp_propertiesArray as $key => $value) {
                                if(is_array($value)){
                                    $tmp_value = New ValueStruct($value[1]);
                                    $tmp_array->keyArray[$key] = $tmp_value;
                                }else{
                                    $tmp_array->keyArray[$key] = $value;
                                }
                            }
                            $array[$result] = clone $tmp_array;
                        }
                        break;
                    default:
                        break;
                }
                break;


                
            case ZEND_SWITCH_STRING:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                if($tmp_op1->if_tainted or (isset($tmp_op1->taintedSource[0]) and $tmp_op1->taintedSource[0] = '_COOKIE')){
                    preg_match_all("/\d+/", $op2, $match);
                    $tmpjumplist = $match[0];
                    foreach ($tmpjumplist as $value) {
                        $jumplist[] = (int)$value;
                    }
                    $jumplist[] = $jmp_ext;
                    $jumpState = 1;
                    $jumpStateFuncName=$thisFuncName;
                    $jumpStatefileName=$tmpfilepathinfo['basename'];
                }
                break;
                
            case ZEND_CASE:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                $tmp_op2 = $this->varCalc($localVar, $op2);
                if($tmp_op1->value == $tmp_op2->value){
                    $jumpFlag = 1;
                }
                break;
            case ZEND_FAST_CALL:
                if($tmp_funcCallState->exceptionFlag){
                    $callStateIndex--;
                    $tmp_funcCallState = $programCallState[$callStateIndex];
                    unset($programCallState[$callStateIndex]);
                }
                break;

            case ZEND_JMPZ:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                if (!$tmp_op1->value) {
                    preg_match("/[0-9]+/", $op2, $match);
                    $jmpAddr = $match[0];
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['z_p'] = 1;
                    } else {
                        $forCount[$thisopId]['z_p']++;
                    }
                    if ($forCount[$thisopId]['z_p'] > 5) {
                        $jmpAddr = NULL;
                        $forCount[$thisopId]['z_p'] = 0;
                    }
                }
                break;
            case ZEND_JMPNZ:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                if($tmp_op1->value and isset($localVar[$op1])){
                    preg_match("/[0-9]+/", $op2, $match);
                    $jmpAddr = $match[0];
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['nz_p'] = 1;
                    } else {
                        $forCount[$thisopId]['nz_p']++;
                    }
                    if ($forCount[$thisopId]['nz_p'] > 5) {
                        $jmpAddr = NULL;
                        $forCount[$thisopId]['nz_p'] = 0;
                    }
                }else if($jumpFlag == 1){
                    preg_match("/[0-9]+/", $op2, $match);
                    $jmpAddr = $match[0];
                    $jumpFlag = 0;
                }
                break;
            case ZEND_JMPZ_EX:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                if (!$tmp_op1->value) {
                    preg_match("/[0-9]+/", $op2, $match);
                    $jmpAddr = $match[0];
                    $localVar[$result] = new ValueStruct(0);
                }else{
                    $localVar[$result] = new ValueStruct(1);
                }
                break;
            case ZEND_JMPNZ_EX:
                $tmp_op1 = $this->varCalc($localVar, $op1);
                if ($tmp_op1->value) {
                    preg_match("/[0-9]+/", $op2, $match);
                    $jmpAddr = $match[0];
                }
                break;

            case ZEND_FETCH_DIM_IS:
                if (isset($array[$op1]->keyArray[$op2])) {
                    if ($array[$op1]->keyArray[$op2]->if_ref == "REF_VAR") {
                        $tmp_arrayItem = $array[$op1]->keyArray[$op2];
                        $localVar[$result] = $this->refCalc($superglobals, $localVar, $array, $object, $tmp_arrayItem->refSource, $tmp_arrayItem->refType, $tmp_arrayItem->refIndex);
                    } else {
                        $localVar[$result] = $array[$op1]->keyArray[$op2];
                    }
                }else{
                    $localVar[$result] = new ValueStruct(false);
                }
                break;
            case ZEND_COALESCE:
                if($localVar[$op1]->value == false){
                    break;
                }else{
                    $localVar[$result] = $localVar[$op1];
                    preg_match("/[0-9]+/", $op2, $match);
                    $jmpAddr = $match[0];
                }
                break;

            case ZEND_FE_RESET_R:
            case ZEND_FE_RESET_RW:
                $array[$result] = $array[$op1];
                reset($array[$result]->keyArray);
                $array[$result]->foreachIndexCount = 0;
                break;


                
            case ZEND_FE_FETCH_R:
                if(!is_null($result)){
                    $foreachKeyflag = 1;
                }else{
                    $foreachKeyflag = 0;
                }
                $foreachExitFlag = 0;
                $foreachCount = count($array[$op1]->keyArray);
                $array[$op1]->foreachIndexCount++;
                if ($foreachCount == null or $foreachCount == 0 and $array[$op1]->if_tainted and $array[$op1]->taintedSource == "TAINTSOURCE"){
                    $array[$op1]->keyArray[0] = new ValueStruct();
                    $array[$op1]->keyArray[0]->taintInfoSave($array[$op1]->if_tainted, $array[$op1]->taintedSource, $array[$op1]->taintedSourceLine, $array[$op1]->taintedLine);
                    $foreachExitFlag = 1;
                    if($foreachKeyflag){
                        $localVar[$result] = new ValueStruct();
                        $localVar[$result]->taintInfoSave($array[$op1]->if_tainted, $array[$op1]->taintedSource, $array[$op1]->taintedSourceLine, $array[$op1]->taintedLine);
                        $foreachKeyflag = 0;
                    }
                }
                else if ($foreachCount < $array[$op1]->foreachIndexCount) {
                    preg_match("/[0-9]+/", $jmp_ext, $match);
                    $jmpAddr = $match[0];
                    unset($array[$op1]->foreachIndexCount);
                    break;
                }else{
                    if (!isset($forCount[$thisopId])) {
                        $forCount[$thisopId]['fetchr_p'] = 1;
                    } else {
                        $forCount[$thisopId]['fetchr_p']++;
                    }
                    if ($forCount[$thisopId]['fetchr_p'] > 5) {
                        preg_match("/[0-9]+/", $jmp_ext, $match);
                        $jmpAddr = $match[0];
                        unset($array[$op1]->foreachIndexCount);
                        $forCount[$thisopId]['fetchr_p'] = 0;
                    }
                }
                $currentItem = current($array[$op1]->keyArray);
                if($currentItem){
                    if($foreachKeyflag){
                        $tmpkeyvalue = key($array[$op1]->keyArray);
                        $localVar[$result] = new ValueStruct($tmpkeyvalue);
                        if(strstr($tmpkeyvalue, 'TAINTED')){
                            $localVar[$result] = clone $array[$op1]->keyVarList[$tmpkeyvalue];
                        }
                        
                    }
                    $arrayItemType = get_class($currentItem);
                    next($array[$op1]->keyArray);
                    switch ($arrayItemType) {
                        case 'ValueStruct':
                            $localVar[$op2] = clone $currentItem;
                            break;
                        case 'ArrayStruct':
                            $array[$op2] = clone $currentItem;
                            break;
                        default:
                            break;
                    }

                }else{
                    $tmpIndex = 0;
                    foreach ($array[$op1]->keyArray as $arrayItem) {
                        $tmpIndex++;
                        if($array[$op1]->foreachIndexCount == $tmpIndex){
                            $localVar[$op2] = clone $arrayItem;
                            break;
                        }
                    }
                }
                if($foreachExitFlag){
                    $array[$op1]->foreachIndexCount = 999;
                }
                break;
            case ZEND_FE_FETCH_RW:
                $foreachCount = count($array[$op1]->keyArray);
                $localVar[$op2]->refSave("REF_VAR",$op1, "ARRAYITEM",1,$array[$op1]->foreachIndexCount);
                $array[$op1]->foreachIndexCount++;
                if ($foreachCount == $array[$op1]->foreachIndexCount) {
                    preg_match("/[0-9]+/", $jmp_ext, $match);
                    $jmpAddr = $match[0];
                    unset($array[$op1]->foreachIndexCount);
                }
                break;


            case ZEND_DECLARE_CONST:
                $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$op1] = new ValueStruct($op2);
                break;
            case ZEND_FETCH_CONSTANT:
                if(isset($superglobals->superGlobalArrays["GLOBALS"]->keyArray[$op2])){
                    $localVar[$result] = clone $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$op2];
                }

                break;


                
            case ZEND_EXIT:
            case ZEND_ECHO:
                if($obState->obCallableFlag){
                    $tmpVarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                    $tmpVar = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $tmpVarType, $cvVarNamelist);
                    if($tmpVar->if_tainted){
                        $tmpOpLine = clone $opArray[$thisopId];
                        $tmpid = rand(1000, 10000);
                        $tmpVarid = "$" . (string)$tmpid;
                        $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $tmpOpLine->op1);
                        $this->varClone($globalState, $localVar, $array, $object, $tmpOpLine->op1, $op1VarType, $tmpVarid);
                        $obState->obOpArray[] = $this->magicMethodOpArrayAdd(61, "INIT_FCALL", $lineNo, "", "IS_UNUSED", $obState->obCallableFuncName, "IS_CONST");
                        $obState->obOpArray[] = $this->magicMethodOpArrayAdd(117, "SEND_VAR", $lineNo, $tmpVarid, "IS_CV");
                        $obState->obOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", $lineNo, "", "IS_UNUSED");
                        break;
                    }
                }
                else if($obState->obFlag){
                    $tmpOpLine = clone $opArray[$thisopId];
                    $tmpid = rand(1000, 10000);
                    $tmpVarid = "$" . (string)$tmpid;
                    $op1VarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $tmpOpLine->op1);
                    $this->varClone($globalState, $localVar, $array, $object, $tmpOpLine->op1, $op1VarType, $tmpVarid);
                    $tmpOpLine->op1 = $tmpVarid;
                    $obState->obOpArray[] = $tmpOpLine;
                    $taintedVarType = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1, 1);
                    $taintedVarValue = $this->valueGet($CONSTARRAYINFO, $superglobals, $localVar, $array, $object, $op1, $taintedVarType, $cvVarNamelist);
                    if($taintedVarValue->if_tainted){
                        $obState->obTaintedFlag = 1;
                    }
                    break;
                }
                $type = $this->varClassify($cvVarNamelist, $superglobals, $localVar, $array, $object, $op1);
                switch ($type) {
                    case IS_INIT:
                        break;
                    case IS_LOCAL:
                        $tmp_op1 = $localVar[$op1];
                        if ($tmp_op1->if_ref == "REF_VAR"){
                            $tmp_op1 = $this->refCalc($superglobals,$localVar,$array,$object,$tmp_op1->refSource,$tmp_op1->refType,$tmp_op1->refIndex);
                        }
                        if ($tmp_op1->if_tainted == 1) {
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [1, "XSS"];
                            } else {
                                debugEcho("[DEBUG] XSS FOUND!\n", $DebugFlag, 'red');
                            }
                            $taint_source_Line = $tmp_op1->taintedSourceLine;
                            $taintedLine = $tmp_op1->taintedLine;
                            $thisLineNo = $processFlag. (string)$lineNo;
                            $this->taintedChainSearch($vulChains, "XSS", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                            if($funcCheckFlag){
                                $funcTypeForCheck = [1, "XSS"];
                            }
                        }
                        break;
                    case IS_CONST_ARRAY:
                    case IS_ARRAY:
                        $tmp_op1 = $array[$op1];
                        if ($tmp_op1->if_tainted == 1) {
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [1, "XSS"];
                            } else {
                                debugEcho("[DEBUG] XSS FOUND!\n", $DebugFlag, 'red');
                            }
                            $taint_source_Line = $tmp_op1->taintedSourceLine;
                            $taintedLine = $tmp_op1->taintedLine;
                            $thisLineNo = $processFlag . (string)$lineNo;
                            $this->taintedChainSearch($vulChains, "XSS", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo, $DebugFlag);
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [1, "XSS"];
                            }
                        }
                        break;
                    case IS_OBJECT:
                        $magicCallFlag = 0;
                        foreach ($object[$op1]->funcOpArray as $key => $value) {
                            if ($key == "__tostring") {
                                $magicCallFlag = 1;
                            }
                        }
                        if ($magicCallFlag) {
                            $tmpid = rand(1000,10000);
                            $tmpVarid = "$".(string)$tmpid;
                            $setOpArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", $lineNo, $op1, "IS_CV", "__tostring", "IS_CONST");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", 0 , "","IS_UNUSED", "", "IS_UNUSED", $tmpVarid, "IS_VAR");
                            $setOpArray[] = $this->magicMethodOpArrayAdd(136, "ECHO", 999 , $tmpVarid ,"IS_VAR");
                            $opPreArray = array_slice($opArray, 0, $thisopId + 1);
                            $opSufArray = array_slice($opArray, $thisopId + 1, $opcodeCount);
                            $opArray = array_merge($opPreArray, $setOpArray, $opSufArray);
                            $methodMagicChangeBBCount = count($setOpArray);
                        }
                        break;
                    case IS_GLOBAL:
                    case IS_GLOBAL_UNBIND:
                        $cvVarName = $this->getCVName($op1, $cvVarNamelist); 
                        $tmp_op1 = $superglobals->superGlobalArrays["GLOBALS"]->keyArray[$cvVarName];
                        if ($tmp_op1->if_ref == "REF_VAR") {
                            $tmp_op1 = $this->refCalc($superglobals, $localVar, $array, $object, $tmp_op1->refSource, $tmp_op1->refType, $tmp_op1->refIndex);
                        }
                        if ($tmp_op1->if_tainted == 1) {
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [1, "XSS"];
                            } else {
                                debugEcho("[DEBUG] XSS FOUND!\n", $DebugFlag, 'red');
                            }
                            $taint_source_Line = $tmp_op1->taintedSourceLine;
                            $taintedLine = $tmp_op1->taintedLine;
                            $thisLineNo = $processFlag . (string)$lineNo;
                            $this->taintedChainSearch($vulChains, "XSS", $taintedChains, $taint_source_Line, $taintedLine, $thisLineNo,$DebugFlag);
                            if ($funcCheckFlag) {
                                $funcTypeForCheck = [1, "XSS"];
                            }
                        }
                        break;

                    default:
                        break;
                }
                break;

            default:
                break;
        }
        if($thisopId == (count($opArray)-1) and $obState->obFlag){
            $this->obFuncEndDefault($obState, $opArray, $thisopId, $opcodeCount, $methodMagicChangeBBCount);
        }

        return $state;
    }

    public function unionPre($bbOUT)
    {
        $bbIN = NULL;
        foreach ($bbOUT as $state) {
            $bbIN = $state;
        }
        return $bbIN;
    }


    public function dataFlowAnalysis($bbINState, &$funcOpArray, &$fileObject, &$includedFileObject, &$includeFileChain, $DebugFlag = False, $funcCheckFlag = False)
    {
        $opArray = &$funcOpArray->opArray;
        $cfgbbArray = $funcOpArray->cfgInfo;
        $phpFilePath = $funcOpArray->phpFilePath;
        $classOpArray = $fileObject->classOpArray;
        $thisFileFuncOpArray = &$fileObject->funcOpArray;
        $thisFuncName = $funcOpArray->funcName;
        $funcCallState = $funcOpArray->funcCallState;


        $cfgbbList = [];
        foreach ($cfgbbArray as $key => $basicblock) {
            $cfgbbList[] = $key;
        }

        $BBname = $cfgbbList[0];
        $exitFlag = 0;
        do{
            $exceptionFlag = 0;
            $basicblock = $cfgbbArray[$BBname];
            $entry = $basicblock->entry;
            $exit = $basicblock->exit;
            $bbconnect = $basicblock->bbconnect;
            $bbIN = $basicblock->bbIN;
            if(empty($bbIN) and !empty($bbINState)){
                $bbINState->currentFilePath = $phpFilePath;
                $bbIN[] = $bbINState;
                unset($bbINState);
            }
            $state = $this->unionPre($bbIN);
            if(is_null($state))
            {
                $state = new ProgramState();
                $state->tmp_funcCallState = &$funcCallState;
            }

            $itemOpArray = $opArray;            
            for($i = $entry;$i <= $exit;$i++)
            {
                if($i+1 <= $exit){
                    try{
                        $state = $this->opcodeAnalysis($itemOpArray, $i, $itemOpArray[$i], $itemOpArray[$i + 1]->lineNo, $phpFilePath, $state, $classOpArray, $thisFileFuncOpArray, $thisFuncName,$includedFileObject, $includeFileChain,$DebugFlag, $funcCheckFlag);
                    } catch (Throwable  $e) {
                        return $state;
                    }
                    $vulInfo = [$phpFilePath, $state->vulChains];

                    if (!empty($state->jumplist) and $state->jumpState == 1) {
                        break;
                    }
                    
                    if($state->exitFlag == 1){
                        $state->exitFlag = 2;
                        $exitFlag = 1;
                        break;
                    }else if($state->exitFlag == 2){
                        $count = 0;
                        foreach ($itemOpArray as $opcodeitem) {
                            if($opcodeitem->opcodeName == "CATCH"){
                                $BBname = $this->searchBB($count, $cfgbbArray);
                                $exceptionFlag = 1;
                                break;
                            }
                            $count++;
                        }
                        break;
                    }
                    if(!empty($state->methodMagicChangeBBCount)){
                        $exit = $exit + $state->methodMagicChangeBBCount;
                        unset($state->methodMagicChangeBBCount);
                    }
                }else{
                    try{
                        $state = $this->opcodeAnalysis($itemOpArray, $i, $itemOpArray[$i], $itemOpArray[$i]->lineNo, $phpFilePath, $state, $classOpArray, $thisFileFuncOpArray, $thisFuncName, $includedFileObject, $includeFileChain, $DebugFlag, $funcCheckFlag);
                    } catch (Throwable  $e) {
                        return $state;
                    }
                    $vulInfo = [$phpFilePath, $state->vulChains];

                    if (!empty($state->jumplist) and $state->jumpState == 1) {
                        break;
                    }

                    if($state->exitFlag == 1){
                        $state->exitFlag = 2;
                        $exitFlag = 1;
                        break;
                    }else if($state->exitFlag == 2){
                        $count = 0;
                        foreach ($itemOpArray as $opcodeitem) {
                            if($opcodeitem->opcodeName == "CATCH"){
                                $BBname = $this->searchBB($count, $cfgbbArray);
                                $exceptionFlag = 1;
                                break;
                            }
                            $count++;
                        }
                        break;
                    }
                    if (!empty($state->methodMagicChangeBBCount)) {
                        $exit = $exit + $state->methodMagicChangeBBCount;
                        unset($state->methodMagicChangeBBCount);
                    }
                }
                
            }
            $basicblock->bbOUT = $state;

            foreach ($basicblock->jumpInfo as $item) {
                if($item == "EXIT"){
                    if(!empty($state->jumplist) and $state->jumpState == 0 and $state->jumpStateFuncName==$thisFuncName and $state->jumpStatefileName == pathinfo($phpFilePath)['basename']){
                        $state->jumpState = 1;
                    }else{
                        $exitFlag = 1;
                    }
                    break;
                }
            }
            if(!$exitFlag and !$exceptionFlag){
                if (isset($state->jmpAddr)) {
                    $BBname = $this->searchBB($state->jmpAddr, $cfgbbArray);
                    if(!isset($BBname)){
                        $exitFlag = 1;
                    }else{
                        $cfgbbArray[$BBname]->bbIN[] = $basicblock->bbOUT;
                        $state->jmpAddr = NULL;
                    }

                } else if(!empty($state->jumplist) and $state->jumpState == 1){
                    $state->jumpState = 0;
                    $tmpjmpAddr = array_shift($state->jumplist);
                    $BBname = $this->searchBB($tmpjmpAddr, $cfgbbArray);
                    if (!isset($BBname)) {
                        $exitFlag = 1;
                    } else {
                        $cfgbbArray[$BBname]->bbIN[] = $basicblock->bbOUT;
                    }
                } else {
                    $BBname = $bbconnect[0];
                    foreach ($bbconnect as $preBBname) {
                        if (isset($cfgbbArray[$preBBname])) {
                            $cfgbbArray[$preBBname]->bbIN[] = $basicblock->bbOUT;
                        }
                    }
                }
            }else if($exceptionFlag){
                $cfgbbArray[$BBname]->bbIN[] = $basicblock->bbOUT;
                $state->exitFlag = 0;
            }
        }while(!$exitFlag);

        if(!empty($state->object)){
            foreach ($state->object as $key => $value) {
                if($key == $value->refSource or $value->if_objectCV == 1){
                    $tmpClassName = $value->className;
                    if(!empty($classOpArray[$tmpClassName]) and !empty($classOpArray[$tmpClassName]->funcOpArray['__destruct'])){
                        $tmp_opArray[] = $this->magicMethodOpArrayAdd(112, "INIT_METHOD_CALL", 0, $key, "IS_CV", "__destruct", "IS_CONST");
                        $tmp_opArray[] = $this->magicMethodOpArrayAdd(60, "DO_FCALL", 0, "", "IS_UNUSED");
                        $state = $this->opcodeAnalysis($tmp_opArray, 0, $tmp_opArray[0], 0, $phpFilePath, $state, $classOpArray, $thisFileFuncOpArray, $thisFuncName,$includedFileObject, $includeFileChain,$DebugFlag, $funcCheckFlag);
                        $state = $this->opcodeAnalysis($tmp_opArray, 0, $tmp_opArray[1], 0, $phpFilePath, $state, $classOpArray, $thisFileFuncOpArray, $thisFuncName, $includedFileObject, $includeFileChain,$DebugFlag, $funcCheckFlag);
                        unset($tmp_opArray);
                    }
                }
            }
        }
        return $state;

    }



}