<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2019-06-07

section : Introduction

	Helper class for constructing URL's.
*/

/*
section : URLComposer class
*/

class URLComposer {

	public $parts = [];

	const PATH = 'path';
	const QUERY = 'query';
	const SCHEME = 'scheme';
	const HOST = 'host';
	const FRAGMENT = 'fragment';

	function __construct($path=null, $query=[]) {
		$this->parts[self::PATH] = ( $path ? $path : getURLPath() );
		$this->parts[self::QUERY] = $query;
		$this->parts[self::SCHEME] = '';
		$this->parts[self::HOST] = '';
		$this->parts[self::FRAGMENT] = '';
	}

	function setQuery($key, $val) {
		$this->parts[self::QUERY][$key] = $val;
	}

	function addKey($key) {
		$this->parts[self::QUERY][] = $key;
	}

	function getURL() {
		$str_query = http_build_query(
			filterRequest($this->parts[self::QUERY]),
			null,
			'&',
			PHP_QUERY_RFC3986
		);
		return implode([
			suffixIfCe($this->parts[self::SCHEME], '://'),
			$this->parts[self::PATH],
			prefixIfCe($str_query, '?'),
			prefixIfCe($this->parts[self::FRAGMENT], '#'),
		]);
	}

}
