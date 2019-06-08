<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2014-03-04
	revised : 2019-06-06
		* Comment cleanup.

section : Introduction

	Subclass of HTML_Template_Sigma that adds some very convenient methods.
*/

/*
section : TemplateSigma class
*/

class TemplateSigma extends \HTML_Template_Sigma {

	function __construct($tfile, $tdir, $cdir=null) {
		parent::__construct($tdir, $cdir);
		$res = $this->loadTemplateFile($tfile);
		if (\SIGMA_OK !== $res) {
			errorLog('error loading template file: ' . $res);
		}
	}

	// Set current block and variables in single step; parses by default.
	function parseBlock($block='__global__', $ar_vars=[], $f_parse=true) {
		$this->setCurrentBlock($block);
		if ($ar_vars) { $this->setVariable((array)$ar_vars); }
		if ($f_parse) { $this->parseCurrentBlock(); }
	}

	// Sets block variables without parsing.
	function setBlockVariables($ar_vars, $block='__global__') {
		$this->parseBlock($block, (array)$ar_vars, false);
	}

}
