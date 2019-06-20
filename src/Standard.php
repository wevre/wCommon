<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2014-03-01
	revised : 2019-06-07
		* Cleaned up comments.
		* Server functions moved to ServerHelper.
		* Dcode moved to Dcode class.

section : Introduction

	General purpose function.
*/

/*
section : Error functions
*/

error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);

function formatException($e) {
	return implode([
		"Exception {$e->getCode()}: {$e->getMessage()} ",
		"(line: {$e->getline()} of {$e->getfile()})",
		PHP_EOL,
		"{$e->getTraceAsString()}",
		PHP_EOL,
	]);
}

// Current path appended to $ident array.
function errorLog($msg, $excp=null, $ident=[]) {
	$ident[] = getURLPath();
	error_log('[' . implode($ident, '-') . "] $msg");
	if ($excp) { error_log(formatException($excp)); }
}

/*
section : Site management functions
*/

// Return subset of $_REQUEST filtered to $keys.
function filterRequest($keys) {
	//return array_intersect_key($_REQUEST, array_flip((array)$keys));
	return array_reduce(
		$keys,
		function($cum, $k) { $cum[$k] = $_REQUEST[$k]; },
		[]
	);
}

// Return path portion of provided URL or of current request.
function getURLPath($url=null) {
	if (!$url) { $url = $_SERVER['REQUEST_URI']; }
	return parse_url($url, PHP_URL_PATH);
}

// Set redirect location and exit. Redirect URL is constructed from $_REQUEST
// $keys, specific $query key/val pairs, $target (which generates a fragment)
// and $path (which defaults to getURLPath().
function bailout($keys=[], $query=[], $target=null, $path=null) {
	$uc = new URLComposer($query, $path);
	$uc->filterRequest($keys);
	if ($target && is_object($target) && method_exists($target, 'getFragment')) {
		$uc->parts[URLComposer::FRAGMENT] = $target->getFragment();
	}
	header('Location: ' . $uc->getURL());
	exit;
}

// Alternate bailout for specifying non-default path.
function bailoutPath($path=null, $keys=[], $query=[], $target=null) {
	bailout($keys, $others, $target, $path);
}

/*
section : Array functions
*/

// Map a function over key/val pairs of an array.
function array_key_map($function, $array) {
	return array_map($function, array_keys((array)$array), (array)$array);
}

// Keep values for specific keys only.
function array_keep($arr, $keys) {
	return array_intersect_key( $arr, array_flip($keys));
}

/*
section : String functions
*/

function randString($len, $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	for ($i=0; $i<$len; $i++) { $ret .= $chars[mt_rand(0, strlen($chars)-1)]; }
	return $ret;
}

// Wrap $item in $prefix and $suffix if $test (as boolean) is true, or if $test
// (as array) contains $item.
function wrap($item, $prefix, $suffix, $test) {
	if (is_array($test) && in_array($item, $test)) {
		return $prefix.$item.$suffix;
	}
	if (!is_array($test) && $test) { return $prefix.$item.$suffix; }
	return $item;
}

// Wrap $item, if not equal to false, in $prefix and $suffix.
function wrapIfCe($item, $prefix, $suffix) {
	return wrap($item, $prefix, $suffix, (bool)$item);
}

// Prefix $item, if not equal to false, with $prefix.
function prefixIfCe($item, $prefix=' ') {
	return wrapIfCe($item, $prefix, '');
}

// Append $item, if not equal to false, with $suffix.
function suffixIfCe($item, $suffix=' ') {
	return wrapIfCe($item, '', $suffix);
}

/*
section : Encoder

	Subclass of TagReplacer that scrambles sensitive text to thwart spammers.
	Tag is 'encode' and supports a single parameter: noscript="noscript text".
*/

class Encoder extends TagReplacer {

	function __construct() {
		parent::__construct('encode');
	}

	// Shift all 95 printable ASCII characters from 32 (space) to 126 (tilde) by
	// a random amount, wrapping around if needed.
	protected function replace($target, $params) {
		$shift = mt_rand(5, 90);
		$shifted = addslashes(preg_replace_callback(
			'/[\x20-\x7E]/',
			function ($m) use ($shift) {
				return chr( 126>=($c=ord($m[0])+$shift) ? $c : $c-95);
			},
			$target
		));
		$unshift = 95 - $shift;
		$coded = implode([
			'<script type="text/javascript">',
			'//<![CDATA[',
			'document.write(',
				"\"{$shifted}\".replace(",
					'/[\x20-\x7E]/g,',
					'function(c){',
						'return String.fromCharCode(',
							"32+(c.charCodeAt(0)+{$unshift}-32)%95",
						');',
					'}',
				')',
			');',
			'//]]>',
			'</script>',
		]);
		if ($params['noscript']) {
			$coded .= '<noscript>' . $params['noscript'] . '</noscript>';
		}
		return $coded;
	}

}
