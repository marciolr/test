<?php
class domManipulate {
	private $dom;
	public function __construct() {
		$this->dom = new DOMDocument();
	}
	public function stringDom($string) {
		return $this->dom->loadXML($string);
	}
	public function fileDom($file) {
		return $this->dom->load($file, LIBXML_DTDLOAD|LIBXML_DTDATTR);
	}
	public function getNodeValue($name, $index=0, $element=null) {
		if ($element===null) {
			$element=$this->dom;
		}
		$node=false;
		if (is_object($element) && $element->getElementsByTagName($name)->length != 0 && $element->getElementsByTagName($name)->length>$index) {
			$node=$element->getElementsByTagName($name)->item($index)->nodeValue;
		}
		return $node;
	}
	public function getNodeValueElement($domTag) {
		$node=false;
		if (is_object($domTag)) {
			$node=$domTag->nodeValue;
		}
		return $node;
	}
	public function getTag($name, $element=null, $index=null) {
		if ($element===null) {
			$element=$this->dom;
		}
		$node=false;
		if (is_object($element) && $element->getElementsByTagName($name)->length != 0) {
			if ($index!==null && $element->getElementsByTagName($name)->length>$index) {
				$node=$element->getElementsByTagName($name)->item($index);
			} else {
				$node=$element->getElementsByTagName($name);
			}
		}
		return $node;
	}
	public function getAttribute($tag, $attr, $index=0) {
		$node=false;
		$domTag=$this->getTag($tag, null, $index);
		if (is_object($domTag)) {
			$node=$domTag->getAttribute($attr);
		}
		return $node;
	}
	public function getAttributeElement($domTag, $attr) {
		$node=false;
		if (is_object($domTag)) {
			$node=$domTag->getAttribute($attr);
		}
		return $node;
	}
	public function unDocZip($stringZip) {
		return gzdecode(base64_decode($stringZip));
	}
}
?>
