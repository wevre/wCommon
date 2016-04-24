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

//require_once 'wCommon/wStandard.php';

/**
* Helper class for composing HTML (or portions thereof).
*/
class wHTMLComposer {

	function __construct() {
		$this->middle = '';
		$this->tagStack = [];
		$this->classCache = [];
	}

//
// !Tags
//

/**
* Returns `true` if $tag is a self-closing tag, such as input or br.
*/
	protected function isSelfClosingTag($tag) {
		return in_array($tag, array('area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', ));
	}

/**
* Begins an HTML tag and pushes it onto the stack.
* Calls to this function must be balanced with later calls to $this->endTag, and can be nested.
* Do not call this method with self-closing tags, such as input or br; use addTag() instead.
* @uses getTag()
* @see getTag() for description of the parameters.
*/
	function beginTag($tag, $class='', $attribs=[], $content='') {
		array_push($this->tagStack, $tag);
		$this->middle .= self::getTag($tag, $class, $attribs, $content);
		$this->registerClass($class);
	}

/**
* Adds an HTML tag with content and includes the closing tag.
* Handles self-closing tags, in which case it doesn't add a closing tag, but the trailing slash.
* @uses getTag()
* @see getTag() for description of the parameters.
*/
	function addTag($tag, $class='', $attribs=[], $content='') {
		$this->middle .= self::getTag($tag, $class, $attribs, $content) . ( self::isSelfClosingTag($tag) ? ' />' : "</$tag>" );
		$this->registerClass($class);
	}

/**
* Adds custom string to the internal HTML string.
*/
	function addCustom($string) {
		$this->middle .= $string;
	}

/**
* Closes a previously opened HTML tag.
* Calls to this function must be balanced with prior calls to $this->beginTag().
*/
	function endTag() {
		$tag = array_pop($this->tagStack);
		$this->middle .= "</{$tag}>";
	}

/**
* Returns the HTML string that was created with prior calls to all the other functions in this class.
* By default, this clears the internal string that has been accumulating HTML text, so that an instance of this class can continue to be used for a new round of HTML text composition. The class cache is NOT cleared, so that after the instance has been used for multiple rounds, we have a full picture of all the classes used along the way.
*/
	function getHTML($reset=true) {
		$result = $this->middle;
		if ($reset) { $this->resetHTML(); }
		return $result;
	}

/**
* Returns a string of HTML with the open tag, any class or other attributes, and any content, but not the closing tag.
* Class names passed in to the $class parameter are kept track of in an internal class cache.
* After using an instance of this class to compose HTML, one can query the instance to know which classes were referenced and make use of that information; for example, to determine which style files to include.
* @param string $tag name of the HTML tag
* @param string $class CSS class to add to the tag, defaults to empty string
* @param array $attribs attributes (other than `class`) to add to the tag, defaults to an empty array; empty values can be passed in, they will be skipped
* @param string $content text to add after the opening tag, defaults to empty string, is ignored if tag is self-closing
*/
	protected static function getTag($tag, $class='', $attribs=[], $content='') {
		$filtered = array_filter($attribs, function ($v) { return !empty($v); } );
		$attribString = implode(' ', array_map(function($k, $v) { return "$k=\"$v\""; }, array_keys($filtered), $filtered));
		return '<' . $tag . wrapIfCe($class, ' class="', '"') . prefixIfCe($attribString, ' ') . ( self::isSelfClosingTag($tag) ? '' : '>' . $content );
	}

//
// !Class cache
//

/**
* Inserts a class into the internal dictionary keeping track of class names.
*/
	protected function registerClass($class) {
		$this->classCache[$class] += 1;
	}

/**
* Returns the list of classes that were referenced during calls to beginTag() or addTag().
*/
	function getClasses() {
		return array_keys($this->classCache);
	}

//
// !Reseting internal variables
//

/**
* Resets the internal string that accumulates HTML text back to an empty string.
*/
	function resetHTML() {
		$this->middle = '';
	}

/**
* Clears the internal dictionary that is keeping track of class names that have been referenced.
*/
	function resetClassCache() {
		$this->classCache = [];
	}

}

?>
