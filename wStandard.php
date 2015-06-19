<?php
/**
* Handy functions shared between websites.
*
* @copyright 2014-2015 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2014-03-01
*
* @version 1.0
*
*/

// -----------------------------
//! errors, exceptions and error logs

error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);

function formatException($e) {
	return "Exception {$e->getCode()}: {$e->getMessage()} (line: {$e->getline()} of {$e->getfile()})\n{$e->getTraceAsString()}\n";
}

function w_error_log($ident, $type, $msg, $excp=null) { error_log("[" . suffixIfCe($ident, '-') . "$type] $msg"); if ($excp) { error_log(formatException($excp)); } }

// -----------------------------
//! confirming the subdomain and http or https

function w_confirmServer($hostname, $https=false, $domain='www') {
	if ($https) {
		if (!$_SERVER['HTTPS'] || 0!==strpos($_SERVER['SERVER_NAME'], $domain)) { header("Location: https://{$domain}.{$hostname}{$_SERVER['REQUEST_URI']}"); }
	} else {
		if ($_SERVER['HTTPS'] || 0!==strpos($_SERVER['SERVER_NAME'], $domain)) { header("Location: http://{$domain}.{$hostname}{$_SERVER['REQUEST_URI']}"); }
	}
}

// -----------------------------
//! string functions

function rand_str($len, $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	for ($i=0; $i<$len; $i++) { $ret .= $chars[mt_rand(0, strlen($chars)-1)]; }
	return $ret;
}

function truncateString($string, $maxLen=24) {
	return ( strlen($string)>$maxLen ? substr($string, 0, $maxLen) . '&hellip;' : $string );
}

// -----------------------------
//! date functions

function dateOrNone($date, $preps=true) { return ( $date ? getTimeDateDisplay(strtotime($date), $preps) : 'None' ); } // for presenting dates in detail lists on our admin pages

function getTimeDateDisplay($timestamp=null, $preps=true) { // return "at 8 pm on Mon 4 April 2006"
	if (is_null($timestamp)) { $timestamp = time(); }
	$time_part = wrap($preps, getTimeDisplay($timestamp), 'at ', ' on') . ' ';
	if (0==date('G', $timestamp) && 0==date('i', $timestamp) && !$preps) { $time_part = ''; }
	$date_part = getDateDisplay($timestamp);
	return	$time_part . $date_part;
}

function getTimeDisplay($timestamp=null, $sep='&nbsp;') { // returns a time display such as 7 pm or 8:28 am; default is to separate with &nbsp; and :00 won't be displayed // relies on class .ampm
	if (is_null($timestamp)) { $timestamp = time(); }
	return date(( 0==date('i', $timestamp) ? 'g' : 'g:i' ), $timestamp) . $sep . '<span class="ampm">' . date('a', $timestamp) . '</span>';
}

function getDateDisplay($timestamp=null) { // returns a date formated: Mon 3 June
	if (is_null($timestamp)) { $timestamp = time(); }
	return date('l j F Y', $timestamp);
}

function dbDate($timestamp=null, $fmt='Y-m-d H:i:s') {
	if (is_null($timestamp)) { $timestamp = time(); }
	return date($fmt, $timestamp);
}

function getTimeIntervalDisplay($timestamp) {
	$then = new DateTime(); $then->setTimestamp($timestamp);
	$now = new DateTime();
	$interval = $now->diff($then);
	$seconds = abs($timestamp-$now->getTimestamp());
	$string = '';
	if ($interval->y > 1) { $string = sprintf('%d years', $interval->y); }
	else if (($months = $interval->m+12*$interval->y) > 2) { $string = sprintf('%d months', $months); }
	else if ($interval->days > 13) { $string = sprintf('%d weeks', $interval->days/7); }
	else if ($interval->days > 1) { $string = sprintf('%d days', $interval->days); }
	else if ($seconds >= 7200) { $string = sprintf('%d hours', (int)($seconds/3600)); }
	else if ($seconds >= 120) { $string = sprintf('%d minutes', (int)($seconds/60));}
	else if ($seconds > 1) { $string = sprintf('%d seconds', $seconds); }
	else { return 'now'; }
	return ( $interval->invert ? $string . ' ago' : 'in ' . $string );
}

