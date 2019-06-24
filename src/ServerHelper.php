<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2019-06-07

section : Introduction

	Helper class for interacting with server and storing client-specific server
	values, such as site name and postmaster.
*/

/*
section : ServerHelper class
*/

class ServerHelper {

	// Sublcass and provide compile-time defaults. Set run-time values in
	// constructor.
	protected $SUBDOMAIN = ''; // constructing and validating URL's
	protected $SITECODE = ''; // emails, hashes, and server paths
	protected $SITENAME = ''; // page titles
	protected $HOSTNAME = ''; // constructing and validating URL's
	protected $FILEPATH = ''; // server paths
	protected $POSTMASTER = ''; // sending emails

	protected static $shared;

	static function shared() {
		if (!self::$shared) {
			$class = get_called_class();
			self::$shared = new $class();
		}
		return self::$shared;
	}

	function subdomain() { return $this->SUBDOMAIN; }
	function sitecode() { return $this->SITECODE; }
	function sitename() { return $this->SITENAME; }
	function hostname() { return $this->HOSTNAME; }
	function filepath() { return $this->FILEPATH; }
	function postmaster() { return $this->POSTMASTER; }

	// Redirect if not HTTPS or domain doesn't match.
	function confirmServer() {
		do {
			if ($_SERVER['HTTPS']) { break; }
			$domain = $this->SUBDOMAIN;
			if (0 === strpos($_SERVER['SERVER_NAME'], $domain)) { break; }
			$hostname = $this->HOSTNAME;
			$request = $_SERVER['REQUEST_URI'];
			header("Location: https://{$domain}.{$hostname}{$request}");
			exit;
		} while (0);
	}

	function isStageRegion($domain='stage') {
		return 0===strpos($this->SUBDOMAIN, $domain);
	}

	function getHost() {
		return $this->SUBDOMAIN . '.' . $this->HOSTNAME;
	}

	function sendEmail($message, $headers) {
		$lc_headers = array_change_key_case($headers);
		if (!array_key_exists('date', $lc_headers)) {
			$headers['Date'] = date('r');
		}
		if (!array_key_exists('from', $lc_headers)) {
			$headers['From'] = $this->POSTMASTER;
		}
		if (!array_key_exists('sender', $lc_headers)) {
			$headers['Sender'] = $this->POSTMASTER;
		}
		if ($this->isStageRegion()) {
			$origToKey = 'X-' . $this->$SITECODE . '-Original-To';
			$headers[$origToKey] = $headers['To'];
			$headers['To'] = $this->POSTMASTER; // Override 'To' in stage mode.
		}
		if (!array_key_exists('content-type', $lc_headers)) {
			$headers['Content-Type'] = 'text/plain; charset=ISO-8859-1';
		}
		if (!array_key_exists('mime-version', $lc_headers)) {
			$headers['MIME-Version'] = '1.0';
		}
		// NOTE: Flag -f sets the envelope sender. Without this, the envelope
		// sender becomes the user running the script (e.g., "www-data"), which is
		// fine for delivery, and maybe fine for replies, but it is definitely NOT
		// fine for bounces, which disappear.
		$parsed = \mailparse_rfc822_parse_addresses($headers['From']);
		if ($parsed) { $envelope_sender = $parsed[0]['address']; }
		else { $envelope_sender = static::POSTMASTER; }
		$smtp = \Mail::factory('mail', '-f' . $envelope_sender);
		$res = $smtp->send($headers['To'], $headers, $message);
		if (\PEAR::isError($res)) {
			throw new \Exception(implode([
				"Error sending email `{$headers['Subject']}`",
				" to `{$headers['To']}`",
				" from `{$headers['From']}`",
				'SMTP error: ' . $res->getMessage(),
			]));
		}
	}

	static function isPostAction($action) {
		return $action == $_POST[FormBuilder::KEY_ACTION];
	}

	static function isGetAction($action) {
		return $action == $_GET[FormBuilder::KEY_ACTION];
	}

}
