<?php

class FileInfo
{
    public $id;
    public $filePath;
    public $phpFilePath;
    public $classOpArray;
    public $funcOpArray;
    public $callGraphInfo;
    public $includedFileObject;

    function __construct($filePath, $phpFilePath = NULL, $classOpArray = array(), $funcOpArray = array(), $callGraphInfo = array(), $includedFileObject = NULL)
    {
        $this->filePath = $filePath;
        $this->phpFilePath = $phpFilePath;
        $this->id = rand();
        $this->classOpArray = $classOpArray;
        $this->funcOpArray = $funcOpArray;
        $this->callGraphInfo = $callGraphInfo;

    }
}

class ClassInfo
{
    public $className;
    public $parentClassName;            
    public $parentClassTraitName;       
    public $properties_info;
    public $funcOpArray;

    function __construct($className, $properties_info = array(), $funcOpArray = array())
    {
        $this->className = $className;
        $this->properties_info = $properties_info;
        $this->funcOpArray = $funcOpArray;
    }
}

class FuncInfo
{
    public $phpFilePath;
    public $funcName;
    public $shortfuncName;
    public $scopeClassName;        
    public $funcType;              
    public $funcArgs;              
    public $funcRetType;           
    public $compiledVars;          
    public $opArray;
    public $opcodeCount;
    public $includeInfo;
    public $jumpInfo;              
    public $jmpNode;               
    public $entryNode;             
    public $bbArray;               
    public $cgInfo;                
    public $cfgInfo;               
    public $funcCallState;         
    public $staticVar;             
    public $anonymousClassIndex;   

    

    function __construct($funcName, $phpFilePath="",  $opcodeCount =0, $opArray = array(), $includeInfo = array(), $jumpInfo = array(), $entryNode = array(0), $jmpNode = array(), $bbArray = array(), $cfgInfo = array(), $cgInfo = array())
    {
        $this->phpFilePath = $phpFilePath;
        $this->funcName = $funcName;
        $this->opcodeCount = $opcodeCount;
        $this->opArray = $opArray;
        $this->includeInfo = $includeInfo;
        $this->jumpInfo = $jumpInfo;
        $this->entryNode = $entryNode;
        $this->jmpNode = $jmpNode;
        $this->bbArray = $bbArray;
        $this->cfgInfo = $cfgInfo;
        $this->cgInfo = $cgInfo;
        
    }

    function argsInfo($arginfoNo, $argname, $argtype, $argis_ref, $argis_variadic){
        $this->funcArgs[$arginfoNo] = new FuncArgs($argname, $argtype, $argis_ref, $argis_variadic);
    }
}



class FuncArgs
{
    public $name;
    public $type;
    public $is_ref;
    public $is_variadic;
    function __construct($name, $type, $is_ref, $is_variadic)
    {
        $this->name = $name;
        $this->type = $type;
        $this->is_ref = $is_ref;
        $this->is_variadic = $is_variadic;
    }
}

class CallNode
{
    public $caller;
    public $callee;
    public $callsiteAddr;
    public $parameters;

    function __construct($caller, $callee, $callsiteAddr, $parameters = array())
    {
        $this->caller = $caller;
        $this->callee = $callee;
        $this->callsiteAddr = $callsiteAddr;
        $this->parameters = $parameters;
    }
}


class BasicBlock
{
    public $entry;
    public $exit;
    public $bbIN;
    public $bbOUT;
    public $jumpInfo;
    public $bbconnect;

    function __construct($entry, $bbIN = array(), $bbOUT = NULL, $jumpInfo = array(),  $bbconnect = array())
    {
        $this->entry = $entry;
        $this->bbIN = $bbIN;
        $this->bbOUT = $bbOUT;
        $this->jumpInfo = $jumpInfo;
        $this->bbconnect = $bbconnect;
    }
}


class Opcode
{
    public $opcodeNo;
    public $opcodeName;
    public $lineNo;
    public $op1;
    public $op1_type;
    public $op2;
    public $op2_type;
    public $result;
    public $result_type;
    public $extended_value;
    public $fetch_flag;
    public $jmp_ext;

    function __construct($opcodeNo, $opcodeName, $lineNo, $op1, $op1_type, $op2 = NULL, $op2_type = NULL, $result = NULL, $result_type = NULL,$extended_value = NULL, $fetch_flag = NULL, $jmp_ext = NULL)
    {
        $this->opcodeNo = $opcodeNo;
        $this->opcodeName = $opcodeName;
        $this->lineNo = $lineNo;
        $this->op1 = $op1;
        $this->op1_type = $op1_type;
        $this->op2 = $op2;
        $this->op2_type = $op2_type;
        $this->result = $result;
        $this->result_type = $result_type;
        $this->extended_value = $extended_value;
        $this->fetch_flag = $fetch_flag;
        $this->jmp_ext = $jmp_ext;
    }
}