function getDateAndIntervalDisplay($datestring) { if (!$datestring) { return ''; } return getTimeDateDisplay($stamp = strtotime($datestring), false) . ' (' . getTimeIntervalDisplay($stamp) . ')'; }

// -----------------------------
//! functions to generate common elements

function areYouSure($item) {
	return 'onclick="javascript:return confirm(\'' . addslashes($item) . '?\')"';
}

function wrap($test, $item, $prefix, $suffix) { // wraps $item in $prefix and $suffix if $test is boolean true, or if $test is an array an $item is in it //NOTE: might need to cast $test to (bool) when calling this function
	if ((is_bool($test) && $test) || (is_array($test) && in_array($item, $test))) { return $prefix.$item.$suffix; }
	else { return $item; }
}

function wrapIfCe($item, $prefix, $suffix) { return wrap((bool)$item, $item, $prefix, $suffix); }

function prefixIfCe($item, $pre=' ') { return ( $item ? $pre : '' ) . $item; } // prepends a string if $item string exists

function suffixIfCe($item, $suff=' ') { return $item . ( $item ? $suff : '' ); } // appends a string if $item exists

function getURLPath($url=null) {
	if (!$url) { $url = $_SERVER['REQUEST_URI']; }
	$parsedUrl = parse_url($url);
	return $parsedUrl['path'];
}

function actionParam($action) { return KEY_ACTION . '=' . $action; }

function keyParam($key, $val) { return $key . '=' . $val; }

function actionLinks($links) { // array of items such as: array('link'=>'', 'display'=>'', 'title'=>'', 'xargs'=>'', ), 'title' is optional and defaults to 'display' //NOTE: creates a sequence of <a> tags which should be wrapped in a parent with class="ibwrap", also makes use of class .hide
	if (!count($links)) { return ''; }
	foreach ($links as $item) {
		if (!$item) { continue; }
		if ($item['link']) {
			if (!$item['title']) { $item['title'] = $item['display']; }
			$string .= wrap(!is_null($item['pre-link']), '<a href="' . $item['link'] . '" title="' . $item['title'] . '"' . ( $item['xargs'] ? ' ' . $item['xargs'] : '' ) . ( $item['hide'] ? ' class="hide"' : '' ) . '>[' . $item['display'] . ']</a>', $item['pre-link'], $item['post-link']);
		} else if ($item['display']) {
			$string .= "<span>{$item['display']}</span>";
		} else if ($item['raw']) {
			$string .= $item['raw'];
		}
	}
	return $string;
}

function displayProps($props) { // displays a list of properties, they will display as a simple <ul> list, but no bullets // assumes .dlist class is defined
	foreach ($props as $name=>$val) { $string .= '<li><span class="fname">' . $name . ':&ensp;</span>' . $val . '</li>'; }
	return '<ul class="dlist">' . $string . '</ul>';
}

function inlineProps($props) { // displays a list of props as inline <p>'s // assumes classes .iprop and .fname are defined
	if (!$props) { return ''; } // short-circuit, allows us to pass an empty array
	foreach ($props as $name=>$val) { $string .= '<p><span class="fname">' . $name . ':</span>&ensp;' . $val . '</p>'; }
	return '<div class="iprop">' . $string . '</div>';
}

function titleWithLinks($title, $links, $force_wrap=false) { // displays a title with action links to the right, all of them as inline-blocks, so title should be wrapped in <h1> or <p> tags // relies on class .ibwrap
	if (!$force_wrap && !$links) { return $title; } // bail out if no links
	return '<div class="ibwrap">' . $title . actionLinks($links) . '</div>';
}

function listOfLinks($links) { // array of items such as: array('link'=>'', 'display'=>'', 'title'=>'', 'xargs'=>'', 'src'=>, ) where 'display' will display first in bold, then an optional image, then an optional 'description'; 'title' (for the <a title=""> and <img alt="">) is optional and will default to 'display'; 'src', if included, will display the image //NOTE: creates a UL list of links with optional images, which are styled with classes .linklist and .llimg
	foreach ($links as $item) {
		if (!$item) { continue; }
		if (!$item['title']) { $item['title'] = $item['display']; }
		$string .= '<li>';
		if ($item['link']) { $string .= '<a href="' . $item['link'] . '" title="' . $item['title'] . '"' . ( $item['xargs'] ? ' ' . $item['xargs'] : '' ) . '>'; }
		$string .= '<p class="rname">' . $item['display'] . '</p>';
		if ($item['src']) { $string .= '<p><img src="' . $item['src'] . '" class="llimg" alt="' . $item['title'] . '" /></p>'; }
		if ($item['description']) { $string .= '<p>' . $item['description'] . '</p>'; }
		if ($item['link']) { $string .= '</a>'; }
		$string .= '</li>';
	}
	return ( $string ? '<ul class="linklist">' . $string . '</ul>' : '' );
}

