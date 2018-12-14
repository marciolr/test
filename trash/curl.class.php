<?php
/*
 * Função auxiliar para o RESTFUL
 */
class Restful {
    public 	$response = null;      	// Contem o retorno
    private $url;                 	// URL da sessão
    private $options = array();   	// Populates curl_setopt_array
    private $headers = array();   	// Extra HTTP headers
    public 	$error_code;            // Retorna código de Erro
    public 	$error_string;          // Retorna mensagem de Erro
    public 	$info;                  // Retorna de informações (tempo passado, etc)
    public 	$log=false;             // Habilita ou desabilita log.
    
    private $logtxt=null;           // Texto log.
    public 	$set_proxyIP=null;		// Proxy IP
	public	$set_proxyPort=null;	// Proxy Port
	public	$set_proxyLogin=null;	// Proxy login
	public 	$set_proxyPass=null;	// Proxy pass
	private	$nl="\r\n";
	
	public function __construct() {
		$this->timeout(3000);
		if ($this->log) {
			$this->message('debug', 'Class Initialized');
		}
    }
    
    // Auxiliar Types
    private function make_types($type) {
		
		switch(strtolower($type))
        {
            case "form" :
                return "application/x-www-form-urlencoded";
            case "js" :
                return "application/x-javascript";

            case "json" :
                return "application/json";

            case "jpg" :
            case "jpeg" :
            case "jpe" :
                return "image/jpg";

            case "png" :
				return "image/png";
            case "gif" :
				return "image/gif";
            case "bmp" :
				return "image/bmp";
            case "tiff" :
                return "image/tiff";

            case "css" :
                return "text/css";

            case "xml" :
                return "text/xml";

            case "doc" :
            case "docx" :
                return "application/msword";

            case "xls" :
            case "xlt" :
            case "xlm" :
            case "xld" :
            case "xla" :
            case "xlc" :
            case "xlw" :
            case "xll" :
                return "application/vnd.ms-excel";

            case "ppt" :
            case "pps" :
                return "application/vnd.ms-powerpoint";

            case "rtf" :
                return "application/rtf";

            case "pdf" :
                return "application/pdf";

            default :
            case "html" :
            case "htm" :
            case "php" :
                return "text/html";

            case "txt" :
                return "text/plain";

            case "mpeg" :
            case "mpg" :
            case "mpe" :
                return "video/mpeg";

            case "mp3" :
                return "audio/mpeg3";

            case "wav" :
                return "audio/wav";

            case "aiff" :
            case "aif" :
                return "audio/aiff";

            case "avi" :
                return "video/msvideo";

            case "wmv" :
                return "video/x-ms-wmv";

            case "mov" :
                return "video/quicktime";

            case "zip" :
                return "application/zip";

            case "tar" :
                return "application/x-tar";

            case "swf" :
                return "application/x-shockwave-flash";
        }
	}
    
	public function request($method, $url, $type='html', $params) {
	     // Realização uma ação
        if ($this->set_proxyIP!=null && $this->set_proxyPort!=null) {
			$this->option('proxy' , 'tcp://'.$this->set_proxyIP.':'.$this->set_proxyPort);
			$this->option('request_fulluri' , true);
			if ($this->set_proxyLogin!=null && $this->set_proxyLogin!=null) {
				$auth = base64_encode($this->set_proxyLogin.':'.$this->set_proxyPass);
				$this->http_header('Proxy-Authorization', 'Basic '.$auth);
			}
		}
		$this->option('method' , strtoupper($method));
		$this->ignore_errors(true);
		$this->connection('close');
		$this->url=$url;
        switch (strtolower($method)) {
			default:
			case 'get':
				return $this->call_get($type, $params);
			break;
			case 'post':
				return $this->call_post($type, $params);
			break;
			case 'put':
				return $this->call_put($type, $params);
			break;
			case 'delete':
				return $this->call_delete($type, $params);
			break;
			case 'options':
				return $this->call_options($type, $params);
			break;
			case 'head':
				return $this->call_head($type, $params);  
			break;
        }
    }
    
    // Requests
	private function call_get($type, $params) {
		if ($this->log) {
			$this->message('alert', 'Type:'.$type);
		}
		if(is_array($params)) {
			$this->url.='?'.http_build_query($params);
		}
		//$this->option('content',$params);
		//$this->http_header('Content-Length',strlen($params));
		$this->http_header('Content-Type',$this->make_types($type));
		return $this->execute();
	}
	
