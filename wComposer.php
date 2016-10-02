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

	const CLASSES_CACHE = 'CLASSES_CACHE';

	function __construct() {
		$this->middle = '';
		$this->tagStack = [];
	}

	public $doIndent = true;

//
// !Elements
//

/**
* Returns `true` if $elem is an empty element, such as 'input' or 'br'.
*/
	protected static function isEmptyElement($elem) {
		return in_array($elem, array('area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', ));
	}

/**
* Begins an HTML element with an opening tag and pushes it onto the tag stack.
* Calls to this function must be balanced with later calls to $this->endElement, and can be nested.
* Do not call this method with empty elements, such as 'input' or 'br'; use addElement() instead.
* @uses getElement()
* @see getElement() for description of the parameters.
*/
	function beginElement($elem, $attribs=[], $content='') {
		if ($this->doIndent) { $indent = PHP_EOL . str_repeat("\t", count($this->tagStack)); }
		array_push($this->tagStack, $elem);
		$this->middle .= $indent . self::getElement($elem, $attribs, $content);
	}

/**
* Adds an HTML element with content and includes the closing tag, or, if it is an empty element, the trailing slash.
* @uses getElement()
* @see getElement() for description of the parameters.
*/
	function addElement($elem, $attribs=[], $content='') {
		if ($this->doIndent) { $indent = PHP_EOL . str_repeat("\t", count($this->tagStack)); }
		$this->middle .= $indent . self::getElement($elem, $attribs, $content, true);
	}

/**
* Adds a custom string to the internal HTML string.
*/
	function addCustom($string) {
		$this->middle .= $string;
	}

/**
* Closes a previously opened HTML element with a closing tag.
* Calls to this function must be balanced with prior calls to $this->beginElement().
*/
	function endElement() {
		$elem = array_pop($this->tagStack);
		if (!$elem) { throw new Exception('Too many calls to endElement'); }
		if ($this->doIndent) { $indent = PHP_EOL . str_repeat("\t", count($this->tagStack)); }
		$this->middle .= $indent . "</{$elem}>";
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
* Returns a string for an HTML element with the opening tag, any class or other attributes, and any content.
* It will also return the closing tag if the $close parameter is true.
* Any 'class' attribute will be kept track of in an internal class cache.
* After using an instance of this class to compose HTML, one can query the instance to know which classes were referenced and make use of that information; for example, to determine which style files to include.
* @param string $elem name of the HTML element
* @param array $attribs attributes to add to the element, defaults to an empty array; empty values can be passed in, they will be skipped. As a shortcut, if a string is supplied by the caller for the $attribs parameter, it will be interpreted as the 'class' attribute for the HTML element.
* @param string $content text to add after the opening tag, defaults to empty string, is ignored if element is empty (such as 'input' or 'br')
* @param bool $close flag indicating whether the element should be closed, defaults to false
*/
	protected static function getElement($elem, $attribs=[], $content='', $close=false) {
		if (is_string($attribs)) { $attribs = [ 'class'=>$attribs ]; }
		if ($class = $attribs['class']) { self::registerClass($class); }
		$attribString = implode(' ', array_key_map('attribParam', array_filter($attribs, 'is_not_null')));
		if (self::isEmptyElement($elem)) { return '<' . $elem . prefixIfCe($attribString, ' ') . ' />'; }
		else { return '<' . $elem . prefixIfCe($attribString, ' ') . '>' . $content . ( $close ? "</$elem>" : '' ); }
	}

//
// !Class cache
//

/**
* Inserts a class into the global dictionary keeping track of class names.
*/
	protected static function registerClass($class) {
		$GLOBALS[self::CLASSES_CACHE][$class] += 1;
	}

/**
* Returns the list of classes that were referenced during calls to beginElement() or addElement().
*/
	static function getClasses() {
		return array_keys($GLOBALS[self::CLASSES_CACHE]);
	}

/**
* Clears the internal dictionary that is keeping track of class names that have been referenced.
*/
	static function resetClassesCache() {
		$GLOBALS[self::CLASSES_CACHE] = [];
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

}

?>
