<?php
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

/**
* A subclass of HTML_Template_Sigma that adds some very convenient methods.
*/
class Template extends HTML_Template_Sigma {

	const SKEY_ERROR = 'tmpl-error';
	const SKEY_CONFIRM = 'tmpl-confirm';

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
		if (!$block) { $block = '__global__'; }
		$this->setCurrentBlock($block);
		if (!is_null($varArray)) { $this->setVariable($varArray); }
	}

}

/**
* A subclass of Template that includes more convenience methods for common page elements.
* See the included file `template.tmpl` for a sample template that works with these methods.
*/
class wTemplate extends Template {

/**
*
*/
	function __construct($tfile, $tdir, $cdir=null) {
		parent::__construct($tfile, $tdir, $cdir);
		if ($_SESSION[self::SKEY_ERROR]) { foreach ($_SESSION[self::SKEY_ERROR] as $msg) { $this->displayMessage($msg, 'error'); } unset($_SESSION[self::SKEY_ERROR]); }
		if ($_SESSION[self::SKEY_CONFIRM]) { foreach ($_SESSION[self::SKEY_CONFIRM] as $msg) { $this->displayMessage($msg); } unset($_SESSION[self::SKEY_CONFIRM]); }
	}

	// -----------------------------
	// !methods for standard template parts: style sheets, scripts, and so forth // all these should be defined in the tmpl-standard.tpl file

/*
*
*/
	function addStyleSheet($sheet) { $this->parseBlock('BLK-STYLE', array('STYLESHEET'=>$sheet, )); }

/*
*
*/
	function addIEStyleSheet($sheet) { $this->parseBlock('BLK-STYLE-IE', array('STYLESHEET'=>$sheet, )); }

/*
*
*/
	function addInlineStyle($text) { $this->parseBlock('BLK-INLINE-STYLE', array('STYLE'=>$text, )); }

/*
*
*/
	function addScriptFile($file) { $this->parseBlock('BLK-SCRIPT', array('SCRIPTFILE'=>$file, )); }

/*
*
*/
	function addInlineScript($text) { $this->parseBlock('BLK-INLINE-SCRIPT', array('SCRIPT'=>$text, )); }

/**
* Adds a snippet of javascript to the BODY element's onload="" attribute. Note that the onload attribute is surrounded by double quotes, so the javascript should only include single quotes.
*/
	function addOnload($string) { $this->parseBlock('BLK-ONLOADS', array('ONLOAD'=>$string, )); }

	// -----------------------------
	// !handling messages and items in the main section of the page
	//NOTE: assumes the presence of the script-fader.js script

/*
*
*/
	function displayMessage($msg, $class='') {
		$this->parseBlock('BLK-MSG', array('MSG'=>$msg, 'MSG-XCLASS'=>$class, ));
		$this->addjQuery();
		if (!$this->addedFader) {
			$this->addScriptFile('/scripts/script-fader.js');
			$this->addedFader = true;
		}
	}

/*
*
*/
	function addjQuery() {
		if (!$this->addedjQuery) {
			$this->addScriptFile('//code.jquery.com/jquery-1.10.2.min.js');
			$this->addedjQuery = true;
		}
	}

	// -----------------------------
	// !functions for setting the error or confirm message that will display at the top of the page

/*
*
*/
	static function resetErrorMessages() { unset($_SESSION[self::SKEY_ERROR]); }

/*
*
*/
	static function resetConfirmMessage() { unset($_SESSION[self::SKEY_CONFIRM]); }

/*
*
*/
	static function addErrorMessage($msg, $onlyIfEmpty=false) {
		if ($onlyIfEmpty && count($_SESSION[self::SKEY_ERROR])>0) { return; }
		$_SESSION[self::SKEY_ERROR][] = $msg;
	}

/*
*
*/
	static function addConfirmMessage($msg, $onlyIfEmpty=false) {
		if ($onlyIfEmpty && count($_SESSION[self::SKEY_CONFIRM])>0) { return; }
		$_SESSION[self::SKEY_CONFIRM][] = $msg;
	}

}

?>
