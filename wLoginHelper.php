<?php
namespace wCommon;
/**
* Helper class for login and logout of users.
*
* @copyright 2018 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2018-07-04
*
* @version 1.0
*
*/

require_once 'wCommon/wStandard.php';

/** Helper class for user login and logout. Clients create a subclass and override the public methods indicated below. */
class wLoginHelper {

	const SKEY_LOCATION = 'url-target';
	const SKEY_USER = 'user-name';
	const SKEY_LAST_LOGIN_DATE = 'user-last-login-date';

	const COOKIE_IDEE = 'signin';
	const COOKIE_WEEKS = 2;

	//
	// !Subclasses to override.
	//

	/** Return current user. */
	function getCurrentUser() {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Set current user. */
	function setCurrentUser($user) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return flag indicating if user is disabled. */
	function isDisabledUser($user) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return true or false if user has permissions. */
	function hasPermissionUser($user) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return date of user login. */
	function getUserLoginDate($user) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Set date of user login. */
	function setUserLoginDate($user, $date) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return a token to identify user. */
	function tokenForUser($user) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return user for token. */
	function userForToken($token) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return cookie for user. */
	function cookieForUser($user) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Return user for cookie. */
	function userForCookie($cookie) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	/** Remove objects tied to cookie. */
	function deleteCookie($cookie) {
		throw new \Exception('Function `' . __FUNCTION__ . '` to be overridden by subclass of wLoginHelper');
	}

	//
	// !Confirm user from session or cookie
	//

	/** Confirm there is a user in session or cookie. If c’è, check for disabled and for permissions, register current user, and return true. If any of that fails, unregister current user, clear session and cookie and return false. */
	function confirmUser() {
		do {
			if (!($user = $this->userFromSession()) && !($user = $this->userFromCookie())) { break; }
			// Confirm user is active
			if ($this->isDisabledUser($user)) {
				errorLog("$user is disabled");
				break;
			}
			// Confirm permissions.
			if (!$this->hasPermissionUser($user)) { break; }
			// Generate message about last login.
			$this->triggerLastLoginMessage();
			// Stash user in session.
			$this->stashUserInSession($user);
			// Notify of user login.
			$this->setCurrentUser($user);
			return true;
		} while (0);
		$this->stashLocation();
		$this->clearUserSession();
		$this->clearUserCookie();
		$this->setCurrentUser(null);
		return false;
	}

	//
	// !Session methods
	//

	/** Stash token for user in session so user remains "logged in". */
	function stashUserInSession($user) {
		$_SESSION[self::SKEY_USER] = $this->tokenForUser($user);
	}

	function clearUserSession() {
		unset($_SESSION[self::SKEY_USER]);
	}

	/** Return user previously stashed in session with `stashUserInSession()`, or null if non c’è. */
	function userFromSession() {
		do try {
			// Fetch the account from the session.
			if (!($token = $_SESSION[self::SKEY_USER])) { break; }
			if (!($user = $this->userForToken($token))) {
				errorLog("No user retrieved from session with token {$token}.");
				break;
			}
			return $user;
		} catch (Exception $e) { errorLog("Exception fetching user {$user} with token {$token} from session.", $e); } while(0);
		// If we reach this point we had an error validating user.
		return null;
	}

	//
	// !Login date and messages
	//

	/** If a login date has been stashed in session, generate a confirmation message to user when she last logged in. */
	function triggerLastLoginMessage() {
		// Create a message about the last time logged in.
		if ($prior = $_SESSION[self::SKEY_LAST_LOGIN_DATE]) {
			unset($_SESSION[self::SKEY_LAST_LOGIN_DATE]);
			wTemplate::addConfirmMessage('You last logged in ' . getDateAndIntervalDisplay($prior) . '.');
		}
	}

	/** Save current login date in session, and set user login date to now. Meant to be called by `login.php` on successful account login. */
	function resetLoginDate($user) {
		$_SESSION[self::SKEY_LAST_LOGIN_DATE] = $this->getUserLoginDate($user);
		$this->setUserLoginDate($user, dbDate());
	}

	//
	// !Cookie methods
	//

	static function doNotTrack() { return false; } //TODO: need to figure out what we want to do with this. For example, login could have a "remember me on this device" checkbox.

	/** Create a cookie, if non c’è, for user to stay logged in for a while. */
	function stashUserInCookie($user) {
		if (self::doNotTrack()) { return; }
		if ($_COOKIE[self::COOKIE_IDEE]) { return; }
		if (!($cookie = $this->cookieForUser($user))) { return; }
		$expire = time() + 60*60*24*7*self::COOKIE_WEEKS;
		setcookie(self::COOKIE_IDEE, $cookie, $expire, '/', getHost(), true);
	}

	/** Clear account cookie. */
	function clearUserCookie() {
		if ($cookie = $_COOKIE[self::COOKIE_IDEE]) { $this->deleteCookie($cookie); }
		unset($_COOKIE[self::COOKIE_IDEE]);
		setcookie(self::COOKIE_IDEE, '', time()-3600, '/', getHost(), true); //NOTE: Must include same parameters from when cookie was originally created.
	}

	/** Return user previously stashed in cookie with `stashUserInCookie()`, or null if non c’è. */
	function userFromCookie() {
		do try {
			// Fetch cookie from session.
			if (!($cookie = $_COOKIE[self::COOKIE_IDEE])) { break; }
			// Find user associated with cookie.
			if (!($user = $this->userForCookie($cookie))) {
				//errorLog("No user fetched for cookie `{$cookie}`.");
				break;
			}
			// Make sure it has been within xxx weeks since account last did a legit signin.
			$cutoff = dbDate(strtotime(self::COOKIE_WEEKS . ' weeks ago'));
			$loginDate = $this->getUserLoginDate($user);
			if (!$loginDate || $loginDate < $cutoff) {
				//errorLog('User last signin older than ' . self::COOKIE_WEEKS . ' weeks');
				break;
			}
			return $user;
		} catch (Exception $e) { errorLog("Exception fetching user `{$user}` from cookie `{$cookie}`.", $e); } while(0);
		// No valid user found from the cookie.
		return null;
	}

//
// !Saved location
//

	function stashLocation() {
		$_SESSION[self::SKEY_LOCATION] = $_SERVER['REQUEST_URI'];
	}

	function popStashedLocation() {
		$loc = $_SESSION[self::SKEY_LOCATION];
		unset($_SESSION[self::SKEY_LOCATION]);
		return ( $loc ? $loc : '/' );
	}

}