class ProgramState
{
    public $currentFilePath;                
    public $currentWorkPath;                
    public $superglobals;
    public $globalState;
    public $localVar;
    
    public $array;              
    public $tmp_array;          
    public $tmp_mulit_array;    
    public $tmp_object;         
    public $tmp_class_prop;     
    public $tmp_ref_object;     
    public $tmp_funcCallState;  
    public $programCallState;   
    public $callStateIndex;     
    public $exceptionState;     
    public $exitFlag;           
    public $jumpFlag;           
    public $jumplist;           
    public $jumpState;          
    public $jumpStateFuncName;  
    public $jumpStatefileName;  

    public $errorState;         
    public $yieldState;         
    public $funcTypeForCheck;   

    public $obState;            
    public $throwCount;         
    public $forCount;           
    
    public $object;
    public $taintedChains;
    public $vulChains;
    public $includePaths;              
    public $jmpAddr;                   
    public $foreachCount;              
    public $foreachIndex;              
    public $assign_DIM_OP_flag;        
    public $methodMagicFlag;           
    public $methodMagicChangeBBCount;  


    function __construct($localVar = array(), $array = array(),  $object = array(),  $taintedChains = array(),  $vulChains = array(), $tmp_array =NULL, $tmp_object =NULL, $tmp_class_prop =NULL, $tmp_ref_object =NULL,$tmp_funcCallState = NULL, $tmp_mulit_array =NULL, $foreachIndex = 0, $assign_DIM_OP_flag =0, $programCallState =NULL, $callStateIndex = 0, $exitFlag = 0, $funcTypeForCheck = NULL)
    {
        $this->localVar = $localVar;
        $this->array = $array;
        $this->taintedChains = $taintedChains;
        $this->vulChains = $vulChains;
        $this->tmp_array = $tmp_array;
        $this->tmp_object = $tmp_object;
        $this->tmp_class_prop = $tmp_class_prop;
        $this->tmp_ref_object = $tmp_ref_object;
        $this->tmp_funcCallState = $tmp_funcCallState;
        $this->tmp_mulit_array = $tmp_mulit_array;
        $this->object = $object;
        $this->foreachIndex = $foreachIndex;
        $this->assign_DIM_OP_flag = $assign_DIM_OP_flag;
        $this->programCallState = $programCallState;
        $this->callStateIndex = $callStateIndex;
        $this->exceptionState = new ExceptionState();
        $this->errorState = new ErrorState();
        $this->yieldState = new YieldState();
        $this->obState = new obState();
        $this->throwCount = 0;
        $this->forCount = array();
        $this->exitFlag = $exitFlag;
        $this->jumpFlag = 0;
        $this->jumplist = array();
        $this->jumpState = 0;
        $this->jumpStateFuncName = 0;
        $this->jumpStatefileName = 0;
        $this->superglobals = new Superglobals();
    }
}


class Superglobals
{
    public $superGlobalArrays;
    public $unbind;
    function __construct()
    {
        $this->superGlobalArrays['GLOBALS'] = new ArrayStruct();
        $this->superGlobalArrays['_SERVER'] = new ArrayStruct();
        $this->superGlobalArrays['_GET'] = new ArrayStruct();
        $this->superGlobalArrays['_POST'] = new ArrayStruct();
        $this->superGlobalArrays['_REQUEST'] = new ArrayStruct();
        $this->superGlobalArrays['_FILES'] = new ArrayStruct();
        $this->superGlobalArrays['_COOKIE'] = new ArrayStruct();
        $this->superGlobalArrays['_SESSION'] = new ArrayStruct();
        $this->superGlobalArrays['_ENV'] = new ArrayStruct();
    }
}


class GlobalState
{
    public $localVar;
    public $array;
    public $object;
    public $uninit;
    function __construct(&$localVar, &$array, &$object)
    {
        $this->localVar = $localVar;
        $this->array = $array;
        $this->object = $object;
    }
}


