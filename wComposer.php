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

require_once 'wCommon/wStandard.php';

/**
* Helper class for composing HTML (or portions thereof).
*/
class wHTMLComposer {

	function __construct() {
		$this->middle = '';
		$this->tagStack = [];
	}

/*
* Begins an HTML tag and pushes it onto the stack.
* Calls to this function must be balanced with later calls to $this->endTag, and can be nested.
* @uses getTag()
* @see getTag() for description of the parameters.
*/
	function beginTag($tag, $class='', $attribs=[], $content='') {
		array_push($this->tagStack, $tag);
		$this->middle .= self::getTag($tag, $class, $attribs, $content);
	}

/*
* Adds an HTML tag with content and includes the closing tag.
* @uses getTag()
* @see getTag() for description of the parameters.
*/
	function addTag($tag, $class='', $attribs=[], $content='') {
		$this->middle .= self::getTag($tag, $class, $attribs, $content) . "</$tag>";
	}

/*
* Adds custom string to the internal HTML string.
*/
	function addCustom($string) {
		$this->middle .= $string;
	}

/*
* Closes a previously opened HTML tag.
* Calls to this function must be balanced with prior calls to $this->beginTag().
*/
	function endTag() {
		$tag = array_pop($this->tagStack);
		$this->middle .= "</{$tag}>";
	}

/*
* Returns the HTML string that was created with prior calls to all the other functions in this class.
*/
	function getHTML() {
		return $this->middle;
	}

/*
* Returns a string of HTML with the open tag, any class or other attributes, and any content, but not the closing tag.
* @param string $tag name of the HTML tag
* @param string $class CSS class to add to the tag, defaults to empty string
* @param array $attribs attributes (other than `class`) to add to the tag, defaults to an empty array
* @param string $content text to add after the opening tag, defaults to empty string
*/
	static function getTag($tag, $class='', $attribs=[], $content='') {
		$attribString = implode(' ', array_map(function($k, $v) { return "$k=\"$v\""; }, array_keys($attribs), $attribs));
		return '<' . $tag . wrapIfCe($class, ' class="', '"') . prefixIfCe($attribString, ' ') . '>' . $content;
	}

}

?>
