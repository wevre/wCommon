<?php
namespace wCommon;
/**
* Subclasses that expand the functionality of HTML_Template_Sigma.
*
* @copyright 2014-2015 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2014-03-04
*
* @version 1.0
*
*/

require_once 'HTML/Template/Sigma.php';

define('g_PAGE_TITLE', 'g_PAGE_TITLE');

function setPageTitle($title) { $GLOBALS[g_PAGE_TITLE] = $title; }

/** A subclass of HTML_Template_Sigma that adds some very convenient methods. */
class Template extends \HTML_Template_Sigma {

	/**
	* Constructor for Template.
	* It loads the template file $tfile.
	* Subclasses should override and provide defaults for $tdir and $cdir.
	* @param string $tfile template file
	* @param string $tdir template directory
	* @param string $cdir cache directory
	*/
	function __construct($tfile, $tdir, $cdir=null) {
		parent::__construct($tdir, $cdir);
		$this->loadTemplateFile($tfile);
	}

	/**
	* Sets variables and parses a block.
	* By default this parses the block, meaning placeholder variables are replaced and the block is written out.
	* You can prevent that with the $doParse parameter, for situations where you need to replace variables but delay parsing.
	* @param string $block the block in the template to parse; if `null`, the __global__ block will be used
	* @param array $varArray array of block variables mapped to replacement strings
	* @param bool $doParse indicates whether or not to parse the block
	*/
	function parseBlock($block, $varArray=null, $doParse=true) { // sets variables and parses the block
		if (!$block) { $block = '__global__'; }
		$this->setCurrentBlock($block);
		if (!is_null($varArray)) { $this->setVariable($varArray); }
		if ($doParse) { $this->parseCurrentBlock(); }
	}

	/**
	* Sets block variables but skips parsing.
	* @param string $block the block in the template to parse; if `null`, the __global__ block will be used
	* @param array $varArray array of block variables mapped to replacement strings
	*/
	function setBlockVariables($block, $varArray=null) {
		$this->parseBlock($block, $varArray, false);
	}

}

/** A subclass of Template that includes more convenience methods for common page elements. See the file `template.tmpl` for a sample template that works with these methods. */
class wTemplate extends Template {

	const SKEY_ERROR = 'tmpl-error';
	const SKEY_CONFIRM = 'tmpl-confirm';

	const CLASS_CP = '\wCommon\wHTMLComposer';

	public $cp; // A composer object for use by the template.

	/** Initializes the template and checks for any error or confirmation messages to be displayed. */
	function __construct($tfile, $tdir, $cdir=null) {
		parent::__construct($tfile, $tdir, $cdir);
		$cpClass = static::CLASS_CP;
		$this->cp = new $cpClass();
		$page_title = $this->getPageTitle();
		$this->setBlockVariables(null, [ 'HEAD-TITLE'=>$page_title, ]);
		if ($_SESSION[self::SKEY_ERROR]) { foreach ($_SESSION[self::SKEY_ERROR] as $msg) { $this->displayMessage($msg, 'error'); } unset($_SESSION[self::SKEY_ERROR]); }
		if ($_SESSION[self::SKEY_CONFIRM]) { foreach ($_SESSION[self::SKEY_CONFIRM] as $msg) { $this->displayMessage($msg); } unset($_SESSION[self::SKEY_CONFIRM]); }
	}

	function show($block = '__global__') {
		if ($this->onloads) { $this->parseBlock('BLK-ONLOADS', [ 'ONLOAD'=>implode(' ', $this->onloads), ]); }
		parent::show($block);
	}

	function getPageTitle() {
		return $GLOBALS[g_PAGE_TITLE];
	}

	//
	// !Standard template parts
	//

	/** Adds a style sheet link. */
	function addStyleSheet($sheet) {
		$this->cp->addElement('link', [ 'rel'=>'stylesheet', 'href'=>$sheet, ]);
		$this->parseBlock('BLK-HEAD-ELEM', [ 'HEAD_TAG'=>$this->cp->getHTML(), ]);
	}

