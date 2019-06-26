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

	const COOKIE_IDEE = 'signin';
	const COOKIE_WEEKS = 2;

	public static $loginError = null;

	protected $user = null;

	function __construct($user) {
		$this->user = $user;
	}

	/*
	section : Subclasses to override
	*/

	function registerUser() { throw new LHException(__FUNCTION__); }

	static function unregisterUser() {
		throw new LHException(__FUNCTION__);
	}

	function isDisabledUser() { throw new LHException(__FUNCTION__); }

	function hasPermissionUser() { throw new LHException(__FUNCTION__); }

	function getUserLoginDate() { throw new LHException(__FUNCTION__); }

	function setUserLoginDate($date) {
		throw new LHException(__FUNCTION__);
	}

	static function tokenForUser($user) { throw new LHException(__FUNCTION__); }

	static function userForToken($token) { throw new LHException(__FUNCTION__); }

	static function getHost() { throw new LHException(__FUNCTION__); }

	static function cookieForUser($user, $expire) {
		throw new LHException(__FUNCTION__);
	}

	static function userForCookie($cookie) {
		throw new LHException(__FUNCTION__);
	}

	static function deleteCookie($cookie) {
		throw new LHException(__FUNCTION__);
	}

	/*
	section : Public methods
	*/

	// Confirm there is a user in session or cookie. If c’è, check for disabled
	// and for permissions, register current user, and return true. If any of
	// that fails, unregister current user, clear session and cookie and return
	// false.
	static function confirmUser() {
		do {
			$user = static::userFromSession() or $user = static::userFromCookie();
			if (!$user) { break; }
			$class = get_called_class();
			$lh = new $class($user);
			if ($lh->isDisabledUser()) {
				static::$loginError = "LH01 :: {$user} is disabled";
				break;
			}
			if (!$lh->hasPermissionUser()) {
				static::$loginError = "LH02 :: {$user} failed permissions";
				break;
			}
			static::triggerLastLoginMessage();
			$lh->stashUserInSession();
			$lh->registerUser();
			return true;
		} while (0);
		static::stashLocation();
		static::clearUserSession();
		static::clearUserCookie();
		static::unregisterUser();
		return false;
	}

	// Stash user in session and cookie and set Location header.
	function onSuccessfulLogin() {
		$this->registerUser();
		$this->stashUserInSession();
		$this->stashUserInCookie();
		$this->resetLoginDate();
		header('Location: ' . static::popStashedLocation());
	}

	// Clear user from session and cookie and set location header.
	static function logout($loc='/login') {
		static::clearUserSession();
		static::clearUserCookie();
		header('Location: ' . $loc);
	}

	/*
	section : Session methods
	*/

	private const SK_USER = 'user-name';

	protected function stashUserInSession() {
		$_SESSION[self::SK_USER] = static::tokenForUser($this->user);
	}

	protected static function clearUserSession() {
		unset($_SESSION[self::SK_USER]);
	}

	protected function userFromSession() {
		do try {
			// Fetch the account from the session.
			$token = $_SESSION[self::SK_USER];
			if (!$token) { break; }
			$user = static::userForToken($token);
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

	private const SK_LAST_LOGIN_DATE = 'user-last-login-date';

	protected const CLS_TEMPLATE = '\wCommon\Template';

	protected static function triggerLastLoginMessage() {
		// Create a message about the last time logged in.
		$prior = $_SESSION[self::SK_LAST_LOGIN_DATE];
		if (!$prior) { return; }
		unset($_SESSION[self::SK_LAST_LOGIN_DATE]);
		call_user_func(
			[ static::CLS_TEMPLATE, 'addConfirmMessage' ],
			'You last logged in ' . getDateAndIntervalDisplay($prior) . '.'
		);
	}

	protected function resetLoginDate() {
		$_SESSION[self::SK_LAST_LOGIN_DATE] = $this->getUserLoginDate();
		$this->setUserLoginDate(dbDate());
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
		$weeks = self::COOKIE_WEEKS;
		$expire = strtotime("+{$weeks} weeks");
		$cookie = static::cookieForUser($user, $expire);
		if (!$cookie) { return; }
		$host = static::getHost();
		setcookie(self::COOKIE_IDEE, $cookie, $expire, '/', $host, true);
	}

	protected static function clearUserCookie() {
		$cookie = $_COOKIE[self::COOKIE_IDEE];
		if ($cookie) { static::deleteCookie($cookie); }
		unset($_COOKIE[self::COOKIE_IDEE]);
		$host = static::getHost();
		setcookie(self::COOKIE_IDEE, '', time()-3600, '/', $host, true);
	}

	// Return user previously stashed in cookie with `stashUserInCookie()`, or
	// null if non c’è.
	protected function userFromCookie() {
		do try {
			$cookie = $_COOKIE[self::COOKIE_IDEE];
			if (!$cookie) { break; }
			$user = static::userForCookie($cookie);
			if (!$user) { break; }
			// Confirm within COOKIE_WEEKS since last login.
			$weeks = self::COOKIE_WEEKS;
			$cutoff = dbDate(strtotime("-{$weeks} weeks"));
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

	private const SK_LOCATION = 'url-target';

	protected static function stashLocation() {
		$_SESSION[self::SK_LOCATION] = $_SERVER['REQUEST_URI'];
	}

	protected static function popStashedLocation() {
		$loc = $_SESSION[self::SK_LOCATION];
		unset($_SESSION[self::SK_LOCATION]);
		return ( $loc ? $loc : '/' );
	}

}
