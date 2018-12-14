<?php
/**
* This class handles and logs the error that occurs in the project
*
* @author Nitesh Apte
* @copyright 2010
* @version 1.0
* @access private
* @License GPL
*/
class ErrorLogging {
    /**
     * @var $_backTrace Backtrace message in _customError() method
     * @see _customError
     */
    private $_backTrace;

    /**
     * @var $_errorMessage Error message
     * @see _customError
     */
    private $_errorMessage;

    /**
     * @var $_traceMessage Contains the backtrace message from _debugBacktrace() method
     * @see _debugBacktrace()
     */
    private $_traceMessage = '';

    /**
     * @var $MAXLENGTH Maximum length for backtrace message
     * @see _debugBacktrace()
     */
    private $_MAXLENGTH = 6400;

    /**
     * @var $_traceArray Contains from debug_backtrace()
     * @see _debugBacktrace()
     */
    private $_traceArray;

    /**
     * @var $_defineTabs
     */
    private $_defineTabs;

    /**
     * @var $_argsDefine
     */
    private $_argsDefine = array();

    /**
     * @var $_newArray
     */
    private $_newArray;

    /**
     * @var $_newValue
     */
    private $_newValue;

    /**
     * @var $_stringValue
     */
    private $_stringValue;

    /**
     * @var $_lineNumber
     */
    private $_lineNumber;

    /**
     * @var $_fileName
     */
    private $_fileName;

    /**
     * @var $_lastError
     */
    private $_lastError;


    /**
     * Set custom error handler
     *
     * @param none
     * @return none
     */
    public function __construct()
    {
        ini_set('html_errors', 0);
        set_error_handler(array($this,'_customError'));
        set_exception_handler(array($this,'_exceptionError'));
        register_shutdown_function(array($this, '_fatalError'));
    }
    /**
     * Custom error logging in custom format
     *
     * @param Int $errNo Error number
     * @param String $errStr Error string
     * @param String $errFile Error file
     * @param Int $errLine Error line
     * @return none
     */
    public function _exceptionError($exception) {
			// these are our templates
		$traceline = "#%s %s(%s): %s(%s)";
		$msg = "PHP Fatal error:  Uncaught exception '%s' with message '%s' in %s:%s\nStack trace:\n%s\n  thrown in %s on line %s";

		// alter your trace as you please, here
		$trace = $exception->getTrace();
		foreach ($trace as $key => $stackPoint) {
			// I'm converting arguments to their type
			// (prevents passwords from ever getting logged as anything other than 'string')
			$trace[$key]['args'] = array_map('gettype', $trace[$key]['args']);
		}

		// build your tracelines
		$result = array();
		foreach ($trace as $key => $stackPoint) {
			$result[] = sprintf(
				$traceline,
				$key,
				$stackPoint['file'],
				$stackPoint['line'],
				$stackPoint['function'],
				implode(', ', $stackPoint['args'])
			);
		}
		// trace always ends with {main}
		$result[] = '#' . ++$key . ' {main}';

		// write tracelines into main template
		$msg = sprintf(
			$msg,
			get_class($exception),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			implode("\n", $result),
			$exception->getFile(),
			$exception->getLine()
		);

		// log or echo as you please
		error_log($msg);
	}

    public function _customError($errNo, $errStr, $errFile, $errLine) {
        if(error_reporting()==0) {
            return;
        }
        $this->_backTrace = $this->_debugBacktrace(2);

        $this->_errorMessage = "<br />\n<h1>Website Error</h1>";
        $this->_errorMessage .= "<br />\n<b>ERROR NO : </b><font color='red'>{$errNo}</font>";
        $this->_errorMessage .= "<br />\n<b>TEXT : </b><font color='red'>{$errStr}</font>";
        $this->_errorMessage .= "<br />\n<b>LOCATION : </b><font color='red'>{$errFile}</font>, <b>line</b> {$errLine}, at ".date("F j, Y, g:i a");
        $this->_errorMessage .= "<br />\n<br />\n<b>Showing Backtrace : </b><br />\n{$this->_backTrace} <br /><br />\n<br />\n";
        $this->_errorMessage .=$this->_getGlobalsVars();

        if(SEND_ERROR_MAIL == TRUE) {
			include_once('Mail.class.php');
            appMail::createMessage('Website Error: '.$errNo,ADMIN_ERROR_MAIL,$this->_errorMessage,$this->_errorMessage);
        }

        if(ERROR_LOGGING==TRUE) {
            error_log($this->_errorMessage, 3, ERROR_LOGGING_FILE);
        }

        if(DEBUGGING == TRUE) {
			echo "<pre>".$this->_errorMessage."</pre>";
        }
        else {
            echo '<div id="errorpage"><h1>Ocorreu um erro no sistema</h1><p>Já foi enviado um e-mail para o responsável com o erro da página.</p></div>
            <script>
            $("#errorpage").addClass("page_error").height("100%");
            </script>';
        }
        die();
    }
    private function _getGlobalsVars() {
		$getData = $this->traceVar($_GET);
		$postData = $this->traceVar($_POST);
		if(isset ($_SESSION))
			$sessionData = $this->traceVar($_SESSION);
		if(isset ($_COOKIE))
			$cookieData = $this->traceVar($_COOKIE);
		$error='';
		if(!empty($_GET)){
			$getData = str_replace(']=&gt;<br />&nbsp;&nbsp;','] =>&nbsp;',$getData);
			$error.="<br />\n".'<b>$_GET Variables:</b>'."<br />\n". $getData;
		}

		if(!empty($_POST)){
			$postData = str_replace(']=&gt;<br />&nbsp;&nbsp;','] =>&nbsp;',$postData);
			$error.="<br />\n".'<b>$_POST Variables:</b>'."<br />\n". $postData;
		}

		if(isset($sessionData)){
			$sessionData = str_replace(']=&gt;<br />&nbsp;&nbsp;','] =>&nbsp;',$sessionData);
			$error.="<br />\n".'<b>$_SESSION Variables:</b>'."<br />\n". $sessionData;
		}

		if(isset($_COOKIE)){
			$cookieData = str_replace(']=&gt;<br />&nbsp;&nbsp;','] =>&nbsp;',$cookieData);
			$error.="<br />\n".'<b>$_COOKIE Variables:</b>'."<br />\n". $cookieData;
		}
		return $error;
	}

