<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2019-06-07

section : Introduction

	Class for finding and replacing custom HTML tags.
*/

/*
section : TagReplacer class

*/

class TagReplacer {

	protected $tag;

	function __construct($tag) {
		$this->tag = $tag;
	}

	// Replace $target text. Subclasses to override.
	protected function replace($target, $params) {
		throw new Exception('Subclass must override TagReplacer::replace()');
	}

	// Return any parameters in open tag at $pos before closing brace at $close.
	protected function getParams($str, $pos, $close, $tag) {
		$start = $pos + strlen($tag);
		if ($start == $close) { return []; }
		$params = [];
		$target = substr($str, $start, $close - $start);
		foreach (explode(' ', $target) as $item) {
			list($key, $val) = explode('=', trim($item));
			if ($val[0] != '"' || $val[-1] != '"') { continue; }
			$params[$key] = substr($val, 1, strlen($val-2));
		}
		return $params;
	}

	// Repeatedly scan for and replace text surrounded by $this->tag.
	function replaceTags($str) {
		$open_tag = "<!--{$this->tag}";
		$close_tag = "</{$this->tag}-->";
		while (false !== ($pos = strpos($str, $open_tag))) {
			// Find any params.
			$close_brace = strpos($str, '>', $pos + strlen($open_tag));
			if (false === $close_brace) { break; }
			$params = $this->getParams($str, $pos, $close_brace, $open_tag);
			// Find the closing tag and process the text inbetween.
			$close = strpos($str, $close_tag, $close_brace);
			if (false === $close) { break; }
			$target = substr(
				$str,
				$close_brace + 1,
				$close - ($close_brace + 1)
			);
			$elem = $this->replace($target);
			$str = substr_replace(
				$str,
				$elem,
				$pos,
				$close + strlen($close_tag) - $pos
			);
		}
		return $str;
	}

}
