<?php
class Filter {
	// Padroes
	static $colorDark	= '#000';
	static $colorLight	= '#fff';
	static private $search=array('/January/','/February/','/March/','/April/','/May/','/June/','/July/','/August/','/September/','/October/','/November/','/December/','/Jan/','/Feb/','/Mar/','/Apr/','/May/','/Jun/','/Jul/','/Aug/','/Sep/','/Oct/','/Nov/','/Dec/','/Sunday/','/Monday/','/Tuesday/','/Wednesday/','/Thursday/','/Friday/','/Saturday/','/Sun/','/Mon/','/Tue/','/Wed/','/Thu/','/Fri/','/Sat/','/Sunday/','/Monday/','/Tuesday/','/Wednesday/','/Thursday/','/Friday/','/Saturday/','/Sun/','/Mon/','/Tue/','/Wed/','/Thu/','/Fri/','/Sat/');
	static private $pattern=array('Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez','Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Dom','Seg','Ter','Qua','Qui','Sex','Sáb','Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Dom','Seg','Ter','Qua','Qui','Sex','Sáb');

	//Filtros
	static private $debug=false;

	public static function value($value, $filter=null, $checkEmpty=true) {
		if ($checkEmpty && ($value === null || $value === '' || $value === 'NaN')) {
			return $value;
		}
		if (!is_array($filter)) {
			$filter=[$filter];
		}
		$arrayobject = new ArrayObject($filter);
		$iterator = $arrayobject->getIterator();
		while($iterator->valid()) {
			$type=$iterator->key();
			if (is_numeric($type)) {
				$type=$iterator->current();
			}
			if (self::$debug) {
				$oldValue=$value;
			}
			switch ($type) {
				case 'init':
				// inicia o debug
					self::$debug=true;
				break;
				case 'end':
				// termina o script like die;
					die();
				break;
				case 'regex':
				// [regex=>expressão] REGEX
					$value = preg_replace('/'.$iterator->current().'/i', '', $value);
				break;
				case 'mail':
				// remove chars invalidos nos emails
					$value = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $value);
				break;
				case 'clearSpaces':
				// [clearSpaces] retira espaço duplicados, e no início e fim
					$replace=array('\\\\','\\0','\\n','\\r','\Z',"\'",'\"','','','');
					$search=array('\\','\0','\n','\r','\x1a',"'",'"','string:','number:','array:');
					$value=preg_replace('/[[:blank:]]+/',' ',$value);
					$value = str_replace($search, $replace, $value);
					$value = trim($value);
				break;
				case 'password':
				// [password] retorna ********
					$value='********';
				break;
				case 'timestamp':
				// [timestamp=>dd/mm/yyyy] converte uma data (dd/mm/yyyy) em timestamp
					$value=trim(str_replace(['/'],'-',$value));
					$value=strtotime($value);
				break;
				case 'date':
				// [date=>1234567890] converte timestamp em data like date
					if (!is_numeric($value)) {
						$rep=[' - ', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro',
						'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez',
						'Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado',
						'Dom','Seg','Ter','Qua','Qui','Sex','Sáb',
						'D','S','T','Q','Q','S','S', 'January','February','March','April','May','June','July','August','September','October','November','December',
						'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec',
						'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday',
						'Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
						$value=str_replace($rep,'',$value);
						$value=str_replace('/','-',$value); // Correção para o formato dd/mm/AAAA
						$value=strtotime($value);
					}
					if (is_numeric($value) && (int)$value>0) {
						$value = date($iterator->current(), $value);
						// Caso retorne a data em inglês
						$value=preg_replace(self::$search, self::$pattern, $value);
					} else {
						$value='';
					}
				break;
				case 'numeric':
				//[numeric=>n] retorna apenas numeros, [.] e [-] ncom n casas
					$value = preg_replace('/[^[:digit:]\.\-]/i', '', $value);
					$value=number_format($value, $iterator->current(), '.', '');
				break;
				case 'split':
				// [split=>n] retorta parte inicial de uma string com tamanho n
					$value = substr($value, 0, $iterator->current());
				break;
				case 'cut':
				// [cut=>n] retorta parte inicial de uma string com tamanho n
					if (strlen($value)>(strlen(substr($value, 0, $iterator->current()))+3) && $iterator->current()>3) {
						$value = substr($value, 0, $iterator->current()).'...';
					}
				break;
				case 'phone':
				// [phone] formata no padrão de telefone
					$value = preg_replace('/[^[:digit:]]/i', '', $value);
					$len=strlen($value);
					if ($len>11) {
						$value = self::value($value, ['mask'=>[' '=>'+  #(  )#    -    ']], $checkEmpty);
					} elseif ($len>10) {
						$value = self::value($value, ['mask'=>[' '=>'(  )#     -    ']], $checkEmpty);
					} elseif ($len>9) {
						$value = self::value($value, ['mask'=>[' '=>'(  )#    -    ']], $checkEmpty);
					} else {
						$value = self::value($value, ['mask'=>'#####-####'], $checkEmpty);
					}
					$value = str_replace('#', ' ', $value);
				break;
				case 'num':
				// [num] retorna apenas numeros com porcentagem se houver
					$perc=false;
					if (strpos($value,'%')) {
						$perc=true;
					}
					$value = preg_replace('/[^[:digit:]\,\.\-]/i', '', $value);
					$value = str_replace(array('.',','),array('','.'), $value);
					if ($perc) {
						$value.= '%';
					}
				break;
				case 'decimal':
				// [decimal=>n] converte para decimal com n casas
					$value = preg_replace('/[^[:digit:]\.\-]/i', '', $value);
					if ((int)$iterator->current()>0) {
						$value=round((double)$value, $iterator->current());
					}
				break;
				case 'toPerc':
				// [toPerc=>n] calcula o valor do percentual n
					$value = self::value($value, ['num'], $checkEmpty);
					$value = preg_replace('/[^[:digit:]\.\-]/i', '', $value);
					$value*= $iterator->current()/100;
				break;
				case 'abs':
				case 'numNatural':
				// [abs] [numNatural] retorna um numero natural
					$value = preg_replace('/[^[:digit:]]/i', '', $value);
				break;
				case 'money':
				// [money] converte um numero em formato moeda 111.222.333,44
					$ponto=strrpos($value,'.');
					$virgula=strrpos($value,',');
					// checa se o valor está em formado de moeda, caso afirmativo transforma em número primeiro
					if ($ponto<$virgula) {
						$value = self::value($value, ['num'], $checkEmpty);
					}
					if (strpos($value,'%')===false) {
						$value = preg_replace('/[^[:digit:]\.\-]/i', '', $value);
						$value=number_format((double)$value, 2, ',', '.');
					}
				break;
				case 'mask':
				// [mask=>####] [mask=>[t=>123tt456##789] coloca mascara no valor, pode ter a mascará definida padrão #
					$mask=$iterator->current();
					$ch='#';
					if (is_array($mask)) {
						$arraymask = new ArrayObject($mask);
						$mask_iterator = $arraymask->getIterator();
						if ($mask_iterator->valid()) {
							$ch=$mask_iterator->key();
							$mask=$mask_iterator->current();
						}
					}

					$mask=strrev($mask);
					$str = strrev($value).$mask;
					$total=strlen(utf8_decode($mask));
					$init=0;
					$txt='';
					for ($i=0;$i<$total;$i++) {
						if ($mask[$i]==$ch && isset($str[$init])) {
							$txt.=$str[$init++];
						} else {
							$txt.=$mask[$i];
						}
					}
					$value=strrev($txt);
				break;
				case 'zero':
				// [zero=>n] preenche com n zeros a esquerda
					$value=sprintf('%0'.$iterator->current().'d', $value);
				break;
				case 'space':
				// [space=>n] preenche com n espaços a esquerda
					$value=sprintf('% '.$iterator->current().'s', $value);
				break;
				case 'rtextsize':
				case 'textsize':
				// [rtextsize=>n] [textsize=>n] alinha o texto a direita com n espaços
					$value=self::value($value, ['space'=>$iterator->current()], $checkEmpty);
					$value=mb_substr($value, 0, $iterator->current());
				break;
				case 'ltextsize':
				// [ltextsize=>n] alinha o texto a esquerda com n espaços
					$value=$value.self::value(' ', ['space'=>$iterator->current()], $checkEmpty);
					$value=substr($value, 0, $iterator->current());
				break;
				case 'uppertext':
				// [uppertext] converte para MAIÚSCULAS alias strtoupper
					$value=strtoupper($value);
				break;
				case 'safetext':
				// [safetext] converte os caracters com acento e graficos para o seu correspondente sem acento
					$patterns = [
						'/Á/','/É/','/Í/','/Ó/','/Ú/',
						'/À/','/È/','/Ì/','/Ò/','/Ù/',
						'/á/','/é/','/í/','/ó/','/ú/','/ý/',
						'/à/','/è/','/ì/','/ò/','/ù/',
						'/â/','/ê/','/î/','/ô/','/û/',
						'/Â/','/Ê/','/Î/','/Ô/','/Û/',
						'/ä/','/ë/','/ï/','/ö/','/ü/','/ÿ/',
						'/Ä/','/Ë/','/Ï/','/Ö/','/Ü/','/Ÿ/',
						'/ã/','/õ/','/ñ/','/å/','/ø/','/š/',
						'/Ã/','/Õ/','/Ñ/','/Å/','/Ø/','/Š/',
						'/ç/','/&#287;/','/&#305;/','/ö/','/&#351;/','/ü/',
						'/Ç/','/Ö/','/Ü/'
					];

					$replace  = [
						'A' , 'E' , 'I' , 'O' , 'U' ,
						'A' , 'E' , 'I' , 'O' , 'U' ,
						'a' , 'e' , 'i' , 'o' , 'u' ,'y',
						'a' , 'e' , 'i' , 'o' , 'u' ,
						'a' , 'e' , 'i' , 'o' , 'u' ,
						'A' , 'E' , 'I' , 'O' , 'U' ,
						'a' , 'e' , 'i' , 'o' , 'u' ,'y',
						'A' , 'E' , 'I' , 'O' , 'U' ,'Y',
						'a' , 'o' , 'n' , 'a' , 'q' ,'s',
						'A' , 'O' , 'N' , 'A' , 'Q' ,'S',
						'c' , 'g' , 'i' , 'o' , 's' ,'u',
						'C','O','U'
					];
					$value=preg_replace($patterns, $replace, $value);
					$value=preg_replace('/[^[:alnum:] \!\*\-\$\(\)\[\]\{\}\,\.\;\:\/\\\#\%\&\@\+\=]/i','', $value);
				break;
				case 'int':
				// [int] converte para inteiro alias (int)
					$value=(int)$value;
				break;
				case 'alpha':
				// [alpha] retorna apanas texto
					$value = preg_replace('/[^[:alpha:]\.]/i', '', $value);
					$value = (string)$value;
				break;
				case 'alnum':
				// [alnum] retorna apanas texto e numeros
					$value = preg_replace('/[^[:alnum:]\.]/i', '', $value);
					$value = (string)$value;
				break;
				case 'replace':
				// [replace=>[padrao=>substituir]] like preg_replace
					foreach ($iterator->current() as $rep_key=>$rep_value) {
						$value=preg_replace($rep_key, $rep_value, $value);
					}
				break;
				case 'cutString':
				// [cutString=>[inicio,fim], cutString=>[inicio,fim], ...]
					$tempValue=$value;
					$value=[];
					foreach ($iterator->current() as $cut_key=>$cut_len) {
						if (isset($cut_len[0]) && isset($cut_len[1])) {
							$cut_len[0]--;
							$value[$cut_key]=trim(substr($tempValue, $cut_len[0], $cut_len[1]-$cut_len[0]));
						}
					}
				break;
				case 'minus':
				// [minus] troca sina do valor
					if (is_numeric($value)) {
						$value -= $iterator->current();
					}
				break;
				case 'brightness':
				// [n] retorna claro ou escuro de acordo com a cor [colorLight, colorDark]
					$value = self::brightness($value);
				break;
				case 'hex2rgb':
				// [#123123] converte um valor de #123123 em [red, green, blue]
					$value = self::hex2RGB($value);
				break;
				case 'luminance':
				// [n] retorna o percentual n de luminance de uma cor
					$value = self::ColorLuminance($value);
				break;
			}
			$iterator->next();
			if (self::$debug) {
				echo (print_r($oldValue, true)).':'.(print_r($value,true)).'<br>';
			}
		}
		return $value;
	}

	// Retorna luminance
	private static function ColorLuminance($color, $percentual) {
		$rgb = self::hex2RGB($color);
	}
	// Retorna claro ou escuro de acordo com a cor
	private static function brightness($color) {
		$rgb = self::hex2RGB($color);
		$brightness=sqrt(
			$rgb['red'] * $rgb['red'] * .299 +
			$rgb['green'] * $rgb['green'] * .587 +
			$rgb['blue'] * $rgb['blue'] * .114
		);
		if ($brightness < 130) {
			return self::$colorLight;
		} else {
			return self::$colorDark;
		}
	}

	private static function hex2RGB($hexStr, $returnAsString = false, $seperator = ',') {
		$hexStr = preg_replace('/[^[:digit:]A-Fa-f]/', '', $hexStr); // Gets a proper hex string
		$rgbArray = array();
		if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
			$colorVal = hexdec($hexStr);
			$rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
			$rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
			$rgbArray['blue'] = 0xFF & $colorVal;
		} elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
			$rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
			$rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
			$rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
		} else {
			return false; //Invalid hex color code
		}
		return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray; // returns the rgb string or the associative array
	}
}
?>
