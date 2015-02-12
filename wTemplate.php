<?php
/**
* Expands the functionality of HTML_Template_Sigma.
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

define('SKEY_ERROR', 'error');
define('SKEY_CONFIRM', 'confirm');

// -----------------------------
//! Template class extends the template class to add the very convenient methods parseBlock() and setBlockVariables()
// scripts can use Template to parse a template file for an element that will be inserted into the final page

class Template extends HTML_Template_Sigma {

	function __construct($tfile, $tdir, $cdir=null) { //NOTE: subclasses should override and provide defaults for template_directory and cache_directory
		parent::__construct($tdir, $cdir);
		$this->loadTemplateFile($tfile);
	}

	function parseBlock($block, $varArray=null, $doParse=true) { // sets variables and parses the block
		if (!$block) { $block = '__global__'; }
		$this->setCurrentBlock($block);
		if (!is_null($varArray)) { $this->setVariable($varArray); }
		if ($doParse) { $this->parseCurrentBlock(); }
	}

	function setBlockVariables($block, $varArray=null) { // sets variables but does NOT parse the block, so caller can do other things if needed before parsing
		if (!$block) { $block = '__global__'; }
		$this->setCurrentBlock($block);
		if (!is_null($varArray)) { $this->setVariable($varArray); }
	}

} // end of Template definition

// -----------------------------
//! StandardTemplate includes convenient functions for common page elements

class CommonTemplate extends Template {

	function __construct($tfile, $tdir, $cdir=null) {
		parent::__construct($tfile, $tdir, $cdir);
		if ($_SESSION[SKEY_ERROR]) { foreach ($_SESSION[SKEY_ERROR] as $msg) { $this->addMessage($msg, 'error'); } unset($_SESSION[SKEY_ERROR]); }
		if ($_SESSION[SKEY_CONFIRM]) { foreach ($_SESSION[SKEY_CONFIRM] as $msg) { $this->addMessage($msg); } unset($_SESSION[SKEY_CONFIRM]); }
	}

	// -----------------------------
	//! methods for standard template parts: style sheets, scripts, and so forth // all these should be defined in the tmpl-standard.tpl file

	function addStyleSheet($sheet) { $this->parseBlock('BLK-STYLE', array('STYLESHEET'=>$sheet, )); }

	function addIEStyleSheet($sheet) { $this->parseBlock('BLK-STYLE-IE', array('STYLESHEET'=>$sheet, )); }

	function addInlineStyle($text) { $this->parseBlock('BLK-INLINE-STYLE', array('STYLE'=>$text, )); }

	function addScriptFile($file) { $this->parseBlock('BLK-SCRIPT', array('SCRIPTFILE'=>$file, )); }

	function addInlineScript($text) { $this->parseBlock('BLK-INLINE-SCRIPT', array('SCRIPT'=>$text, )); }

	function addOnload($string) { $this->parseBlock('BLK-ONLOADS', array('ONLOAD'=>$string, )); }

	// -----------------------------
	//! handling messages and items in the main section of the page
	//NOTE: assumes the presence of the script-fader.js script

	function addMessage($msg, $class='') {
		$this->parseBlock('BLK-MSG', array('MSG'=>$msg, 'MSG-XCLASS'=>$class, ));
		$this->addjQuery();
		if (!$this->addedFader) {
			$this->addScriptFile('/scripts/script-fader.js');
			$this->addedFader = true;
		}
	}

	function addjQuery() {
		if (!$this->addedjQuery) {
			$this->addScriptFile('//code.jquery.com/jquery-1.10.2.min.js');
			$this->addedjQuery = true;
		}
	}

	// -----------------------------
	//! functions for setting the error or confirm message that will display at the top of the page

	static function resetErrorMessages() { unset($_SESSION[SKEY_ERROR]); }

	static function resetConfirmMessage() { unset($_SESSION[SKEY_CONFIRM]); }

	static function addErrorMessage($msg, $onlyIfEmpty=false) {
		if ($onlyIfEmpty && count($_SESSION[SKEY_ERROR])>0) { return; }
		$_SESSION[SKEY_ERROR][] = $msg;
	}

	static function addConfirmMessage($msg, $onlyIfEmpty=false) {
		if ($onlyIfEmpty && count($_SESSION[SKEY_CONFIRM])>0) { return; }
		$_SESSION[SKEY_CONFIRM][] = $msg;
	}

} // end of CommonTemplate definition

?>
