<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2016-03-23
	revised : 2019-06-06
		* Comment cleanup.

section : Introduction

	Helper class for constructing HTML elements.
*/

/*
section : HTMLComposer class
*/

class HTMLComposer {

	function __construct() {
		$this->cache = '';
		$this->tagStack = [];
	}

	/*
	section : Composing elements

		Compose elements in a single call to `addElement` or in matching calls to
		`beginElement` and `endElement` with other composing nested in between.
		Parameter `attribs` is a dictionary of attribute names and values, but can
		also be a single string which will act as a 'class' attribute.

		Turned on by default, $f_indent will attempt to insert indentation to
		improve readability.
	*/

	public $f_indent = true;

	// Add indentation to make rendered HTML easier to read.
	protected function addIndent() {
		if (!$this->f_indent) { return; }
		if ($this->cache) { $this->cache .= PHP_EOL; }
		$this->cache .= str_repeat("\t", count($this->tagStack));
	}

	// Open an element. To be closed later with call to `endElement`.
	function beginElement($elem, $attribs=[], $content='') {
		$this->addIndent();
		array_push($this->tagStack, $elem);
		$this->cache .= $this->getElement($elem, $attribs, $content);
	}

	// Compose element including closing tag.
	function addElement($elem, $attribs=[], $content='') {
		$this->addIndent();
		$this->cache .= $this->getElement($elem, $attribs, $content, true);
	}

	// Insert anythying.
	function addCustom($string) {
		$this->cache .= $string;
	}

	// Close previously opened element.
	function endElement() {
		$elem = array_pop($this->tagStack);
		if (!$elem) { throw new \Exception('Too many calls to endElement'); }
		$this->addIndent();
		$this->cache .= "</{$elem}>";
	}

	// Return HTML and clear internal cache.
	function getHTML($reset=true) {
		$result = $this->cache;
		if ($reset) { $this->cache = ''; }
		return $result;
	}

	// Return string for an HTML element, may or may not be closed.
	protected function getElement(
		$elem,
		$attribs=[],
		$content='',
		$close=false // Include closing tag?
	) {
		if (is_string($attribs)) { $attribs = [ 'class'=>$attribs ]; }
		else if (!is_array($attribs)) { $attribs = []; }
		$attribString = implode(
			' ',
			array_key_map(
				function ($k, $v) { return "{$k}=\"{$v}\""; },
				array_filter($attribs)
			)
		);
		$ar_result = [
			'<',
			$elem,
			prefixIfCe($attribString, ' '),
		];
		if (self::isEmptyElement($elem)) {
			$ar_result[] = ' />';
		} else {
			$ar_result[] = '>';
			$ar_result[] = $content;
			if ($close) { $ar_result[] = "</$elem>"; }
		}
		return implode($ar_result);
	}

	protected static function isEmptyElement($elem) {
		return in_array($elem, [
			'area',
			'base',
			'br',
			'col',
			'command',
			'embed',
			'hr',
			'img',
			'input',
			'link',
			'meta',
			'param',
			'source',
		]);
	}

}
