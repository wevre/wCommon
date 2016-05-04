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

//
// !Error functions
//

error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);

/**
* Returns a string with the information from the exception split out and labeled.
* @param Exception $e
*/
function formatException($e) {
	return "Exception {$e->getCode()}: {$e->getMessage()} (line: {$e->getline()} of {$e->getfile()})\n{$e->getTraceAsString()}\n";
}

/**
* Prints error information to the standard error log.
* The type of message, preceded by the ident value, will be printed in square brackets, followed by the msg.
* If an $excp is passed in, it will be printed after using the formatException() function.
* Typically, projects that use this library will overload this funciton with their own version, supplying default values for some of the parameters.
* For example, here the $ident parameter is supplied by a global variable:
* <code>
* function my_error_log($type, $msg, $excp=null) {
*     global $account;
*     w_error_log( $account, $type, $msg, $excp);
* }
</code>
* @param string $ident
* @param string $type
* @param string $msg
* @param Exception $excp
* @uses formatException()
*/
function w_error_log($ident, $type, $msg, $excp=null) {
	error_log("[" . suffixIfCe($ident, '-') . "$type] $msg");
	if ($excp) { error_log(formatException($excp)); }
}

//
// !Site management functions
//

/**
* Gets the 'path' portion of a URL, either a specific URL passed in as the single parameter; or, by default, the current request URI.
*/
function getURLPath($url=null) {
	if (!$url) { $url = $_SERVER['REQUEST_URI']; }
	$parsedUrl = parse_url($url);
	return $parsedUrl['path'];
}

/**
* Returns the key and value joined by an equals sign, perfect for constructing URL parameters.
*/
function keyParam($key, $val) { return $key . '=' . $val; }

/**
* Confirms the requested URL and redirects if necessary.
* Very useful to enforce HTTP or HTTPS.
* Typically, projects will overload this function with their own version, supplying default values for one or more parameters.
*/
function w_confirmServer($hostname, $https=false, $domain='www') {
	if ($https) {
		if (!$_SERVER['HTTPS'] || 0!==strpos($_SERVER['SERVER_NAME'], $domain)) { header("Location: https://{$domain}.{$hostname}{$_SERVER['REQUEST_URI']}"); }
	} else {
		if ($_SERVER['HTTPS'] || 0!==strpos($_SERVER['SERVER_NAME'], $domain)) { header("Location: http://{$domain}.{$hostname}{$_SERVER['REQUEST_URI']}"); }
	}
}

/**
* Sets the location and exits the script to redirect the browser.
* Typically used on scripts that handle both GET and POST and need to redirect between them.
* The baseURL will match the current URLPath, and extra URL parameters will come (usually) from the existing GET or POST request.
* Additional parameters, if needed, are specified with $others as an associative array in the form param_name=>param_value.
* @param array $keys list of parameters currently present in $_REQUEST that will be copied to the redirect URL
* @param array $others associative array of parameters not present in $_REQUEST to be added to the redirect URL
* @param object $target an object that responds to getFragment() to add a fragment to the redirect URL
* @uses getURLPath()
* @uses prefixIfCe()
* @uses keyParam()
*/
function w_bailout($keys=[], $others=[], $target=null) {
	if ($target && is_object($target) && method_exists($target, 'getFragment')) { $fragment = $target->getFragment(); }
	header('Location: ' . getURLPath() . prefixIfCe(implode("&", array_map(function($k) { return "{$k}={$_REQUEST[$k]}"; }, array_filter($keys, function($k) { return $_REQUEST[$k]; }))), '?') . prefixIfCe(implode('&', array_map('keyParam', array_keys($others), $others)), '&') . prefixIfCe($fragment, '#'));
	exit;
}

//
// !String functions
//

/**
* Returns a random string of length $len.
* @param int $len
* @param string $chars characters to use, defaults to the uppercase letters
*/
function rand_str($len, $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	for ($i=0; $i<$len; $i++) { $ret .= $chars[mt_rand(0, strlen($chars)-1)]; }
	return $ret;
}

/**
* Truncates a too-long string, replacing the chopped-off tail with ellipses (using &hellip;).
*/
function truncateString($string, $maxLen=24) {
	return ( strlen($string)>$maxLen ? substr($string, 0, $maxLen) . '&hellip;' : $string );
}

