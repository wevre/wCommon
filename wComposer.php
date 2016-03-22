<?php
/**
* Helper class for constructing HTML elements.
*
* @copyright 2016 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2016-03-23
*
* @version 1.0
*
*/

class wHTMLComposer {

	function __construct() {
		$this->middle = '';
	}

	function beginTag($tag, $class, $attribs=[], $content='') {
		$this->currentTag = $tag;
		$this->middle .= $this->getTag($tag, $class, $attribs, $content);
	}

	function addTag($tag, $class, $attribs=[], $content='') {
		$this->middle .= $this->getTag($tag, $class, $attribs, $content) . "</$tag>";
	}

	function addCustom($string) {
		$this->middle .= $string;
	}

	function endTag() {
		$this->middle .= "</{$this->currentTag}>";
	}

	function getHTML() {
		return $this->middle;
	}

	function getTag($tag, $class, $attribs=[], $content='') {
		$attribString = implode(' ', array_map(function($k, $v) { return "$k=\"$v\""; }, array_keys($attribs), $attribs));
		return '<' . $tag . wrapIfCe($class, ' class="', '"') . prefixIfCe($attribString, ' ') . '>' . $content;
	}

}

?>