//
//! Generic function to replace sections delimited by custom tags.
//

function scanTag($string, $open_tag, $close_tag, $replacer) {
	while (false !== ($pos = strpos($string, $open_tag))) {
		// find the closing tag and process the text inbetween
		if (false === ($close = strpos($string, $close_tag, $pos))) { break; }
		$target = substr($string, $pos+strlen($open_tag), $close-($pos+strlen($open_tag)));
		$tag = $replacer($target);
		$string = substr_replace($string, $tag, $pos, $close+strlen($close_tag)-$pos);
	}
	return $string;
}

//
//! Function to scramble sensitive imformation to foil spammers.
//

define('DCODE_OPEN_TAG', '<!--encode>');
define('DCODE_CLOSE_TAG', '</encode-->');

function dcode($string, $addNoScript=true) { // Wrapper function to simplify the dcode call.
	$replacer = function($shifted) {
		$uppShift = mt_rand(3, 23);
		$lowShift = mt_rand(3,23);
		$numShift = mt_rand(2,8);
		$shifted = preg_replace_callback('/[A-Z]/', function ($m) use ($uppShift) { return chr( (90>=($c=ord($m[0])+$uppShift)) ? $c : $c-26 ); }, $shifted);
		$shifted = preg_replace_callback('/[a-z]/', function ($m) use ($lowShift) { return chr( (122>=($c=ord($m[0])+$lowShift)) ? $c : $c-26 ); }, $shifted);
		$shifted = preg_replace_callback('/\d/', function ($m) use ($numShift) { return chr( (57>=($c=ord($m[0])+$numShift)) ? $c : $c-10 ); }, $shifted);
		$coded = '<script type="text/javascript">' . PHP_EOL . '//<![CDATA[' . PHP_EOL . '<!--' . PHP_EOL . 'document.write("'. addslashes($shifted) . '".replace(/[A-Z]/g,function(c){return String.fromCharCode(90>=(c=c.charCodeAt(0)+' . (26-$uppShift) . ')?c:c-26);}).replace(/[a-z]/g,function(c){return String.fromCharCode(122>=(c=c.charCodeAt(0)+' . (26-$lowShift) . ')?c:c-26);}).replace(/\d/g,function(c){return String.fromCharCode(57>=(c=c.charCodeAt(0)+' . (10-$numShift) . ')?c:c-10);}));' . PHP_EOL . '//-->' . PHP_EOL . '//]]>' . PHP_EOL . '</script>';
		if ($addNoScript) { $coded .= '<noscript><span class="bmatch">please enable javascript</span></noscript>'; }
		return $coded;
	}
	return scanTag($string, DCODE_OPEN_TAG, DCODE_CLOSE_TAG, $replacer);
}

//
//! Redirects, in the typical case, from an edit page back to a display page, using parameters present in GET or POST to construct the redirect URL.
//

// Sets the location and exits the PHP script in order to redirect the browser. The assumption is that the base URL is the same as the current URLPath, and the parameters needed to control the specific page are already present in the GET or POST request, and we just need to copy over the ones that are important. Those are specified by key with the -- wait for it -- $keys parameter. If a fragment is desired, you pass that in as an object to the $target parameter, and the `getFragment` function will be called on that object. The `others` parameter is a keyed array of additional URL parameters that are not in GET or POST.
function w_bailout($keys=array(), $others=[], $target=null) {
	if ($target) { $fragment = $target->getFragment(); }
	header('Location: ' . getURLPath() . prefixIfCe(implode("&", array_map(function($k) { return "{$k}={$_REQUEST[$k]}"; }, array_filter($keys, function($k) { return $_REQUEST[$k]; }))), '?') . prefixIfCe(implode('&', array_map(function($k,$v) { return "{$k}={$v}"; }, array_keys($others), $others)), '&') . prefixIfCe($fragment, '#'));
	exit;
}

?>
