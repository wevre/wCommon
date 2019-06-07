<?php
namespace wCommon;
/*
project : wCommon
	author : Mike Weaver
	created : 2018-07-04
	revised : 2019-06-06
		* Comment cleanup.

section : Introduction

	Helper class for logging in and out of users.
*/

/*
section : LoginHelper class
*/

class LHException extends \Exception {

	function __construct($fun) {
		parent::__construct(implode([
			'Function `',
			$fun,
			'` to be overridden by subclass of LoginHelper',
		]));
	}

}

class LoginHelper {

	private const SK_LOCATION = 'url-target';
	private const SK_USER = 'user-name';
	private const SK_LAST_LOGIN_DATE = 'user-last-login-date';

	const COOKIE_IDEE = 'signin';
	const COOKIE_WEEKS = 2;

	protected $host = '';

	function __construct($host) {
		$this->host = $host;
	}

	/*
	section : Subclasses to override
	*/

	function getCurrentUser() { throw new LHException(__FUNCTION__); }

	function setCurrentUser($user) { throw new LHException(__FUNCTION__); }

	function isDisabledUser($user) { throw new LHException(__FUNCTION__); }

	function hasPermissionUser($user) { throw new LHException(__FUNCTION__); }

	function getUserLoginDate($user) { throw new LHException(__FUNCTION__); }

	function setUserLoginDate($user, $date) {
		throw new LHException(__FUNCTION__);
	}

	function tokenForUser($user) { throw new LHException(__FUNCTION__); }

	function userForToken($token) { throw new LHException(__FUNCTION__); }

	function cookieForUser($user) { throw new LHException(__FUNCTION__); }

	function userForCookie($cookie) { throw new LHException(__FUNCTION__); }

	function deleteCookie($cookie) { throw new LHException(__FUNCTION__); }

	/*
	section : Public methods
	*/

	// Confirm there is a user in session or cookie. If c’è, check for disabled
	// and for permissions, register current user, and return true. If any of
	// that fails, unregister current user, clear session and cookie and return
	// false.
	function confirmUser() {
		do {
			$user = $this->userFromSession() or $user = $this->userFromCookie();
			if (!$user) { break; }
			if ($this->isDisabledUser($user)) {
				errorLog("{$user} is disabled");
				break;
			}
			if (!$this->hasPermissionUser($user)) { break; }
			$this->triggerLastLoginMessage();
			$this->stashUserInSession($user);
			$this->setCurrentUser($user);
			return true;
		} while (0);
		$this->stashLocation();
		$this->clearUserSession();
		$this->clearUserCookie();
		$this->setCurrentUser(null);
		return false;
	}

	// Stash user in session and cookie and set Location header.
	function onSuccessfulLogin($user) {
		$lh->setCurrentUser($user);
		$lh->stashUserInSession($user);
		$lh->stashUserInCookie($user);
		$lh->resetLoginDate($user);
		header('Location: ' . $this->popStashedLocation());
	}

	// Clear user from session and cookie and set location header.
	function logout($log='/login') {
		$this->clearUserSession();
		$this->clearUserCookie();
		header('Location: ' . $loc);
	}

	/*
	section : Session methods
	*/

	protected function stashUserInSession($user) {
		$_SESSION[self::SK_USER] = $this->tokenForUser($user);
	}

	protected function clearUserSession() {
		unset($_SESSION[self::SK_USER]);
	}

	protected function userFromSession() {
		do try {
			// Fetch the account from the session.
			$token = $_SESSION[self::SK_USER];
			if (!$token) { break; }
			$user = $this->userForToken($token);
			if (!$user) {
				errorLog("No user for {$token}.");
				break;
			}
			return $user;
		} catch (Exception $e) {
			errorLog("{$user} :: {$token}", $e);
		} while(0);
		// If we reach this point we had an error validating user.
		return null;
	}

	/*
	section : Login date and messages
	*/

	protected const CLS_TEMPLATE = '\wCommon\Template';

	protected function triggerLastLoginMessage() {
		// Create a message about the last time logged in.
		$prior = $_SESSION[self::SK_LAST_LOGIN_DATE];
		if (!$prior) { return; }
		unset($_SESSION[self::SK_LAST_LOGIN_DATE]);
		call_user_func(
			[ static::CLS_TEMPLATE, 'addConfirmMessage' ],
			'You last logged in ' . getDateAndIntervalDisplay($prior) . '.'
		);
	}

	protected function resetLoginDate($user) {
		$_SESSION[self::SK_LAST_LOGIN_DATE] = $this->getUserLoginDate($user);
		$this->setUserLoginDate($user, dbDate());
	}

	/*
	section : Cookie methods
	*/

	//TODO: need to figure out what we want to do with this. For example, login
	//could have a "remember me on this device" checkbox.
	static function doNotTrack() { return false; }

	function stashUserInCookie($user) {
		if (self::doNotTrack()) { return; }
		if ($_COOKIE[self::COOKIE_IDEE]) { return; }
		$cookie = $this->cookieForUser($user);
		if (!$cookie) { return; }
		$expire = time() + 60*60*24*7*self::COOKIE_WEEKS;
		setcookie(self::COOKIE_IDEE, $cookie, $expire, '/', $this->host, true);
	}

	protected function clearUserCookie() {
		$cookie = $_COOKIE[self::COOKIE_IDEE];
		if ($cookie) { $this->deleteCookie($cookie); }
		unset($_COOKIE[self::COOKIE_IDEE]);
		setcookie(self::COOKIE_IDEE, '', time()-3600, '/', $this->host, true);
	}

	// Return user previously stashed in cookie with `stashUserInCookie()`, or
	// null if non c’è.
	protected function userFromCookie() {
		do try {
			$cookie = $_COOKIE[self::COOKIE_IDEE];
			if (!$cookie) { break; }
			$user = $this->userForCookie($cookie);
			if (!$user) { break; }
			// Confirm within COOKIE_WEEKS since last login.
			$cutoff = dbDate(strtotime(self::COOKIE_WEEKS . ' weeks ago'));
			$loginDate = $this->getUserLoginDate($user);
			if (!$loginDate || $loginDate < $cutoff) { break; }
			return $user;
		} catch (Exception $e) {
			errorLog("Fetching {$user} from {$cookie}.", $e);
		} while(0);
		return null;
	}

	/*
	section : Saved location
	*/

	protected function stashLocation() {
		$_SESSION[self::SK_LOCATION] = $_SERVER['REQUEST_URI'];
	}

	protected function popStashedLocation() {
		$loc = $_SESSION[self::SK_LOCATION];
		unset($_SESSION[self::SK_LOCATION]);
		return ( $loc ? $loc : '/' );
	}

}