/**
* Wraps $item in $prefix and $suffix if $test is boolean true, or if $test is an array and $item is in it.
*/
function wrap($test, $item, $prefix, $suffix) {
	if ((is_bool($test) && $test) || (is_array($test) && in_array($item, $test))) { return $prefix.$item.$suffix; }
	else { return $item; }
}

/**
* Wraps a string, if it is not empty, with a specified prefix and suffix.
* 'Ce' comes from Italian c’è which roughly means "exists". It is pronounced "cheh".
* @param string $item the string to wrap if it is not empty
* @param string $prefix the prefix to prepend
* @param string $suffix the suffix to append
*/
function wrapIfCe($item, $prefix, $suffix) { return wrap((bool)$item, $item, $prefix, $suffix); }

/**
* Prepends a string, if it is not empty, with a space or other character.
* @param string $item the string to prepend to if it is not empty
* @param string $pre the prefix to use, defaults to a space
*/
function prefixIfCe($item, $pre=' ') { return ( $item ? $pre : '' ) . $item; }

/**
* Appends a string, if it is not empty, with a space or other character.
* @param string $item the string to append to if it is not empty
* @param string $suff the suffix to use, defaults to a space
*/
function suffixIfCe($item, $suff=' ') { return $item . ( $item ? $suff : '' ); }

//
// !Date functions
//

/**
* Returns the date formatted into the phrase: "at 7:54 PM on Monday 3 April 1996".
* @param int $timestamp integer timestamp such as that returned by strtotime(), if null it is set to time()
* @param bool $preps flag indicating whether or not to include prepositions around the time portion
* @uses getTimeDisplay(), getDateDisplay() to format the time and date portions, respectively
*/
function getTimeDateDisplay($timestamp=null) {
	if (is_null($timestamp)) { $timestamp = time(); }
	return getTimeDisplay($timestamp) . ' ' . getDateDisplay($timestamp);
}

/**
* Returns the time formatted with am/pm in small caps.
* Won't include the minutes if they are zero. So 7:00 prints as 7, but 6:52 will print with the minutes.
* Uses a span element and the class "ampm" to format the meridiem. Projects using this should have a class defined in CSS:
* <code>
* .ampm { font-variant: small-caps; }
* </code>
*/
function getTimeDisplay($timestamp=null, $sep='&nbsp;') {
	if (is_null($timestamp)) { $timestamp = time(); }
	$test = date('g:i', $timestamp);
	if ($test == '12:00') { return 'noon'; }
	else if ($test == '00:00') { return 'midnight'; }
	return ( '00' == substr($test, 3) ?  substr($test, 0, 3) : $test ) . $sep . '<span class="ampm">' . date('a', $timestamp) . '</span>';
}

/**
* Returns the date formatted as: "Monday 3 June 1997".
* The format string is "l j F Y".
* @param int $timestamp integer timestamp such as that returned by strtotime(), if null it is set to time()
*/
function getDateDisplay($timestamp=null) {
	if (is_null($timestamp)) { $timestamp = time(); }
	return date('l j F Y', $timestamp);
}

/**
* Returns a date in standard database format: 2016-03-16 14:03:23.
* @param int $timestamp integer timestamp such as that returned by strtotime(), defaults to `null` which will be replaced with the current time()
* @param string $fmt date format string to use, defaults to 'Y-m-d H:i:s'
*/
function dbDate($timestamp=null, $fmt='Y-m-d H:i:s') {
	if (is_null($timestamp)) { $timestamp = time(); }
	return date($fmt, $timestamp);
}

/**
* Returns a string explaining the time between the $timestamp and now.
* The gap is described in units (years, months, weeks, days, etc.) that are the best match for the size of the gap.
* Simplicity is preferred over precision. So, for instance, a $timestamp from 7 years, 3 months and 2 days ago will return "7 years ago".
* Smaller quantities are preferred, so it won't return "25 days", but instead will return "3 weeks"; and "12 weeks" will turn into "3 months".
* @param int $timestamp integer timestamp such as that returned by strtotime()
* @todo Should include a second parameter that can override 'now'.
*/
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