class ArrayStruct
{
    public $if_ref;                 
    public $refSource;              
    public $refType;                
    public $refIndex;               
    public $keyArray;               
    public $keyVarList;             
    public $indexCount;             
    public $foreachIndexCount;      
    public $if_tainted;             
    public $taintedSource;          
    public $taintedSourceLine;      
    public $taintedLine;            
    public $cvName;                 
    function __construct($if_ref = 0, $refSource = NULL, $refType = NULL, $refIndex = NULL, $keyArray = array(), $indexCount = NULL)
    {
        $this->if_ref = $if_ref;
        $this->refSource = $refSource;
        $this->refType = $refType;
        $this->refIndex = $refIndex;
        $this->keyArray = $keyArray;
        $this->indexCount = $indexCount;
        $this->keyVarList = array();
    }
    function refSave($if_ref = 0, $refSource = NULL, $refType = NULL, $refIndex = NULL)
    {
        $this->if_ref = $if_ref;
        $this->refSource = $refSource;
        $this->refType = $refType;
        $this->refIndex = $refIndex;
    }
    function taintInfoSave($if_tainted, $taintedSource, $taintedSourceLine, $taintedLine)
    {
        $this->if_tainted = $if_tainted;
        $this->taintedSource = $taintedSource;
        $this->taintedSourceLine = $taintedSourceLine;
        $this->taintedLine = $taintedLine;
    }
}



class InternalFuncCallChains{
    public $callChains;
    public $reverseChains;
    public $sourceChains;
    function __construct($callChains = array(), $reverseChains = array(), $sourceChains = array())
    {
        $this->callChains = $callChains;
        $this->reverseChains = $reverseChains;
        $this->sourceChains = $sourceChains;
    }
}

class ValueStruct{
    public $if_ref;                 
    public $refSource;              
    public $refType;                
    public $refIndex;               
    public $if_array;               
    public $value;                  
    public $if_tainted;             
    public $taintedSource;
    public $taintedSourceLine;
    public $taintedLine;
    public $internalFuncCallChain;  
    public $cvName;                 
    function __construct($value = NULL, $if_ref = 0, $refSource = NULL, $refType = NULL, $refIndex = NULL, $if_array = 0,  $if_tainted = 0, $taintedSource = NULL, $taintedSourceLine = NULL, $taintedLine = NULL, $internalFuncCallChain = NULL)
    {
        $this->if_ref = $if_ref;
        $this->refSource = $refSource;
        $this->refType = $refType;
        $this->refIndex = $refIndex;
        $this->if_array = $if_array;
        $this->value = $value;
        $this->if_tainted = $if_tainted;
        $this->taintedSource = $taintedSource;
        $this->taintedSourceLine = $taintedSourceLine;
        $this->taintedLine = $taintedLine;
        $this->internalFuncCallChain = new InternalFuncCallChains();
    }


    function localSaveArrayItem($ValueSturctItem)
    {
        $this->if_ref = $ValueSturctItem->if_ref;
        $this->refSource = $ValueSturctItem->refSource;
        $this->refType = $ValueSturctItem->refType;
        $this->refIndex = $ValueSturctItem->refIndex;
        $this->value = $ValueSturctItem->value;
        $this->if_tainted = $ValueSturctItem->if_tainted;
        $this->taintedSource = $ValueSturctItem->taintedSource;
        $this->taintedSourceLine = $ValueSturctItem->taintedSourceLine;
        $this->taintedLine = $ValueSturctItem->taintedLine;
    }

    function refSave($if_ref = 0, $refSource = NULL, $refType = NULL, $if_array =0, $refIndex = NULL)
    {
        $this->if_ref = $if_ref;
        $this->refSource = $refSource;
        $this->refType = $refType;
        $this->if_array = $if_array;
        $this->refIndex = $refIndex;
    }

    function valuePassFromLocal($LocalSturct)
    {
        $this->if_ref = $LocalSturct->if_ref;
        $this->refSource = $LocalSturct->refSource;
        $this->refType = $LocalSturct->refType;
        $this->refIndex = $LocalSturct->refIndex;
        $this->value = $LocalSturct->value;
        $this->if_tainted = $LocalSturct->if_tainted;
        $this->taintedSource = $LocalSturct->taintedSource;
        $this->taintedSourceLine = $LocalSturct->taintedSourceLine;
        $this->taintedLine = $LocalSturct->taintedLine;
        $this->internalFuncCallChain = $LocalSturct->internalFuncCallChain;
    }

    function taintInfoSave($if_tainted, $taintedSource, $taintedSourceLine,$taintedLine)
    {
        $this->if_tainted = $if_tainted;
        $this->taintedSource[] = $taintedSource;
        $this->taintedSourceLine = $taintedSourceLine;
        $this->taintedLine = $taintedLine;
    }

