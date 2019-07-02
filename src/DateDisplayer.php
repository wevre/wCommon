<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2019-06-07

section : Introduction

	Helper class for displaying dates and times.
*/

/*
section : DateDisplayer class

	Initialize with a numeric timestamp, or a string date.

	For display, uses class CLS_AMPM which subclasses can override if they don't
	want to use the default: 'ampm';
*/

class DateDisplayer {

	const CLS_AMPM = 'ampm';

	function __construct($timestamp=null, $sep='&nbsp;') {
		$this->timestamp = time();
		if (is_string($timestamp)) { $this->timestamp = strtotime($timestamp); }
		else if (is_numeric($timestamp)) { $this->timestamp = $timestamp; }
		$this->sep = $sep;
	}


	// Return date formatted as "7:45 <span class="ampm">pm</span> Mon 3 Apr
	// 1996".
	function getTimeDateDisplay() {
		return $this->getTimeDisplay() . ' ' . $this->getDateDisplay();
	}

	// Return time formatted with small caps am/pm, no minutes if ':00', and
	// 'noon' or 'midnight' if applicable.
	function getTimeDisplay() {
		$test24h = date('H:i', $this->timestamp);
		if ($test24h == '12:00') { return 'noon'; }
		else if ($test24h == '00:00') { return 'midnight'; }
		$test = date('g:i', $this->timestamp);
		return implode([
			( '00' == substr($test, -2) ?  substr($test, 0, -3) : $test ),
			$this->sep,
			'<span class="' . static::CLS_AMPM . '">',
			date('a', $this->timestamp),
			'</span>',
		]);
	}

	// Return date formatted as "Mon 3 Jun 1997". If time is 00:00, return prior
	// day.
	function getDateDisplay() {
		$timestamp = $this->timestamp;
		if (date('H:i', $timestamp) == '00:00') {
			$timestamp = strtotime('-1 day', $timestamp);
		}
		return date('D j M Y', $timestamp);
	}

	// Return date in standard database format: "2016-03-16 14:03:23".
	function dbDate($fmt='Y-m-d H:i:s') {
		return date($fmt, $this->timestamp);
	}

	// Return a string spelling out time between $this->timestamp and now,
	// rounding to the nearest year, month, week, day, etc.
	function getTimeIntervalDisplay() {
		$then = new \DateTime(); $then->setTimestamp($this->timestamp);
		$now = new \DateTime();
		$interval = $now->diff($then);
		$seconds = abs($this->timestamp - $now->getTimestamp());
		$string = '';
		if ($interval->y > 1) { $string = sprintf('%d years', $interval->y); }
		else if (($months = $interval->m+12*$interval->y) > 2) {
			$string = sprintf('%d months', $months);
		}
		else if ($interval->days > 13) {
			$string = sprintf('%d weeks', $interval->days/7);
		}
		else if ($interval->days > 1) {
			$string = sprintf('%d days', $interval->days);
		}
		else if ($seconds >= 7200) {
			$string = sprintf('%d hours', (int)($seconds/3600));
		}
		else if ($seconds >= 120) {
			$string = sprintf('%d minutes', (int)($seconds/60));
		}
		else if ($seconds > 1) { $string = sprintf('%d seconds', $seconds); }
		else { return 'now'; }
		return ( $interval->invert ? $string . ' ago' : 'in ' . $string );
	}

	// Return time/date display followed by interval in parens.
	function getDateAndIntervalDisplay() {
		return implode([
			$this->getTimeDateDisplay(),
			' (',
			$this->getTimeIntervalDisplay(),
			')',
		]);
	}

}