/**
* Returns a string with the time date display and the interval: "7:23 Monday 5 December 2006 (4 years ago)".
* @param string $datestring string representation of a date
* @uses getTimeDateDisplay(), getTimeIntervalDisplay()
* @todo Should include a default parameter to control inclusion of prepositions. Currently they are forced off.
*/
function getDateAndIntervalDisplay($datestring) { if (!$datestring) { return ''; } return getTimeDateDisplay($stamp = strtotime($datestring), false) . ' (' . getTimeIntervalDisplay($stamp) . ')'; }


//
// !Array functions
//

/**
* Maps a function over the key-value pairs of an array
* @param callable $function a function that takes two parameters, the key and the value
* @param array $array the array to map
*/
function array_key_map($function, $array) {
	return array_map($function, array_keys($array), $array);
}

// -----------------------------
// !Functions for common web page elements

/**
*
*/
function areYouSure($item) {
	return 'onclick="javascript:return confirm(\'' . addslashes($item) . '?\')"';
}

/**
*
*/
function actionLinks($links) { // array of items such as: array('link'=>'', 'display'=>'', 'title'=>'', 'xargs'=>'', ), 'title' is optional and defaults to 'display' //NOTE: creates a sequence of <a> elements which should be wrapped in a parent with class="ibwrap", also makes use of class .hide
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

/**
*
*/
function displayProps($props) { // displays a list of properties, they will display as a simple <ul> list, but no bullets // assumes .dlist class is defined
	foreach ($props as $name=>$val) { $string .= '<li><span class="fname">' . $name . ':&ensp;</span>' . $val . '</li>'; }
	return '<ul class="dlist">' . $string . '</ul>';
}

/**
*
*/
function inlineProps($props) { // displays a list of props as inline <p>'s // assumes classes .iprop and .fname are defined
	if (!$props) { return ''; } // short-circuit, allows us to pass an empty array
	foreach ($props as $name=>$val) { $string .= '<p><span class="fname">' . $name . ':</span>&ensp;' . $val . '</p>'; }
	return '<div class="iprop">' . $string . '</div>';
}

/**
*
*/
function titleWithLinks($title, $links, $force_wrap=false) { // displays a title with action links to the right, all of them as inline-blocks, so title should be wrapped in <h1> or <p> elements // relies on class .ibwrap
	if (!$force_wrap && !$links) { return $title; } // bail out if no links
	return '<div class="ibwrap">' . $title . actionLinks($links) . '</div>';
}

/**
*
*/
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
// !Custom HTML element processing
//

/**
* Repeatedly searches text for an element with custom opening and closing tags, and then calls the $replace function to substitute the text.
* The "tags" will typically look like custom HTML tags, but can technically be any text.
* The $replacer function should take a single string argument, which will be the text in between the opening and closing tags.
* The tags are not passed to $replacer. The result returned by $replacer will replace the original tags and content.
* @param string $string the text to search
* @param string $open_tag the custom opening tag
* @param string $end_tag the custom closing tag
* @param callable $replacer a function to replace the found text
*/
function scanElement($string, $open_tag, $close_tag, $replacer) {
	while (false !== ($pos = strpos($string, $open_tag))) {
		// Find the closing tag and process the text inbetween.
		if (false === ($close = strpos($string, $close_tag, $pos))) { break; }
		$target = substr($string, $pos+strlen($open_tag), $close-($pos+strlen($open_tag)));
		$elem = $replacer($target);
		$string = substr_replace($string, $elem, $pos, $close+strlen($close_tag)-$pos);
	}
	return $string;
}

/**
* Constant that defines the opening tag for a DCODE block.
*/
const DCODE_OPEN_TAG = '<!--encode>';

/**
* Constant that defines the closing tag for a DCODE block.
*/
const DCODE_CLOSE_TAG = '</encode-->';

/**
* Replaces text in a string with a scrambled version and a javascript function to descramble it.
* The string can contain multiple, non-nested instances of sensitive text surrounded by the DCODE OPEN and DCODE CLOSE tags, each instance will be replaced.
* @param string $string the string which contains special tags to replace with the scrambler
* @param bool $addNoScript flag to control whether additional <noscript> element is added
* @uses scanElement()
*/
function dcode($string, $addNoScript=true) {
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
	};
	return scanElement($string, DCODE_OPEN_TAG, DCODE_CLOSE_TAG, $replacer);
}

?>