	private function call_post($type, $params) {
		if ($this->log) {
			$this->message('alert', 'Type:'.$type);
		}
		if(is_array($params)) {
			$params=http_build_query($params);
		}
		$this->option('content',$params);
		$this->http_header('Content-Length',strlen($params));
		$this->http_header('Content-Type',$this->make_types($type));
		return $this->execute();
	}
	private function call_put($type, $params) {
		if ($this->log) {
			$this->message('alert', 'Type:'.$type);
		}
		if(is_array($params)) {
			$params=http_build_query($params);
		}
		$this->option('content',$params);
		$this->http_header('Content-Length',strlen($params));
		$this->http_header('Content-Type',$this->make_types($type));
		return $this->execute();
	}
	private function call_options($type, $params) {
		if ($this->log) {
			$this->message('alert', 'Type:'.$type);
		}
		if(is_array($params)) {
			$this->url.='?'.http_build_query($params);
		}
		$this->http_header('Content-Type',$this->make_types($type));
		return $this->execute();
	}
	private function call_head($type, $params) {
		if ($this->log) {
			$this->message('alert', 'Type:'.$type);
		}
		if(is_array($params)) {
			$this->url.='?'.http_build_query($params);
		}
		$this->http_header('Content-Type',$this->make_types($type));
		return $this->execute();
	}

    // COOKIES
    public function set_cookies($params = array()) {
        if (is_array($params)) {
            $params = http_build_query($params, null, '&');
        }
        $this->http_header('Cookie', $params);
    }
    // Populate headers
    public function multiheader($headers = array()) {
        // Junta com outras opções
        foreach ($headers as $headers_code => $headers_value) {
            $this->http_header($headers_code, $headers_value);
        }
    }
    
    public function http_header($header, $content = null) {
		if (isset($this->headers[$header])) {
			$this->headers[$header] = $content.'; '.$this->headers[$header];
		} else {
			$this->headers[$header] = $content;
		}
    }
    
    private function make_header() {
		$header='';
        foreach ($this->headers as $key=>$value) {
			$header.= $key.':'.$value.$this->nl;
		}
		 $this->option('header',$header);
    }
    
    // seta opções
    public function option($code, $value) {
        $this->options[$code] = $value;
    }
    
    public function agent($txt='Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1') {
		$this->option('user_agent' , $txt);
	}
    public function timeout($timeout=300) {
		ini_set('default_socket_timeout',$timeout);
		$this->option('timeout' , $timeout);
	}
    public function ignore_errors($type=true) {
		$this->option('ignore_errors' , $type);
	}
    public function connection($type='close') {
		$this->http_header('Connection' , $type);
	}
	// cria os parametros
	private function make_params() {
		$this->make_header();
		$params = array('http' => $this->options);
	   return $params;
	}
	// Executa e retorna
    private function execute() {
		$fp = file_get_contents($this->url, false, stream_context_create($this->make_params()));
		if (!$fp) {
			$this->response['body']=array(500 => 'Internal Server Error');
		} else {
			$this->response['body']=$fp;
		}
		$this->response['head']=$this->headers;
	}
	
	// Debug
	public function message($error,$msg) {
		$this->logtxt.='<h4>'.$error.'</h4>'.$msg;
	}
	
	public function debug() {
		$response='';
		$response.= "=============================================<br/>\n";
		$response.= "<h2>Test</h2>\n";
		$response.= "=============================================<br/>\n";
		$response.= "<h3>Response</h3>\n";
		if (isset($this->last_response)) {
			$response.= "<code>" . nl2br(htmlentities($this->last_response)) . "</code><br/>\n\n";
		}
		if ($this->error_string) {
			$response.= "=============================================<br/>\n";
			$response.= "<h3>Errors</h3>";
			$response.= "<strong>Code:</strong> " . $this->error_code . "<br/>\n";
			$response.= "<strong>Message:</strong> " . $this->error_string . "<br/>\n";
		}
		$response.= "=============================================<br/>\n";
		$response.= "<h3>Info</h3>\n";
		$response.= "<pre>\n";
		$response.=print_r($this->response,true)."<br/>\n";
		$response.= "=============================================<br/>\n";
		$response.= "</pre>\n";
		$response.= "<h3>HEADERS</h3>\n";
		$response.= "<pre>\n";
		$response.=print_r($this->headers,true)."<br/>\n";
		$response.= "</pre>\n";
		$response.= "=============================================<br/>\n";
		$response.= "<h3>Options</h3>\n";
		$response.= "<pre>\n";
		$response.=print_r($this->options,true)."<br/>\n";
		$response.= "</pre>\n";
		$response.= "=============================================<br/>\n";
		if ($this->logtxt!==null) {
			$response.= "<pre>\n";
			$response.= "<h3>Log</h3>\n";
			$response.=$this->logtxt."<br/>\n";
			$response.= "</pre>\n";
		}
		return $response;
	}
}
?>
