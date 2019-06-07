<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2014-03-04
	revised : 2019-06-06
		* Added class `const`'s.
		* Tweaked how messages are displayed and faded.

section : Introduction

	Subclass TemplateSigma with methods for adding head elements and displaying
	error/confirmation messages.
*/

/*
section : Template class
*/

class Template extends TemplateSigma {

	private const SK_ERROR = 'tmpl-error';
	private const SK_CONFIRM = 'tmpl-confirm';

	// Locations.
	const TMPL_FILE = 'Provide default template filename';
	const TMPL_CACHE_DIR = 'Provide cache directory';
	const TMPL_DIR = 'Provide template directory';

	// Subclasses can override to provide their preferred Composer.
	const CLS_COMPOSER = '\wCommon\HTMLComposer';

	// Blocks, variables, and classes used in sample template.
	const BLK_BODY_ATTR = 'BLK-BODY-ATTR';
	const BLK_HEAD_ELEM = 'BLK-HEAD-ELEM';
	const BLK_MAIN_ITEM = 'BLK-MAIN-ITEM';
	const BLK_MSG = 'BLK-MSG';
	const VAR_HEAD_TITLE = 'HEAD-TITLE';
	const VAR_HEAD_ELEM = 'HEAD-ELEM';
	const VAR_BODY_ATTR = 'BODY-ATTR';
	const VAR_MAIN_ITEM = 'MAIN-ITEM';
	const VAR_MSG = 'MSG';
	const VAR_MSG_ID = 'MSG-ID';
	const CLS_MSG_X = 'msgx';
	const CLS_ERROR = 'error';

	public $cp;

	function __construct($title, $tfile=null, $tdir=null, $cdir=null) {
		if (!$tfile) { $tfile = static::TMPL_FILE; }
		if (!$tdir) { $tdir = static::TMPL_CACHE_DIR; }
		if (!$cdir) { $cdir = static::TMPL_CACHE_DIR; }
		parent::__construct($tfile, $tdir, $cdir);
		// Initialize our composer.
		$cpClass = static::CLS_COMPOSER;
		$this->cp = new $cpClass();
		// Set page title.
		$this->setPageTitle($title);
		// Render any error/confirmation messages.
		$this->renderMessages();
	}

	function show($block='__global__') {
		// Add onloads.
		$this->renderOnloads();
		// Add message fader.
		$this->renderMsgCloseScript();
		// Show.
		parent::show($block);
	}

	function setPageTitle($title) {
		$this->setBlockVariables([ static::VAR_HEAD_TITLE=>$title, ]);
	}

	/*
	section : Header parts
	*/

	protected function parseHeadElem() {
		$this->parseBlock(
			static::BLK_HEAD_ELEM,
			[ static::VAR_HEAD_ELEM=>$this->cp->getHTML(), ]
		);
	}

	function addStyleSheet($sheet) {
		$this->cp->addElement('link', [ 'rel'=>'stylesheet', 'href'=>$sheet, ]);
		$this->parseHeadElem();
	}

	function addInlineStyle($text) {
		$this->cp->addElement('style', [], $text);
		$this->parseHeadElem();
	}

	function addScriptFile($file) {
		$this->cp->addElement('script', [ 'src'=>$file, ]);
		$this->parseHeadElem();
	}


	function addInlineScript($text) {
		$this->cp->addElement('script', [], $text);
		$this->parseHeadElem();
	}

	/*
	section : Onloads
	*/

	// Add onload to cache, it will render when we show template.
	function addOnload($string) {
		// Onloads are declared inside double quotes, escape any internal ones.
		$this->cache_onloads[] = addcslashes($string, '"');
	}

	protected function renderOnloads() {
		if (!$this->cache_onloads) { return; }
		$str_onload = implode([
			'onload="',
			implode(' ', $this->cache_onloads),
			'"',
		]);
		$this->parseBlock(
			static::BLK_BODY_ATTR,
			[ static::VAR_BODY_ATTR=>$str_onload, ]
		);
	}

	/*
	section : Main items
	*/

	function addItem($item) {
		$this->parseBlock(
			static::BLK_MAIN_ITEM,
			[ static::VAR_MAIN_ITEM=>$item, ]
		);
	}

	/*
	section : Error and confirm messages

		Messages are added to queues in $_SESSION and will be rendered on next
		page display.
	*/

	protected static function queueMessage($queue, $msg, $onlyIfEmpty=false) {
		if (!$onlyIfEmpty || 0==count((array)$_SESSION[$queue])) {
			$_SESSION[$queue][] = $msg;
		}
	}

	static function addErrorMessage($msg, $onlyIfEmpty=false) {
		self::queueMessage(self::SK_ERROR, $msg, $onlyIfEmpty);
	}

	static function addConfirmMessage($msg, $onlyIfEmpty=false) {
		self::queueMessage(self::SK_CONFIRM, $msg, $onlyIfEmpty);
	}

	protected function renderMessages() {
		foreach ((array)$_SESSION[self::SK_ERROR] as $msg) {
			$this->composeMessage($msg, static::CLS_ERROR);
		}
		unset($_SESSION[self::SK_ERROR]);
		foreach ((array)$_SESSION[self::SK_CONFIRM] as $msg) {
			$this->composeMessage($msg);
		}
		unset($_SESSION[self::SK_CONFIRM]);
	}

	// Wrap message in a DIV with an automatically generated id. Append to `$msg`
	// a <P> element containing `$close` (text, image, SVG) and 'onclick' call to
	// `msgClose` that fades out wrapping DIV#id.
	protected function composeMessage($msg, $class='', $close='X') {
		$this->ctr_msg_id += 1;
		$this->cp->addCustom($msg);
		$this->cp->beginElement('p', [
			'class'=>static::CLS_MSG_X,
			'onclick'=>"msgClose('{$this->ctr_msg_id}')",
		], $close);
		$this->parseBlock(
			static::BLK_MSG,
			[
				static::VAR_MSG_ID=>$this->ctr_msg_id,
				static::VAR_MSG=>$this->cp->getHTML(),
			]
		);
		$this->flg_needs_fader = true;
	}

	protected function renderMsgCloseScript() {
		if (!$this->flg_needs_fader) { return; }
		$this->addInlineScript(<<<EOT
function msgClose(id) {
	var item = document.getElementById(id);
	if (!item) { return; }
	var next = item.nextSibling;
	if (next) {
		next.style.marginTop = (next.offsetTop - item.offsetTop) + 'px';
		setTimeout(
			function() {
				next.style.transition = 'margin-top 1s';
				next.style.marginTop = 0;
			},
			20
		);
	}
	item.style.display = 'none';
}
EOT
		);
	}

}