    function internalFuncCallCheck()
    {
        foreach ($this->internalFuncCallChain as $value) {
            if(!empty($value)){
                return true;
            }
        }
        return false;
    }
}


class ObjectStruct{
    public $className;              
    public $propertiesArray;        
    public $staticPropertyArray;    
    public $funcOpArray;            
    public $if_ref;                 
    public $if_objectCV;            
    public $refSource;              
    public $refType;                
    public $refIndex;               
    public $anonymousClassInfo;     
    public $anonymousClassIndex;    

    public $dymFuncCallFlag;        
    public $dymFuncParameters;      

    public $cvName;                 

    function __construct($className = NULL, $propertiesArray = NULL, $staticPropertyArray = NULL, $if_ref = 0, $refSource = NULL, $refType = NULL, $refIndex = NULL, $dymFuncCallFlag = 0, $dymFuncParameters = NULL)
    {
        $this->className = $className;
        $this->propertiesArray = $propertiesArray;
        $this->staticPropertyArray = $staticPropertyArray;
        $this->if_ref = $if_ref;
        $this->if_objectCV = 0;
        $this->refSource = $refSource;
        $this->refType = $refType;
        $this->refIndex = $refIndex;
        $this->dymFuncCallFlag = $dymFuncCallFlag;
        $this->dymFuncParameters = $dymFuncParameters;
    }

    function propertyAssign($propertiesArray)
    {
        $this->propertiesArray = $propertiesArray;
    }

    function internalMethodInit()
    {
        $internalClassMethods = get_class_methods($this->className);
        foreach ($internalClassMethods as $method) {
            $method = strtolower($method);  
            $this->funcOpArray[$method] = new FuncInfo($method);
            $this->funcOpArray[$method]->funcType = 'INTERNAL';
        }
    }
}

class FuncCallState
{
    public $superglobals;           
    public $localSave;              
    public $globalState;            
    public $funcInfo;               
    public $funcparameters;         
    public $parametersCount;        
    public $returnVar;              
    public $returnCount;            
    public $ifClassMethod;          
    public $className;              
    public $methodName;             
    public $staticVar;              
    public $taintedChains;          
    public $callerName;             
    public $globalBind;             
    public $thisObjectVarId;        
    public $thisObjectVarcvName;    
    public $exceptionFlag;          

    function __construct($funcInfo = NULL, $funcparameters = NULL, $parametersCount = 0, $returnVar = NULL, $returnCount = 0)
    {
        $this->funcInfo = $funcInfo;
        $this->funcparameters = $funcparameters;
        $this->parametersCount = $parametersCount;
        $this->returnVar = $returnVar;
        $this->returnCount = $returnCount;
        $this->exceptionFlag = 0;
    }

    function classMethodInit($ifClassMethod, $className, $methodName)
    {
        $this->ifClassMethod = $ifClassMethod;
        $this->className = $className;
        $this->methodName = $methodName;
    }
}


class ErrorState{
    public $errorFlag;
    public $errorMessageVar;
    function __construct($errorFlag = 0, $errorMessageVar = NULL)
    {
        $this->errorFlag = $errorFlag;
        $this->errorMessageVar = $errorMessageVar;
    }
}

class ExceptionState
{
    public $exceptionFlag;
    public $exceptionMessageVar;
    function __construct($exceptionFlag = 0, $exceptionMessageVar = NULL)
    {
        $this->exceptionFlag = $exceptionFlag;
        $this->exceptionMessageVar = $exceptionMessageVar;
    }
}

class YieldState
{
    public $yieldFlag;
    public $yieldArrayVar;
    function __construct($yieldFlag = 0, $yieldArrayVar = NULL)
    {
        $this->yieldFlag = $yieldFlag;
        $this->yieldArrayVar = $yieldArrayVar;
    }    
}

class obState
{
    public $obFlag;                 
    public $obTaintedFlag;          
    public $obOpArray;              
    public $obFuncCallState;        
    public $obCallableFlag;         
    public $obCallableFuncName;     

    function __construct($obFlag = 0, $obTaintedFlag = 0, $obOpArray = array(), $obFuncCallState = array(), $obCallableFlag = 0, $obCallableFuncName = NULL)
    {
        $this->obFlag = $obFlag;
        $this->obTaintedFlag = $obTaintedFlag;
        $this->obOpArray = $obOpArray;
        $this->obFuncCallState = $obFuncCallState;
        $this->obCallableFlag = $obCallableFlag;
        $this->obCallableFuncName = $obCallableFuncName;
    }    
}