    /**
     * Build backtrace message
     *
     * @param $_entriesMade Irrelevant entries in debug_backtrace, first two characters
     * @return
     */
    private function _debugBacktrace($_entriesMade)
    {
        $this->_traceArray = debug_backtrace();

        for($i=0;$i<$_entriesMade;$i++)
        {
            array_shift($this->_traceArray);
        }

        $this->_defineTabs = sizeof($this->_traceArray)-1;
        foreach($this->_traceArray as $this->_newArray)
        {
            $this->_defineTabs -=1;
            if(isset($this->_newArray['class']))
            {
                $this->_traceMessage .= $this->_newArray['class'].'.';
            }
            if(!empty($this->_newArray['args']))
            {
                foreach($this->_newArray['args'] as $this->_newValue)
                {
                    if(is_null($this->_newValue))
                    {
                        $this->_argsDefine[] = NULL;
                    }
                    elseif(is_array($this->_newValue))
                    {
                        $this->_argsDefine[] = 'Array['.sizeof($this->_newValue).']';
                    }
                    elseif(is_object($this->_newValue))
                    {
                        $this->_argsDefine[] = 'Object: '.get_class($this->_newValue);
                    }
                    elseif(is_bool($this->_newValue))
                    {
                        $this->_argsDefine[] = $this->_newValue ? 'TRUE' : 'FALSE';
                    }
                    else
                    {
                        $this->_newValue = (string)@$this->_newValue;
                        $this->_stringValue = htmlspecialchars(substr($this->_newValue, 0, $this->_MAXLENGTH));
                        if(strlen($this->_newValue)>$this->_MAXLENGTH)
                        {
                            $this->_stringValue = '...';
                        }
                        $this->_argsDefine[] = "\"".$this->_stringValue."\"";
                    }
                }
            }
            $this->_traceMessage .= $this->_newArray['function'].'('.implode(',', $this->_argsDefine).')';
            $this->_lineNumber = (isset($this->_newArray['line']) ? $this->_newArray['line']:"unknown");
            $this->_fileName = (isset($this->_newArray['file']) ? $this->_newArray['file']:"unknown");

            $this->_traceMessage .= sprintf(" # line %4d. file: %s", $this->_lineNumber, $this->_fileName, $this->_fileName);
            $this->_traceMessage .= "<br />\n";
        }
        return $this->_traceMessage;
    }

    public function _fatalError()
    {
        $this->_lastError = error_get_last();
        if($this->_lastError['type'] == 1 || $this->_lastError['type'] == 4 || $this->_lastError['type'] == 16 || $this->_lastError['type'] == 64 || $this->_lastError['type'] == 256 || $this->_lastError['type'] == 4096)
        {
            $this->_customError($this->_lastError['type'], $this->_lastError['message'], $this->_lastError['file'], $this->_lastError['line']);
        }
    }
    private function traceVar($var){
		ob_start();
		var_dump($var);
		$var = ob_get_contents();
		ob_end_clean();
		return '<pre>'.$var.'</pre>';
	}

	private function printVar($var){
		ob_start();
		print_r($var);
		$var = ob_get_contents();
		ob_end_clean();
		return '<pre>'.$var.'</pre>';
	}

	//only call this if debug on
	private function shutdown(){
		$isError = false;
		if ($error = error_get_last()){
			switch($error['type']){
				case E_ERROR:
				case E_CORE_ERROR:
				case E_COMPILE_ERROR:
				case E_USER_ERROR:
					$isError = true;
					break;
			}
		}
		if($isError){
			//print_r($error);exit;
			setErrorHandler($error['type'], $error['message'], $error['file'], $error['line']);
		}
	}
}
new ErrorLogging;
?>
