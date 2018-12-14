<?php
/*
* Funcoes axiliares para gerar a NFem
*/
class NFem {
	private $dom;
	private $elemento, $index=0;
	public $xml, $error;
	public $versao='1.0';
	public $dirXML, $rpsSchemaName;
	public function __construct($version='1.0', $charset='UTF-8') {
		libxml_use_internal_errors(true);
		$this->dom = new DOMDocument($version, $charset);
		$this->dom->formatOutput = true;
		$this->error=false;
	}
	
	/*
	 * LOTE
	 */
	 public function addLote($numero, $tipo=1) {
		$this->elemento['lote']['versao'] 					=  $this->versao;
		$this->elemento['lote']['numero'] 					=  $numero;
		$this->elemento['lote']['tipo'] 					=  $tipo;
	 }
	/*
	 * PRESTADOR
	 */
	public function addPrestador($documento, $razaoNome) {
		$this->elemento['prestador']['documento']			= $documento;
		$this->elemento['prestador']['razao_social']		= $razaoNome;
		
	}
	/*
	 * TOMADOR
	 * situacao_especial: 1-SUS, 2-Orgão do poder Executivo, 3-Bancos, 4-Comércio/industria, 5-Poder Legislativo/Judiciario, 0 -Outros
	 * estrangeiro: 0-não, 1-sim
	 * cidade: se for joinville não adicionar
	 */
	public function addTomador($documento, $nome, $inscricaoMunicipal, $email, $situacaoEspecial, $cep, $endereco, $numero, $complemento, $bairro, $cidade, $estado='SC', $estrangeiro=0, $pais='BR') {
		$this->elemento['tomador'][$this->index]['documento'] 			= $documento;
		$this->elemento['tomador'][$this->index]['nome'] 				= $nome;
		$this->elemento['tomador'][$this->index]['inscricao_municipal'] = $inscricaoMunicipal;
		$this->elemento['tomador'][$this->index]['email'] 				= $email;
		$this->elemento['tomador'][$this->index]['situacao_especial'] 	= $situacaoEspecial;
		$this->elemento['tomador'][$this->index]['cep'] 				= $cep;
		$this->elemento['tomador'][$this->index]['endereco'] 			= $endereco;
		$this->elemento['tomador'][$this->index]['numero'] 				= $numero;
		$this->elemento['tomador'][$this->index]['complemento'] 		= $complemento;
		$this->elemento['tomador'][$this->index]['bairro'] 				= $bairro;
		$this->elemento['tomador'][$this->index]['cidade']				= $cidade;
		$this->elemento['tomador'][$this->index]['estado'] 				= $estado;
		$this->elemento['tomador'][$this->index]['estrangeiro']			= $estrangeiro;
		$this->elemento['tomador'][$this->index]['pais'] 				= $pais;
	}
	/*
	 * RPS
	 * Operacao: I - Inserção, A - Alteração, C - cancelamento
	 * tipo: 1 - RPS, 2 -Nota fiscal mista
	 * destino_servico: 0 - outros, 1 - Comercio/industria
	 * pais_servico: 0 - Brasil, 1 - Fora do Brasil
	 * iss_retido: 1-ISS retido, 0 - ISS não retido
	 * destino_servico:0-outros, 1-Comércio/Indústria
	 * serviço:código do serviço
	 */    
	public function addRps($numero, $serie, $data, $operacao, $tipo=1, $descricaoServico, $destinoServico, $valorTotal, $valorDeducao, $servico, $localServico, $codigoCei, $paisServico=0, $aliquotaIss, $valorIss, $issRetido, $valorIrrf, $valorInss, $valorPis, $valorCofins, $valorCsll) {
		$this->elemento['rps'][$this->index]['numero']				= $numero;
		$this->elemento['rps'][$this->index]['serie']				=  $serie;
		$this->elemento['rps'][$this->index]['data'] 				=  $data;
		$this->elemento['rps'][$this->index]['operacao'] 			=  $operacao;
		$this->elemento['rps'][$this->index]['tipo'] 				=  $tipo;
		$this->elemento['rps'][$this->index]['descricao_servico']	=  $descricaoServico;
		$this->elemento['rps'][$this->index]['valor_total'] 		=  $valorTotal;
		$this->elemento['rps'][$this->index]['valor_deducao'] 		=  $valorDeducao;
		$this->elemento['rps'][$this->index]['servico'] 			=  $servico;
		$this->elemento['rps'][$this->index]['local_servico'] 		=  $localServico;
		$this->elemento['rps'][$this->index]['codigo_cei'] 			=  $codigoCei;
		$this->elemento['rps'][$this->index]['aliquota_iss'] 		=  $aliquotaIss;
		$this->elemento['rps'][$this->index]['valor_iss'] 			=  $valorIss;
		$this->elemento['rps'][$this->index]['iss_retido']			=  $issRetido;
		$this->elemento['rps'][$this->index]['valor_irrf'] 			=  $valorIrrf;
		$this->elemento['rps'][$this->index]['valor_inss'] 			=  $valorInss;
		$this->elemento['rps'][$this->index]['valor_pis'] 			=  $valorPis;
		$this->elemento['rps'][$this->index]['valor_cofins'] 		=  $valorCofins;
		$this->elemento['rps'][$this->index++]['valor_csll'] 		=  $valorCsll;
	}
	// Helper para criação dos nós XML
	private function addElement($child, $name, $value='', $attr=null) {
		$xmlRoot = $this->dom->createElement($name, $value);
		if (is_array($attr)) {
			foreach ($attr as $name=>$value) {
				$xmlAttr = $this->dom->createAttribute($name);
				$xmlAttr->value = $value;
				$xmlRoot->appendChild($xmlAttr);
			}
		}
		if ($child!==null) {
			$child->appendChild($xmlRoot);
		} else {
			$this->dom->appendChild($xmlRoot);
		}
		return $xmlRoot;
	}
	// Cria XML do Lote
	public function rpsRender() {
		if (!isset($this->elemento['lote'])) {
			$this->error='Lote não definido';
		} elseif (!isset($this->elemento['prestador'])) {
			$this->error='Prestador não definido';
		
		} elseif (!isset($this->elemento['tomador'])) {
			$this->error='Tomador não definido';
		}
		if (!$this->error) {
			$loteAttr=[
				'xmlns'=>'http://www.nfem.joinville.sc.gov.br', 
				'xmlns:xsi'=>'http://www.w3.org/2001/XMLSchema-instance', 
				'xsi:schemaLocation'=>'http://www.nfem.joinville.sc.gov.br rps_'.$this->versao.'.xsd'
			];
			$lote=$this->addElement(null,'lote', null, $loteAttr);
			$this->addElement($lote, 'versao', $this->elemento['lote']['versao']);
			$this->addElement($lote, 'numero', $this->elemento['lote']['numero']);
			$this->addElement($lote, 'tipo', $this->elemento['lote']['tipo']);			
			$prestador=$this->addElement($lote, 'prestador');
			$this->addElement($prestador, 'documento', $this->elemento['prestador']['documento']);
			$this->addElement($prestador, 'razao_social', $this->elemento['prestador']['razao_social']);
			//
			foreach ($this->elemento['rps'] as $index=>$elementoRrs) {
				$rps=$this->addElement($lote, 'rps');
				$this->addElement($rps, 'numero', $elementoRrs['numero']);
				$this->addElement($rps, 'serie', $elementoRrs['serie']);
				$this->addElement($rps, 'data', $elementoRrs['data']);
				$this->addElement($rps, 'operacao', $elementoRrs['operacao']);
				$this->addElement($rps, 'tipo', $elementoRrs['tipo']);
					$tomador=$this->addElement($rps, 'tomador');
					$elementoTomador=$this->elemento['tomador'][$index];
					if ($elementoTomador['estrangeiro']==1) {
						$this->addElement($tomador, 'estrangeiro', 1);
						$this->addElement($tomador, 'documento', $elementoTomador['documento']);
						$this->addElement($tomador, 'nome', $elementoTomador['nome']);
						$this->addElement($tomador, 'email', $elementoTomador['email']);
						$this->addElement($tomador, 'situacao_especial', $elementoTomador['situacao_especial']);
						$this->addElement($tomador, 'endereco', $elementoTomador['endereco']);
						$this->addElement($tomador, 'cidade', $elementoTomador['cidade']);
						$this->addElement($tomador, 'pais', $elementoTomador['pais']);
					} else {
						$this->addElement($tomador, 'documento', $elementoTomador['documento']);
						$this->addElement($tomador, 'nome', $elementoTomador['nome']);
						$this->addElement($tomador, 'inscricao_municipal', $elementoTomador['inscricao_municipal']);
						$this->addElement($tomador, 'email', $elementoTomador['email']);
						$this->addElement($tomador, 'situacao_especial', $elementoTomador['situacao_especial']);
						$this->addElement($tomador, 'cep', $elementoTomador['cep']);
						$this->addElement($tomador, 'endereco', $elementoTomador['endereco']);
						$this->addElement($tomador, 'numero', $elementoTomador['numero']);
						$this->addElement($tomador, 'complemento', $elementoTomador['complemento']);
						$this->addElement($tomador, 'bairro', $elementoTomador['bairro']);
						if (strtolower($elementoTomador['cidade'])!='joinville' && !empty($elementoTomador['cidade'])) {
							$this->addElement($tomador, 'cidade', $elementoTomador['cidade']);
							$this->addElement($tomador, 'estado', $elementoTomador['estado']);
						}
						$this->addElement($tomador, 'pais', $elementoTomador['pais']);
					}
				$this->addElement($rps, 'descricao_servicos', $elementoRrs['descricao_servico']);
				$this->addElement($rps, 'valor_total', $elementoRrs['valor_total']);
				if  ((int)$elementoRrs['valor_deducao']>0) {
					$this->addElement($rps, 'valor_deducao', $elementoRrs['valor_deducao']);
				}
				$this->addElement($rps, 'servico', $elementoRrs['servico']);
				if (strtolower($elementoRrs['local_servico'])!='joinville' && !empty($elementoRrs['local_servico'])) {
					$this->addElement($rps, 'local_servico', $elementoRrs['local_servico']);
				}
				if (strlen($elementoRrs['codigo_cei'])==12) {
					$this->addElement($rps, 'codigo_cei', $elementoRrs['codigo_cei']);
				}
				$this->addElement($rps, 'aliquota_iss', $elementoRrs['aliquota_iss']);
				// Valor do ISS deve ser zero
				$this->addElement($rps, 'valor_iss', $elementoRrs['valor_iss']*0);
				$this->addElement($rps, 'iss_retido', $elementoRrs['iss_retido']);
				if ($elementoRrs['iss_retido']==0) {
					$this->addElement($rps, 'valor_irrf', $elementoRrs['valor_irrf']);
					$this->addElement($rps, 'valor_inss', $elementoRrs['valor_inss']);
					$this->addElement($rps, 'valor_pis', $elementoRrs['valor_pis']);
					$this->addElement($rps, 'valor_cofins', $elementoRrs['valor_cofins']);
					$this->addElement($rps, 'valor_csll', $elementoRrs['valor_csll']);
				}
			}
			//
		}
		return $this->dom->saveXML();
	}
	// Salva XML
	public function rpsGenerate() {
		$xml=$this->rpsRender();
		if (!file_exists($this->dirXML.'/temp')) {
			mkdir($this->dirXML.'/temp');
		}
		file_put_contents($this->dirXML.'/temp/temp.xml', $xml);
		$this->rpsValidate($xml);
	}
	// Valida XML pelo arquivo xsd
	public function rpsValidate($rpsXml) {
		$xml = new DOMDocument();
		$xsd=$this->rpsSchemaName.'_'.$this->versao.'.xsd';
		$xml->loadXML($rpsXml);
		if (!$xml->schemaValidate($xsd)) {
			print '<b>DOMDocument::schemaValidate() Generated Errors!</b><br>';
			$this->libxml_display_errors(libxml_get_errors());
			libxml_clear_errors();
		}
		
	}
	// Helper para mostrar os possíveis erros
	private function libxml_display_errors($errors) {
		foreach ($errors as $error) {
			$error=$this->display_xml_error($error);
			print $error.'<hr>';
		}
	}
	private function display_xml_error($error) {
		$return = str_repeat('-', $error->column);
		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$return .= 'Alerta '.$error->code.': ';
				break;
			 case LIBXML_ERR_ERROR:
				$return .= 'Erro '.$error->code.': ';
				break;
			case LIBXML_ERR_FATAL:
				$return .= 'Erro fatal '.$error->code.': ';
				break;
		}
		$return .= trim($error->message);
		$en = [
			'{http://www.nfem.joinville.sc.gov.br}',
			'{https://www.nfem.joinville.sc.gov.br}',
			'{http://nfem.joinville.sc.gov.br}',
			'{https://nfem.joinville.sc.gov.br}',
			'[facet "pattern"]',
			'The value',
			'is not accepted by the pattern',
			'has a length of',
			'[facet "minLength"]',
			'this underruns the allowed minimum length of',
			'[facet "maxLength"]',
			'this exceeds the allowed maximum length of',
			'Element',
			'attribute',
			'is not a valid value of the local atomic type',
			'is not a valid value of the atomic type',
			'Missing child element(s). Expected is',
			'The document has no document element',
			'[facet "enumeration"]',
			'one of',
			'failed to load external entity',
			'Failed to locate the main schema resource at',
			'This element is not expected. Expected is',
			'is not an element of the set'
		];

		$pt = [
			'',
			'',
			'',
			'',
			'[Erro "Layout"]',
			'O valor',
			'não é aceito para o padrão.',
			'tem o tamanho',
			'[Erro "Tam. Min"]',
			'deve ter o tamanho mínimo de',
			'[Erro "Tam. Max"]',
			'Tamanho máximo permitido',
			'Elemento',
			'Atributo',
			'não é um valor válido',
			'não é um valor válido',
			'Elemento filho faltando. Era esperado',
			'Falta uma tag no documento',
			'[Erro "Conteúdo"]',
			'um de',
			'falha ao carregar entidade externa',
			'Falha ao tentar localizar o schema principal em',
			'Este elemento não é esperado. Esperado é',
			'não é um dos seguintes possiveis'
		];
		return str_replace($en, $pt, $return);
	}
}
?>
