<?php
/*
* Funcoes axiliares para gerar a NFem - JOINVILLE
*
* Link Emissor
* -- https://nfem.joinville.sc.gov.br/processos/emitir_nfe.aspx?tmdDoc=0
* Impressão
* -- https://nfem.joinville.sc.gov.br/processos/imprimir_nfe.aspx?numero=NFENUMERO&documento_prestador=CNPJ
* Login
* -- https://nfem.joinville.sc.gov.br/processos/imprimir_nfe.aspx?numero=NFENUMERO&documento_prestador=CNPJ
*/
class invoiceNFem {
	public $isLogin			= false;
	public $user			= null;
	public $pass			= null;
	public $proxyIP			= '';
	public $proxyPort		= '';
	public $error			= false;
	public $errorMsg		= null;
	//
	private $cookiePath		= null;
	private $nfemPah		= null;
	private $options		= [];
	private $headers		= [];
	private $cookieName		= 'cookieFile.txt';
	private $baseUrl		= 'https://nfem.joinville.sc.gov.br/';
	private $dataNFem		= null;
	//
	public function __construct($user, $pass) {
		$this->user=$user;
		$this->pass=$pass;
		if (empty($this->user) || empty($this->pass)) {
			$this->setError('Usuário e senha da NF-em devem ser preenchidos nas configurações');
			return false;
		}
		$this->cookiePath=Doo::conf()->TMP_PATH;
		$this->nfemPah=Doo::conf()->NFem_PATH;
		if ($this->nfemPah!==null && !is_dir($this->nfemPah)) {
			mkdir($this->nfemPah);
		}
		if ($this->nfemPah!==null && !is_dir($this->nfemPah.'print/')) {
			mkdir($this->nfemPah.'print/');
		}
		if ($this->cookiePath!==null && !is_dir(Doo::conf()->TMP_PATH)) {
			mkdir($this->cookiePath);
		}
	}
	private static function headerRead( $curl, $header_line ) {
		return strlen($header_line);
	}
	public function withProxy() {
		if (!empty($this->proxyIP) && !empty($this->proxyPort)) {
			$this->options[CURLOPT_PROXY]=$this->proxyIP;
			$this->options[CURLOPT_PROXYPORT]=$this->proxyPort;
			$this->options[CURLOPT_HTTPPROXYTUNNEL]=CURLPROXY_HTTP;
			$this->options[CURLOPT_PROXYTYPE]=true;
		}
	}
	public function checkLogin($dom) {
		$info=$dom->getElementById("ctl00_conteudo_cpf_cnpj_prestador");
		if($info!=null) {
			$this->isLogin=true;
		}
		return $this->isLogin;
	}
	private function clearCookie() {
		if (file_exists($this->cookiePath.$this->cookieName)) {
			unlink($this->cookiePath.$this->cookieName);
		}
	}
	public function getError() {
		return $this->errorMsg;
	}
	private function setError($mens) {
		$this->error=true;
		$this->errorMsg[]=$mens;
	}
	private function resetError() {
		$this->error=false;
		$this->errorMsg=null;
	}
	private function makeLogin() {
		if (empty($this->user) || empty($this->pass)) {
			$this->setError('Usuário e senha da NF-em devem ser preenchidos nas configurações');
			return false;
		}
		try {
			$response=$this->get($this->baseUrl.'login.aspx', 'Testa login ');
			$dom = new DOMDocument();
			$dom->preserveWhiteSpace = false;
			$dom->strictErrorChecking = false;
			$dom->formatOutput = true;

			@$dom->loadHTML($response);

			if (!$this->checkLogin($dom)) {
				if (file_exists($this->cookiePath.$this->cookieName)) {
					unlink($this->cookiePath.$this->cookieName);
				}
				$input = $dom->getElementsByTagName("input");
				$data=[
					'__LASTFOCUS'=>'',
					'__EVENTTARGET'=>'',
					'__EVENTARGUMENT'=>'',
					'__VIEWSTATE'=>'',
					'__VIEWSTATEGENERATOR'=>'',
					'__EVENTVALIDATION'=>'',
					'ctl00$conteudo$documento'=>'',
					'ctl00$conteudo$senha'=>'',
					'ctl00$conteudo$botao'=>'Entrar',
					'ctl00$conteudo$tbDocSituacao'=>'',
					'ctl00$conteudo$nfe_documento_prestador'=>'',
					'ctl00$conteudo$nfe_numero'=>'',
					'ctl00$conteudo$nfe_codigo'=>'',
				];

				foreach ($input as $tag) {
					$data[$tag->getAttribute('name')]=$tag->getAttribute('value');
				}
				unset($data['ctl00$conteudo$visualizar_nfe']);
				unset($data['ctl00$conteudo$btSituacao']);
				$data['ctl00$conteudo$documento']=$this->user;
				$data['ctl00$conteudo$senha']=$this->pass;
				$response=$this->post($this->baseUrl.'login.aspx', $data);
				$dom = new DOMDocument();
				$dom->preserveWhiteSpace = false;
				$dom->strictErrorChecking = false;
				$dom->formatOutput = true;
				@$dom->loadHTML($response);
				$errors=@$dom->getElementById('ctl00_conteudo_ValidationSummary1');
				if ($errors!==null && @$errors->getElementsByTagName('li')->length>0) {
					foreach($errors->getElementsByTagName('li') as $node) {
						$txt=utf8_decode($node->nodeValue);
						if ($txt=='O texto digitado não corresponde à imagem.') {
							$this->setError('Erro ao efetuar o login, verifique o seu usuário ou senha.');
							$this->clearCookie();
						} else {
							$this->setError($txt);
						}
					}
				} else {
					$this->isLogin=true;
				}
			}
			return $response;
		} catch(Exception $e ) {
			$this->clearCookie();
			$this->setError('Problemas ao se comunicar com o site da prefeitura.');
			return false;
		}
	}

