<?php
namespace wCommon;
/**
* Subclass that expands functionality of HTML_Template_Sigma.
*
* @copyright 2014-2015 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2014-03-04
*
* @version 1.0.1
*
*/

/** A subclass of HTML_Template_Sigma that adds some very convenient methods. */
class TemplateSigma extends \HTML_Template_Sigma {

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