	/** Adds a string for an inline style sheet. */
	function addInlineStyle($text) {
		$this->cp->addElement('style', [], $text);
		$this->parseBlock('BLK-HEAD-ELEM', [ 'HEAD_TAG'=>$this->cp->getHTML(), ]);
	}

	/** Adds a script file link. */
	function addScriptFile($file) {
		$this->cp->addElement('script', [ 'src'=>$file, ]);
		$this->parseBlock('BLK-HEAD-ELEM', [ 'HEAD_TAG'=>$this->cp->getHTML(), ]);
	}

	/** Adds a string as an inline script. */
	function addInlineScript($text) {
		$this->cp->addElement('script', [], $text);
		$this->parseBlock('BLK-HEAD-ELEM', [ 'HEAD_TAG'=>$this->cp->getHTML(), ]);
	}

	/** Adds a snippet of javascript to the BODY element's onload="" attribute. Note that the onload attribute is surrounded by double quotes, so the javascript should only include single quotes. */
	function addOnload($string) {
		$this->onloads[] = $string;
	}

	/** Adds an item to the BLK-MAIN-ITEM portion. See the sample template. */
	function addItem($item) {
		$this->parseBlock('BLK-MAIN-ITEMS', array('MAIN-ITEM'=>$item, ));
	}

	/** Adds an item based on whatever has been composed in the internal $cp Composer. */
	function addComposerItem() {
		$this->addItem($this->cp->getHTML());
	}

	//
	// !Error and confirm messages
	//

		/**
		* Displays a message. On the first message only, adds an inline script to fade out the message after a delay.
		* @param string $msg The message to display
		* @param string $class An additional class to place on the message (i.e., 'error').
		* @param string $close Text to represent "close this message". Defaults to "X".
		* See the sample template for the DIV's and classes that make messages work.
		*/
	function displayMessage($msg, $class='', $close='X') {
		$this->parseBlock('BLK-MSG', array('MSG'=>$msg, 'MSG-XCLASS'=>$class, 'MSG-X'=>$close));
		if ($this->addedFader) { return; }
		$this->addInlineScript(<<<EOT
function msgClose(item) {
	while (item && item.parentNode) {
		if (item.nodeName == 'DIV' && item.className == 'mborder') {
			var next = item.nextSibling;
			if (next) {
				next.style.marginTop = (next.offsetTop - item.offsetTop) + 'px';
				setTimeout(function() { next.style.transition = 'margin-top 1s'; next.style.marginTop = 0; }, 20);
			}
			item.style.display = 'none';
			break;
		}
		item = item.parentNode;
	}
}
EOT
		);
		$this->addedFader = true;
	}

	/** Clears error messages from the session-stored list. */
	static function resetErrorMessages() { unset($_SESSION[self::SKEY_ERROR]); }

	/** Clears confirmation messages from the session-stored list. */
	static function resetConfirmMessage() { unset($_SESSION[self::SKEY_CONFIRM]); }

	/** Adds an error message to the session-stored list. They will be displayed on the next page load. The $onlyIfEmpty parameter, if true, will prevent a message from being added if one already exists. */
	static function addErrorMessage($msg, $onlyIfEmpty=false) {
		if ($onlyIfEmpty && count($_SESSION[self::SKEY_ERROR])>0) { return; }
		$_SESSION[self::SKEY_ERROR][] = $msg;
	}

	/** Adds a confirmation message to the session-stored list. They will be displayed on the next page load. The $onlyIfEmpty parameter, if true, will prevent a message from being added if one already exists. */
	static function addConfirmMessage($msg, $onlyIfEmpty=false) {
		if ($onlyIfEmpty && count($_SESSION[self::SKEY_CONFIRM])>0) { return; }
		$_SESSION[self::SKEY_CONFIRM][] = $msg;
	}

}

?>