	public function printNFem($num, $try=0) {
		$num=(int)$num;
		if ($this->error || $num==0) {
			return false;
		}
		if ($this->nfemPah!==null && file_exists($name=$this->nfemPah.'print/nfem'.$num.'.pdf')) {
			//headers
			header('Content-Type: application/pdf');
			header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
			header('Pragma: public');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
			header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
			header('Content-Disposition: inline; filename="'.basename($name).'"');
			echo file_get_contents($name);
		} elseif ($try<3) {
			$this->makePDFNFem($num);
			$this->printNFem($num, $try++);
		} else {
			$this->setError('Erro ao tentar criar o arquivo PDF.');
		}
	}
	public function makePDFNFem($num) {
		$num=(int)$num;
		if ($this->error || $num==0) {
			return false;
		}
		$data=$this->getInfoNFem($num);
		$pdf=new PDF();
		$pdf->SetMargins(10, 0);
		$pdf->setHeaderMargin(0);
		$pdf->setFooterMargin(0);
		$pdf->setImageScale(1);
		//
		$pdf->SetSubject('NFem Nº'.$data['nfe_numero']);
		$pdf->AddPage();
		$border=0.2;
		$titleBigSize=14;
		$titleSize=9;
		$textSize=8;
		$smallSize=7;

		$margin=$pdf->getMargins();

		$maxWidth=$pdf->setColumns(1,1);
		$image=20;
		$box=34;
		$offset=($box-$image)/2;
		$lineStyle=['width' => 0.2, 'cap' => 'butt', 'join' => 'miter', 'dash' => '', 'phase' => 0, 'color' => [0, 0, 0]];
		$boxStyle=['width' => 0.5, 'cap' => 'square', 'join' => 'miter', 'dash' => 0, 'phase' => 0];
		$pdf->Rect($margin['left']-1, $margin['top']-1, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y=$pdf->getY()+$box-1;

		//$pdf->Line($x1, $y1, $x2, $y2, $style=array());

		$pdf->Image(Doo::conf()->VIEW_FOLDER.'layout/nfem/7952/print/logo_prefeitura.png', $margin['left'], $margin['top']+$offset/2, 30);

		$boxTopRight=$maxWidth-30;

		$pdf->setY($margin['top']+$offset-2);

		$pdf->SetFont('helvetica', 'B', $titleBigSize);
		$pdf->Ln(1);
		$pdf->MultiCell($boxTopRight+$margin['left'],0, 'PREFEITURA MUNICIPAL DE JOINVILLE', null, 'C', 0, 1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-5);
		$pdf->Ln(1);
		$pdf->MultiCell($boxTopRight+$margin['left'],0, 'SECRETARIA MUNICIPAL DA FAZENDA', null, 'C', 0, 1);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-4);
		$pdf->Ln(1);
		$pdf->MultiCell($boxTopRight+$margin['left'],0, 'NOTA FISCAL DE SERVIÇOS - ELETRÔNICA (NF-em)', null, 'C', 0, 1);
		$pdf->SetLeftMargin($boxTopRight);
		$pdf->Line($boxTopRight, $margin['top']-1, $boxTopRight, $margin['top']+$box-1, $lineStyle);
		$pdf->setY($margin['top']);
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell(40, 0, 'Número da NF-em', null, 'C', 0, 1);
		$pdf->SetFont('helvetica', 'B', $textSize);
		$pdf->MultiCell(40, 0, $data['nfe_numero'], null, 'C', 0, 1);
		$pdf->Ln(1);
		$pdf->Line($boxTopRight, $pdf->getY(), $maxWidth+$margin['right'], $pdf->getY(), $lineStyle);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell(40, 0, 'Data e Hora de Emissão', null, 'C', 0, 1);
		$pdf->SetFont('helvetica', 'B', $textSize);
		$pdf->MultiCell(40, 0, date('d/m/Y H:m', $data['nfe_data']), null, 'C', 0, 1);
		$pdf->Ln(1);
		$pdf->Line($boxTopRight, $pdf->getY(), $maxWidth+$margin['right'], $pdf->getY(), $lineStyle);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell(40, 0, 'Código de Verificação', null, 'C', 0, 1);
		$pdf->SetFont('helvetica', 'B', $textSize);
		$code=explode('-',$data['nfe_codigo']);
		$txt=$code[0]."-\n".$code[1]."-".$code[2]."-".$code[3]."-\n".$code[4];
		$pdf->MultiCell(40, 0, $txt, null, 'C', 0, 1);
		$pdf->Ln(1);
		// Prestador
		$boxy=$pdf->getY();
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-4);
		$pdf->MultiCell($maxWidth, 0, 'PRESTADOR DE SERVIÇOS', null, 'C', 0, 1);
		$pdf->Ln(2);
		if (file_exists(Doo::Conf()->NFem_PATH.'logo.png')) {
			$pdf->Image(Doo::Conf()->NFem_PATH.'logo.png', $margin['left'], $pdf->getY()+1, 35);
		}
		$yt=$pdf->getY();
		$offsetMargin=64;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-5);

		$row1=$pdf->getStringHeight($maxWidth-$offsetMargin, $data['prestador_nome']);
		$row2=$pdf->getStringHeight($maxWidth-$offsetMargin, $data['prestador_endereco']);

		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['prestador_documento'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, $data['prestador_nome'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, $data['prestador_endereco'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['prestador_cep'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['prestador_cidade'], null, 'L', 0, 1);

		$offsetMargin=35;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->setY($yt);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-5);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'CPF/CNPJ:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, 'Razão Social:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, 'Endereço:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'CEP:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Município:', null, 'L', 0, 1);

		$offsetMargin=110;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->setY($yt);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-5);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Inscrição Municipal:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Inscrição Estadual:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Estado:', null, 'L', 0, 1);

		$offsetMargin=145;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->setY($yt);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-5);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['prestador_inscricao'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['prestador_inscricao_estadual'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['prestador_estado'], null, 'L', 0, 1);
		$pdf->Ln(1);
		//
		$box=$pdf->getY()-$boxy;
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y+=$box;
		// Tomador
		$boxy=$y;
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-4);
		$pdf->MultiCell($maxWidth, 0, 'TOMADOR DE SERVIÇOS', null, 'C', 0, 1);
		$pdf->Ln(2);
		$yt=$pdf->getY();
		$offsetMargin=40;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-5);
		$row1=$pdf->getStringHeight($maxWidth-$offsetMargin, $data['tomador_nome']);
		$row2=$pdf->getStringHeight($maxWidth-$offsetMargin, $data['tomador_endereco']);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['tomador_documento'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, $data['tomador_nome'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, $data['tomador_endereco'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['tomador_cep'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['tomador_cidade'], null, 'L', 0, 1);

		$offsetMargin=0;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->setY($yt);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-5);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'CPF/CNPJ:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, 'Nome/Razão Social:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, 'Endereço:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'CEP:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Município:', null, 'L', 0, 1);

		$offsetMargin=105;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->setY($yt);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-5);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Inscrição Municipal:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Inscrição Estadual:', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, 'Estado:', null, 'L', 0, 1);

		$offsetMargin=145;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->setY($yt);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-5);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['tomador_inscricao'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row1, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, $row2, '', null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['tomador_inscricao_estadual'], null, 'L', 0, 1);
		$pdf->Ln(1);
		$pdf->MultiCell($maxWidth-$offsetMargin, 0, $data['tomador_estado'], null, 'L', 0, 1);
		$pdf->Ln(1);
		//
		$box=$pdf->getY()-$boxy;
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y+=$box;

		// Serviços
		$boxy=$pdf->getY();
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-4);
		$pdf->MultiCell($maxWidth, 0, 'DISCRIMINAÇÃO DOS SERVIÇOS', null, 'C', 0, 1);
		$pdf->Ln(2);
		$yt=$pdf->getY();
		$offsetMargin=0;
		$pdf->SetLeftMargin($margin['left']+$offsetMargin+1);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-5);
		$row1=$pdf->getStringHeight($maxWidth, $data['nfe_descricao_servico']);
		$pdf->MultiCell($maxWidth, 0, $data['nfe_descricao_servico'], null, 'L', 0, 1);
		$pdf->Ln(1);
		//
		$box=85;
		if ($row1>$box) {
			$box=$row1;
		}
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y+=$box;
		// Totais
		$boxy=$y;
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-4);
		$pdf->MultiCell($maxWidth, 0, 'VALOR TOTAL DA NOTA = R$ '.$data['nfe_valor_total'], null, 'C', 0, 1);
		$pdf->Ln(1);
		//
		$box=$pdf->getY()-$boxy;
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y+=$box;
		// Código Serviço
		$boxy=$y;
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'N', $textSize);
		$pdf->MultiCell($maxWidth, 0, 'Código do Serviço: <b>'.$data['nfe_servico'].'</b> '.$data['nfe_servico_descricao'], null, 'L', 0, 1,'','',true,0,true);
		$pdf->Ln(1);
		//
		$box=$pdf->getY()-$boxy;
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y+=$box;
		// Impostos
		$boxy=$y;
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$col=$maxWidth/4;
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell($col, 0, 'Valor Retenções (R$)', null, 'C', 0, 1);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $textSize+1);
		$pdf->MultiCell($col, 0, $data['nfe_valor_retencao'], null, 'R', 0, 1);
		$pdf->SetLeftMargin($margin['left']+$col);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell($col, 0, 'Base Cálculo ISS (R$)', null, 'C', 0, 1);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $textSize+1);
		$pdf->MultiCell($col, 0, $data['nfe_base_calculo'], null, 'R', 0, 1);
		$pdf->SetLeftMargin($margin['left']+$col*2);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell($col, 0, 'Alíquota ISS (%)', null, 'C', 0, 1);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $textSize+1);
		$pdf->MultiCell($col, 0, $data['nfe_aliquota'].'%', null, 'R', 0, 1);
		$pdf->SetLeftMargin($margin['left']+$col*3);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'N', $smallSize);
		$pdf->MultiCell($col, 0, 'Valor do ISS (R$)', null, 'C', 0, 1);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $textSize+1);
		$pdf->MultiCell($col, 0, $data['nfe_iss'], null, 'R', 0, 1);
		//
		$pdf->Ln(1);
		$box=$pdf->getY()-$boxy;
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$pdf->Line($margin['left']+$col, $y, $margin['left']+$col, $y+$box, $lineStyle);
		$pdf->Line($margin['left']+$col*2, $y, $margin['left']+$col*2, $y+$box, $lineStyle);
		$pdf->Line($margin['left']+$col*3, $y, $margin['left']+$col*3, $y+$box, $lineStyle);
		$y+=$box;
		// Outras info
		$boxy=$y;
		$pdf->SetLeftMargin($margin['left']);
		$pdf->setY($y);
		$pdf->Ln(1);
		$pdf->SetFont('helvetica', 'B', $titleBigSize-4);
		$pdf->MultiCell($maxWidth, 0, 'OUTRAS INFORMAÇÕES', null, 'C', 0, 1);
		$pdf->SetFont('helvetica', 'N', $titleBigSize-5);
		$pdf->MultiCell($maxWidth, 0, $data['informacoes_adicionais'], null, 'L', 0, 1,'','',true,0,true);

		//MultiCell($w, $h, $txt, $border=0, $align='J', $fill=false, $ln=1, $x='', $y='', $reseth=true, $stretch=0, $ishtml=false, $autopadding=true, $maxh=0, $valign='T', $fitcell=false)

		//
		$box=$pdf->getY()-$boxy;
		$pdf->Rect($margin['left']-1, $y, $maxWidth+1, $box, 'S', ['all' =>$boxStyle]);
		$y+=$box;

		$pdf->Output(Doo::conf()->NFem_PATH.'print/nfem'.$num.'.pdf', 'F');
	}

	public function getInfoNFem($num) {
		$num=(int)$num;
		if (!$this->isLogin) {
			$this->makeLogin();
		}
		if ($this->error || $num==0) {
			return false;
		}
		$data=[];
		$url=$this->baseUrl.'processos/';
		$html=$this->get($url.'imprimir_nfe.aspx?numero='.$num.'&documento_prestador='.$this->user, 'Impressão ');
		if ($this->error) {
			return false;
		}
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->strictErrorChecking = false;
		$dom->formatOutput = true;
		@$dom->loadHTML(utf8_decode($html));
		$img = $dom->getElementsByTagName("img");
		foreach ($img as $tag) {
			$tag->setAttribute('src', $this->baseUrl.$tag->getAttribute('src'));
		}
		$html=$dom->saveHTML();
		//
		$data['nfe_numero'] = $dom->getElementById('nfe_numero')->nodeValue;
		$data['nfe_data'] = strtotime(str_replace('/','-',$dom->getElementById('nfe_data')->nodeValue));
		$data['nfe_codigo'] = $dom->getElementById('nfe_codigo')->nodeValue;
		// Prestador
		/*if (!file_exists(Doo::Conf()->NFem_PATH.'logo.png')) {
			$image=false;//$this->get($dom->getElementById('prestador_logomarca')->getAttribute('src'));
			if ($image!==false) {
				imagepng(imagecreatefromstring($image), Doo::Conf()->NFem_PATH.'logo.png', 9);
			}
		}*/
		$data['prestador_logomarca'] = '';
		//Logo:id="prestador_logomarca" src="https://nfem.joinville.sc.gov.br/../imagem_logomarca.aspx?id=13439"
		$data['prestador_documento'] = $dom->getElementById('prestador_documento')->nodeValue;
		$data['prestador_inscricao'] = $dom->getElementById('prestador_inscricao')->nodeValue;
		$data['prestador_nome'] = $dom->getElementById('prestador_nome')->nodeValue;
		$data['prestador_endereco'] = $dom->getElementById('prestador_endereco')->nodeValue;
		$data['prestador_cep'] = $dom->getElementById('prestador_cep')->nodeValue;
		$data['prestador_inscricao_estadual'] = $dom->getElementById('prestador_inscricao_estadual')->nodeValue;
		$data['prestador_cidade'] = $dom->getElementById('prestador_cidade')->nodeValue;
		$data['prestador_estado'] = $dom->getElementById('prestador_estado')->nodeValue;
		// Tomador
		$data['tomador_documento'] = $dom->getElementById('tomador_documento')->nodeValue;
		$data['tomador_inscricao'] = $dom->getElementById('tomador_inscricao')->nodeValue;
		$data['tomador_nome'] = $dom->getElementById('tomador_nome')->nodeValue;
		$data['tomador_endereco'] = $dom->getElementById('tomador_endereco')->nodeValue;
		$data['tomador_cep'] = $dom->getElementById('tomador_cep')->nodeValue;
		$data['tomador_inscricao_estadual'] = $dom->getElementById('tomador_inscricao_estadual')->nodeValue;
		$data['tomador_cidade'] = $dom->getElementById('tomador_cidade')->nodeValue;
		$data['tomador_estado'] = $dom->getElementById('tomador_estado')->nodeValue;
		// Serviços
		$data['nfe_descricao_servico'] = $dom->getElementById('nfe_descricao_servico')->nodeValue;
		// Totais/Impostos/Outros
		$data['nfe_valor_total'] = $dom->getElementById('nfe_valor_total')->nodeValue;
		$data['nfe_servico'] = $dom->getElementById('nfe_servico')->nodeValue;
		$data['nfe_servico_descricao'] = $dom->getElementById('nfe_servico_descricao')->nodeValue;
		$data['nfe_valor_retencao'] = $dom->getElementById('nfe_valor_retencao')->nodeValue;
		$data['nfe_base_calculo'] = $dom->getElementById('nfe_base_calculo')->nodeValue;
		$data['nfe_aliquota'] = $dom->getElementById('nfe_aliquota')->nodeValue;
		$data['nfe_iss'] = $dom->getElementById('nfe_iss')->nodeValue;
		$data['informacoes_adicionais'] = $dom->getElementById('informacoes_adicionais')->c14n();
		$data=array_map('trim',$data);
		return $data;
	}
	// Headers e opções do CURL
	private function initOptions() {
		$headers=[
			'Accept:text/html,application/xhtml+xml,application/xml;q=0.9,* /*;q=0.8',
			'Accept-Encoding:deflate, br',
			'Accept-Language:pt-BR',
			'Connection:keep-alive',
			'Content-Type:application/x-www-form-urlencoded',
			'DNT:1',
			'Host:nfem.joinville.sc.gov.br',
			'Referer:https://nfem.joinville.sc.gov.br',
			'Upgrade-Insecure-Requests:1',
			'User-Agent:Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:54.0) Gecko/20100101 Firefox/54.0',
			'Connection: keep-alive',
			'Keep-Alive: 300'
		];
		return [
			CURLOPT_AUTOREFERER => true,
			CURLOPT_BINARYTRANSFER=>false,
			CURLOPT_COOKIESESSION=>false,
			CURLOPT_COOKIEJAR=>$this->cookiePath.$this->cookieName,
			CURLOPT_COOKIEFILE=>$this->cookiePath.$this->cookieName,
			CURLOPT_CERTINFO=>false,
			CURLOPT_CRLF=>true,
			CURLOPT_DNS_USE_GLOBAL_CACHE=>false,
			CURLOPT_FAILONERROR=>false,
			CURLOPT_FILETIME=>false,
			CURLOPT_FOLLOWLOCATION=>true,
			CURLOPT_FORBID_REUSE=>true,
			CURLOPT_FRESH_CONNECT=>true,
			CURLOPT_HEADER=>false,
			CURLINFO_HEADER_OUT=>false,
			CURLOPT_NETRC=>false,
			CURLOPT_NOBODY=>false,
			CURLOPT_NOPROGRESS=>true,
			CURLOPT_NOSIGNAL=>true,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_SSL_VERIFYPEER=>false,
			CURLOPT_TRANSFERTEXT=>false,
			CURLOPT_UPLOAD=>false,
			CURLOPT_VERBOSE=>false,
			//CURLOPT_BUFFERSIZE=>(1024*16),
			CURLOPT_CONNECTTIMEOUT=>120,
			CURLOPT_DNS_CACHE_TIMEOUT=>120,
			CURLOPT_FTPSSLAUTH=>false,
			CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,
			CURLOPT_MAXCONNECTS=>1,
			CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
			CURLOPT_SSLVERSION=>CURL_SSLVERSION_TLSv1,
			CURLOPT_TIMEOUT=>300,
			CURLOPT_HTTPHEADER=>$headers,
			//CURLOPT_HEADERFUNCTION=>'self::headerRead',
			CURLOPT_PRIVATE=>true,
			CURLOPT_REFERER=>$this->baseUrl,
			CURLOPT_ENCODING       => "",
		];
	}
	private function get($url, $location='') {
		if ($this->error) {
			return false;
		}
		$curl = curl_init();
		$options=$this->initOptions();
		$options[CURLOPT_HTTPGET]=true;
		$options[CURLOPT_URL]=$url;
		$options[CURLOPT_POST]=false;
		curl_setopt_array($curl, $options);
		$response=curl_exec($curl);
		if (curl_errno($curl)!=0) {
			//echo 'GET:'.$location.' -> '.$url;
			$this->setError($location.curl_error($curl));
			$response=false;
			//die('Error');
		}
		curl_close($curl);
		return $response;
	}
	private function post($url, $data, $location='') {
		if ($this->error) {
			return false;
		}
		$curl = curl_init();
		$options=$this->initOptions();
		$options[CURLOPT_HTTPGET]=false;
		$options[CURLOPT_POST]=true;
		$options[CURLOPT_POSTFIELDS]= http_build_query($data);
		$options[CURLOPT_URL]=$url;
		curl_setopt_array($curl, $options);
		$response=curl_exec($curl);
		if (curl_errno($curl)!=0) {
			//echo 'POST:'.$location.' -> '.$url.PHP_EOL;
			//var_dump($options[CURLOPT_POSTFIELDS]);
			//var_dump(curl_getinfo($curl));
			$this->setError($location.curl_error($curl));
			$response=false;
			//die('Error');
		}
		curl_close($curl);
		return $response;
	}
	private function nfemStep1() {
		/*
			STEP 1
			Tomador:
			name="ctl00$conteudo$nacionalidade" value="tomador_nacional"
			name="ctl00$conteudo$nacionalidade" value="tomador_internacional"
			name="ctl00$conteudo$cpf_cnpj" maxlength="18"
			name="ctl00$conteudo$ultimo_cpf_cnpj" type="hidden"
			name="ctl00$conteudo$busca_tomador$client_id_campo_documento" value="ctl00_conteudo_cpf_cnpj"
			name="ctl00$conteudo$busca_tomador$filtro_nome" maxlength="60"
			name="ctl00$conteudo$busca_tomador$botao_filtrar" value="Buscar Tomador" type="submit"
			name="ctl00$conteudo$botao_prosseguir" value="Próximo Passo" type="submit"
		*/
		if (!$this->isLogin) {
			$this->makeLogin();
		}
		$html=$this->get($this->baseUrl.'processos/emitir_nfe.aspx?tmdDoc=0', 'Inicio passo 1 ');
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->strictErrorChecking = false;
		$dom->formatOutput = true;
		@$dom->loadHTML($html);
		$form=[];
		$input = $dom->getElementsByTagName("input");
		foreach ($input as $tag) {
			$form[$tag->getAttribute('name')] = $tag->getAttribute('value');
		}
		unset($form['ctl00$conteudo$busca_tomador$botao_filtrar']); // Remove botão busca
		$form['ctl00$conteudo$nacionalidade']='tomador_nacional';
		$form['ctl00$conteudo$cpf_cnpj']=$this->dataNFem['cpfCnpj'];
		$form['ctl00$conteudo$ultimo_cpf_cnpj']='';
		$html=$this->post($this->baseUrl.'processos/emitir_nfe.aspx?tmdDoc=0', $form, 'Envio passo 1 ');
		return $this->nfemStep2($html);
	}
	private function nfemStep2($html, $try=0) {
		/*
		STEP 2
		Tomador:
		Nome: name="ctl00$conteudo$nome" maxlength="60"
		IF CNPJ
			Inscrição Municipal: name="ctl00$conteudo$inscricao_municipal" maxlength="20"
			Inscrição Estadual: name="ctl00$conteudo$tbInscricaoEstadual"
		E-mail: name="ctl00$conteudo$email" maxlength="50"
		Situação Especial: name="ctl00$conteudo$situacao_especial"
			value="0" > Outro
			value="1" > SUS
			value="2" > Órgão do poder Executivo
			value="3" > Bancos
			value="4" > Comércio/Indústria
			value="5" > Poder Legislativo/Judiciário
		Endereço do Tomador:
		CEP: name="ctl00$conteudo$cep" maxlength="8"
		Logradouro: name="ctl00$conteudo$logradouro" maxlength="150"
		Número: name="ctl00$conteudo$numero" maxlength="10"
		Complemento: name="ctl00$conteudo$complemento" maxlength="100"
		Bairro: name="ctl00$conteudo$bairro" maxlength="50"
		Cidade:
		IF JOINVILLE
			name="ctl00$conteudo$cidade" value="cidade_interna"
		ELSE
			name="ctl00$conteudo$cidade" value="cidade_externa"
			name="ctl00$conteudo$nome_cidade_externa" maxlength="60"
			Estado: name="ctl00$conteudo$estado" value="SIGLA"
		Valor Total: name="ctl00$conteudo$valor_total" maxlength="20"
		Valor de Dedução ISSQN: name="ctl00$conteudo$valor_deducao" maxlength="20"
		Valor IR: name="ctl00$conteudo$valor_ir" maxlength="20"
		Valor CSLL: name="ctl00$conteudo$valor_csll" maxlength="20"
		Valor PIS: name="ctl00$conteudo$valor_pis" maxlength="20"
		Valor COFINS: name="ctl00$conteudo$valor_cofins" maxlength="20"
		Valor INSS: name="ctl00$conteudo$valor_inss" maxlength="20"
		Descrição dos Serviços: name="ctl00$conteudo$descricao_servico"
		Serviço: name="ctl00$conteudo$codigo_servico"
			value="1" : 1.01
			value="2" : 1.02
			value="3" : 1.03
			value="4" : 1.04
			value="5" : 1.05
			value="6" : 1.06
			value="7" : 1.07
			value="8" : 1.08
			value="9" : 2.01
			value="11" : 3.02
			value="12" : 3.03
			value="13" : 3.04
			value="14" : 3.05
			value="15" : 4.01
			value="16" : 4.02
			value="17" : 4.03
			value="18" : 4.04
			value="19" : 4.05
			value="20" : 4.06
			value="21" : 4.07
			value="22" : 4.08
			value="23" : 4.09
			value="24" : 4.10
			value="25" : 4.11
			value="26" : 4.12
			value="27" : 4.13
			value="28" : 4.14
			value="29" : 4.15
			value="30" : 4.16
			value="31" : 4.17
			value="32" : 4.18
			value="33" : 4.19
			value="34" : 4.20
			value="35" : 4.21
			value="36" : 4.22
			value="37" : 4.23
			value="38" : 5.01
			value="39" : 5.02
			value="40" : 5.03
			value="41" : 5.04
			value="42" : 5.05
			value="43" : 5.06
			value="44" : 5.07
			value="45" : 5.08
			value="46" : 5.09
			value="47" : 6.01
			value="48" : 6.02
			value="49" : 6.03
			value="50" : 6.04
			value="51" : 6.05
			value="52" : 7.01
			value="53" : 7.02
			value="54" : 7.03
			value="55" : 7.04
			value="56" : 7.05
			value="57" : 7.06
			value="58" : 7.07
			value="59" : 7.08
			value="60" : 7.09
			value="61" : 7.10
			value="62" : 7.11
			value="63" : 7.12
			value="64" : 7.13
			value="67" : 7.16
			value="68" : 7.17
			value="69" : 7.18
			value="70" : 7.19
			value="71" : 7.20
			value="72" : 7.21
			value="73" : 7.22
			value="74" : 8.01
			value="75" : 8.02
			value="76" : 9.01
			value="77" : 9.02
			value="78" : 9.03
			value="79" : 10.01
			value="80" : 10.02
			value="81" : 10.03
			value="82" : 10.04
			value="83" : 10.05
			value="84" : 10.06
			value="85" : 10.07
			value="86" : 10.08
			value="87" : 10.09
			value="88" : 10.10
			value="89" : 11.01
			value="90" : 11.02
			value="91" : 11.03
			value="92" : 11.04
			value="93" : 12.01
			value="94" : 12.02
			value="95" : 12.03
			value="96" : 12.04
			value="97" : 12.05
			value="98" : 12.06
			value="99" : 12.07
			value="100" : 12.08
			value="101" : 12.09
			value="102" : 12.10
			value="103" : 12.11
			value="104" : 12.12
			value="105" : 12.13
			value="106" : 12.14
			value="107" : 12.15
			value="108" : 12.16
			value="109" : 12.17
			value="111" : 13.02
			value="112" : 13.03
			value="113" : 13.04
			value="114" : 13.05
			value="115" : 14.01
			value="116" : 14.02
			value="117" : 14.03
			value="118" : 14.04
			value="119" : 14.05
			value="120" : 14.06
			value="121" : 14.07
			value="122" : 14.08
			value="123" : 14.09
			value="124" : 14.10
			value="125" : 14.11
			value="126" : 14.12
			value="127" : 14.13
			value="128" : 15.01
			value="129" : 15.02
			value="130" : 15.03
			value="131" : 15.04
			value="132" : 15.05
			value="133" : 15.06
			value="134" : 15.07
			value="135" : 15.08
			value="136" : 15.09
			value="137" : 15.10
			value="138" : 15.11
			value="139" : 15.12
			value="140" : 15.13
			value="141" : 15.14
			value="142" : 15.15
			value="143" : 15.16
			value="144" : 15.17
			value="145" : 15.18
			value="146" : 16.01
			value="147" : 17.01
			value="148" : 17.02
			value="149" : 17.03
			value="150" : 17.04
			value="151" : 17.05
			value="152" : 17.06
			value="154" : 17.08
			value="155" : 17.09
			value="156" : 17.10
			value="157" : 17.11
			value="158" : 17.12
			value="159" : 17.13
			value="160" : 17.14
			value="161" : 17.15
			value="162" : 17.16
			value="163" : 17.17
			value="164" : 17.18
			value="165" : 17.19
			value="166" : 17.20
			value="167" : 17.21
			value="168" : 17.22
			value="169" : 17.23
			value="170" : 17.24
			value="171" : 18.01
			value="172" : 19.01
			value="173" : 20.01
			value="174" : 20.02
			value="175" : 20.03
			value="176" : 21.01
			value="177" : 22.01
			value="178" : 23.01
			value="179" : 24.01
			value="180" : 25.01
			value="181" : 25.02
			value="182" : 25.03
			value="183" : 25.04
			value="184" : 26.01
			value="185" : 27.01
			value="186" : 28.01
			value="187" : 29.01
			value="188" : 30.01
			value="189" : 31.01
			value="190" : 32.01
			value="191" : 33.01
			value="192" : 34.01
			value="193" : 35.01
			value="194" : 36.01
			value="195" : 37.01
			value="196" : 38.01
			value="197" : 39.01
			value="198" : 40.01
		name="ctl00$conteudo$confirmar" value="Próximo Passo" type="submit"

		Errors:: id="ctl00_conteudo_ValidationSummary2" -> li
		*/
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->strictErrorChecking = false;
		$dom->formatOutput = true;
		@$dom->loadHTML($html);
		$errors=@$dom->getElementById('ctl00_conteudo_ValidationSummary2');
		// tenta por 3 vezes e para
		if ($try>2) {
			$this->setError('[e1]Erro ao receber dados do site da prefeitura verifique se a nota foi emitida.<br>'.$errors);
			return false;
		}
		if ($errors!==null && @$errors->getElementsByTagName('li')->length>0) {
			foreach($errors->getElementsByTagName('li') as $node) {
				$txt=utf8_decode($node->nodeValue);
				if ($txt!='A cidade não está preenchida.') {
					$this->setError($txt);
				}
			}
			if ($this->error) {
				return false;
			}
		}
		$form=[];
		$input = $dom->getElementsByTagName("input");
		foreach ($input as $tag) {
			$form[$tag->getAttribute('name')] = $tag->getAttribute('value');
		}
		// Preenche o formulário
		$form['ctl00$conteudo$nome'] = Filter::value($this->dataNFem['nome'], ['split'=>60]);
		if (strlen($this->dataNFem['cpfCnpj'])>11) {
			$form['ctl00$conteudo$inscricao_municipal'] = $this->dataNFem['im'];
			$form['ctl00$conteudo$tbInscricaoEstadual'] = $this->dataNFem['ie'];
			$form['ctl00$conteudo$situacao_especial'] = 0;// Padrão Outros
		}
		$form['ctl00$conteudo$email'] = $this->dataNFem['mail'];
		// Endereço
		$form['ctl00$conteudo$cep'] = $this->dataNFem['cep'];
		$form['ctl00$conteudo$logradouro'] = $this->dataNFem['endereco'];
		$form['ctl00$conteudo$numero'] = $this->dataNFem['numero'];
		$form['ctl00$conteudo$complemento'] = $this->dataNFem['complemento'];
		$form['ctl00$conteudo$bairro'] = $this->dataNFem['bairro'];
		if (strtolower($this->dataNFem['cidade'])=='joinville') {
			$form['ctl00$conteudo$cidade'] = 'cidade_interna';
			$form['ctl00$conteudo$estado'] = 'SC';
		} else {
			$form['ctl00$conteudo$cidade'] = 'cidade_externa';
			$form['ctl00$conteudo$nome_cidade_externa'] = $this->dataNFem['cidade'];
			$form['ctl00$conteudo$estado'] = $this->dataNFem['estado'];
		}
		$form['ctl00$conteudo$valor_total'] = filter::value($this->dataNFem['valorTotal'], ['money']);
		$form['ctl00$conteudo$valor_deducao'] = filter::value($this->dataNFem['deducao']);
		if (isset($form['ctl00$conteudo$tipo_servico'])) {
			$form['ctl00$conteudo$tipo_servico'] = 'tipo_servico_meus';
		}
		$form['ctl00$conteudo$codigo_servico'] = $this->dataNFem['codigoServicos'];
		$form['ctl00$conteudo$descricao_servico'] = $this->dataNFem['servicos'];
		// Dados informativos
		$form['ctl00$conteudo$valor_ir'] = filter::value($this->dataNFem['valorIr'], ['money']);
		$form['ctl00$conteudo$valor_csll'] = filter::value($this->dataNFem['valorCsll'], ['money']);
		$form['ctl00$conteudo$valor_pis'] = filter::value($this->dataNFem['valorPis'], ['money']);
		$form['ctl00$conteudo$valor_cofins'] = filter::value($this->dataNFem['valorCofins'], ['money']);
		$form['ctl00$conteudo$valor_inss'] = filter::value($this->dataNFem['valorInss'], ['money']);
		// Submit
		$form['ctl00$conteudo$confirmar'] = 'Próximo Passo';
		$html=$this->post($this->baseUrl.'processos/emitir_nfe.aspx?tmdDoc=0', $form, 'Envio passo 2 ');
		// checa se foi enviado e pode prosseguir para o passo seguinte, caso contrário tenta novamente
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->strictErrorChecking = false;
		$dom->formatOutput = true;
		$h=@$dom->loadHTML($html);
		$next=@$dom->getElementById('ctl00_conteudo_grupo_observacao_confirmacao');
		if ($next!==null && @$next->getElementsByTagName('h1')->length>0) {
			return $this->nfemStep3($html);
		}
		/*$this->setError('[e2]Erro ao receber dados do site da prefeitura verifique se a nota foi emitida.');
		$errors=@$dom->getElementById('ctl00_conteudo_ValidationSummary2');
		if ($errors!==null && @$errors->getElementsByTagName('li')->length>0) {
			foreach($errors->getElementsByTagName('li') as $node) {
				$txt=utf8_decode($node->nodeValue);
				if ($txt!='A cidade não está preenchida.') {
					$this->setError($txt);
				}
			}
		}*/
		//return false;
		return $this->nfemStep2($html, $try++);
	}
	private function nfemStep3($html) {
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->strictErrorChecking = false;
		$dom->formatOutput = true;
		@$dom->loadHTML($html);
		$errors=@$dom->getElementById('ctl00_conteudo_ValidationSummary2');
		if ($errors!==null && @$errors->getElementsByTagName('li')->length>0) {
			foreach($errors->getElementsByTagName('li') as $node) {
				$txt=utf8_decode($node->nodeValue);
				$this->setError($txt);
			}

			return false;
		}
		$form=[];
		$input = $dom->getElementsByTagName("input");
		foreach ($input as $tag) {
			$form[$tag->getAttribute('name')] = $tag->getAttribute('value');
		}
		$html=$this->post($this->baseUrl.'processos/emitir_nfe.aspx?tmdDoc=0', $form, 'Envio passo 3 ');
		//echo $html;
		return $this->nfemStep4($html);
	}
	private function nfemStep4($html) {
		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->strictErrorChecking = false;
		$dom->formatOutput = true;
		@$dom->loadHTML($html);
		$errors=@$dom->getElementById('ctl00_conteudo_ValidationSummary2');
		if ($errors!==null && @$errors->getElementsByTagName('li')->length>0) {
			foreach($errors->getElementsByTagName('li') as $node) {
				$txt=utf8_decode($node->nodeValue);
				$this->setError($txt);
			}
			return false;
		}
		$action=$dom->getElementById('aspnetForm');
		if ($action!==null) {
			$form=$action->getAttribute('action');
			preg_match('/.*?numero=([0-9]{1,})/', $form, $number);
		}
		$response=isset($number[1])?$number[1]:false;
		if($response!==false) {
			$this->makePDFNFem($response);
		}
		//echo $html;
		return $response;
	}
	private function checkData() {
		if (!isset($this->dataNFem['nome']) || empty($this->dataNFem['nome'])) {
			$this->setError('Nome deve ser preenchido.');
		}
		if (!isset($this->dataNFem['cpfCnpj']) || empty($this->dataNFem['cpfCnpj'])) {
			$this->setError('CPF/CNPJ deve ser preenchido.');
		}
		if (!isset($this->dataNFem['valorTotal']) || empty($this->dataNFem['valorTotal'])) {
			$this->setError('Valor Total deve ser preenchido.');
		}
		return true;
	}
	public function initNFem($nome, $cpfCnpj, $im, $ie, $mail, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado, $valorTotal, $deducao, $codigoServicos, $servicos, $ir='', $csll='', $pis='', $cofins='', $inss='') {
		$this->dataNFem=[
			'nome'			=>$nome,
			'cpfCnpj'		=>$cpfCnpj,
			'im'			=>$im,
			'ie'			=>$ie,
			'mail'			=>$mail,
			'cep'			=>$cep,
			'endereco'		=>$endereco,
			'numero'		=>$numero,
			'complemento'	=>$complemento,
			'bairro'		=>$bairro,
			'cidade'		=>$cidade,
			'estado'		=>$estado,
			'valorTotal'	=>$valorTotal,
			'deducao'		=>$deducao,
			'codigoServicos'=>$codigoServicos,
			'servicos'		=>$servicos,
			'valorIr'		=>$ir,
			'valorCsll'		=>$csll,
			'valorPis'		=>$pis,
			'valorCofins'	=>$cofins,
			'valorInss'		=>$inss,
		];
		return $this->checkData();
	}
	public function sendNFem() {
		if (!$this->error) {
			return $this->nfemStep1();
		} else {
			return false;
		}
	}
}
?>
