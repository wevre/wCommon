<?php
namespace wCommon;
/**
* Represents an account with login capability.
*
* @copyright 2018 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2018-03-12
*
*/

use dStruct\dConnection;

class dsAccount extends dStruct\dStruct {

	const SKEY_URL_TARGET = 'url-target';
	const SKEY_ACCOUNT_IDEE = 'account-idee';
	const SKEY_DATE_PREV_LOGIN = 'date-prev-login';

	const COOKIE_IDEE = 'login';

	const POST_USER = 'user';
	const POST_PSWD = 'pswd';

	static protected $shared_acct;

	//
	// !Field definitions.
	//

	static function selfFieldDefs() {
		return [
			'username'=>dConnection::dCONCAT,
			'password'=>dConnection::dCONCAT,
			'dateCreate'=>dConnection::dDATETIME,
			'dateLogin'=>dConnection::dDATETIME,
			'flagResetPassword'=>dConnection::dBOOL,
			'disabled'=>dConnection::dBOOL,
		];
	}

	static function shouldFetchKey() { return true; }

	//
	// !Shared account
	//

	static function sharedAccount() {
		return self::$shared_acct;
	}

	static function registerSharedAccount($acct) {
		self::$shared_acct = $acct;
	}

	//
	// !Account from POST
	//

	static function getLoginErrorMessage() { return 'Invalid login'; }

	function onSuccessfulLogin() {
		$this->stashInSession();
		$this->resetLoginDate();
		header('Location: ' . self::popStashedLocation());
	}

	static function accountFromPost() {
		$cnxn = dConnection::sharedConnection(); //TODO: will this work?
		do try {
			$cnxn->startTransaction();
			// Validate username and account object.
			$username = strtolower($_POST[self::POST_USER]);
			$gname = get_called_class();
			if (!($account = $cnxn->fetchStructForKey($gname, $username))) { errorLog("No account object retrieved for `$username`"); break; }
			if ($account->username != $username) { errorLog("Account $account username `{$account->username}` does not match POST username `$username`"); break; }
			// Confirm account is active.
			if ($account->disabled) { errorLog("Account $account is disabled."); break; }
			// Confirm password.
			if (!$account->confirmPassword($_POST[self::POST_PSWD])) { errorLog('Password mismatch'); break; }
			//TODO: from DCF code: check if password reset is needed and redirect to password reset page.
			$account->onSuccessfulLogin();
			$cnxn->commitTransaction();
			exit;
		} catch (Exception $e) { wCommon\errorLog("Exception fetching account {$account}", $e); } while(0);
		// If we reach this point we had an error validating the user, return null.
		$cnxn->rollbackTransaction();
		StandardTemplate::addErrorMessage(static::getLoginErrorMessage());
		bailout();
	}

	//
	// !Account stashed in session
	//

	/** Stash account idee in session so account remains "logged in". */
	function stashInSession() {
		$_SESSION[self::SKEY_ACCOUNT_IDEE] = $this->idee;
	}

	static function clearSessionAccount() {
		unset($_SESSION[self::SKEY_ACCOUNT_IDEE]);
	}

	/** Return account previously stashed in session with `stashInSession()`, or null if non c’è. */
	static function accountFromSession() {
		do try {
			// Fetch the account from the session.
			if (!($idee = $_SESSION[self::SKEY_ACCOUNT_IDEE])) { break; }
			$gname = get_called_class();
			if (!($account = Connection::sharedConnection()->fetchStructForIdee($gname, $idee))) {
				errorLog("No account retrieved from session with idee {$idee}.");
				break;
			}
			return $account;
		} catch (Exception $e) { errorLog("Exception fetching account with idee {$idee}.", $e); } while(0);
		//  Error validating account. Return null.
		return null;
	}

	/** If a login date has been stashed in session, generate a confirmation message to user when she last logged in. */
	function triggerPreviousLoginMessage() {
		// Create a message about the last time logged in.
		if ($prior = $_SESSION[self::SKEY_DATE_PREV_LOGIN]) {
			unset($_SESSION[self::SKEY_DATE_PREV_LOGIN]);
			StandardTemplate::addConfirmMessage('You last logged in ' . getDateAndIntervalDisplay($prior) . '.');
		}
	}

	/** Save current login date in session, and set account->loginDate to now. Called by `login.php` on successful account login. */
	function resetLoginDate() {
		$_SESSION[self::SKEY_DATE_PREV_LOGIN] = $this->loginDate;
		$this->dateLogin = dbDate();
	}

//
// !Account stashed in cookie
//

	static function doNotTrack() { return false; }

	/** Create a cookie, if non c’è, for account to stay logged in for a while. */
	function stashInCookie() {
		if (self::doNotTrack()) { return; }
		if ($_COOKIE[self::COOKIE_IDEE]) { return; }
		if (!($cookie = dsCookie::createForAccount($this))) { return; }
		$expire = time() + 60*60*24*7*dsCookie::WEEKS; // Expires in 6 weeks.
		setcookie(self::COOKIE_IDEE,  $cookie->value, $expire, '/', getHost(), true);
	}

	/** Clear account cookie. */
	static function clearAccountCookie() {
		unset($_COOKIE[self::COOKIE_IDEE]);
		setcookie(self::COOKIE_IDEE, '', time()-3600, '/', getHost(), true); //NOTE: Must include same parameters from when cookie was originally created.
	}

	static function accountFromCookie() {
		do try {
			// Fetch cookie from session.
			if (!($cookie = $_COOKIE[self::COOKIE_IDEE])) { break; }
			// Find account associated with cookie.
			if (!($account = dsCookie::accountForCookie($cookie))) {
				errorLog('No account fetched for cookie `' . $cookie . '`.');
				break;
			}
			// Make sure it has been within six weeks since account last did a legit signin.
			$cutoff = dbDate(strtotime(dsCookie::WEEKS . ' weeks ago'));
			if (!$account->dateSignin || $account->dateSignin < $cutoff) {
				clan_error_log('account last signin older than ' . dsCookie::WEEKS . ' weeks');
				break;
			}
			// If the account is already in the session, this method won't be called. If the account is no longer in the session, but still in the cookie, this method will be called once, but afterward the account will be re-stashed in the session. So when we get to this point, it should be when a user's session has expired and they are coming back after some time and the cookie is still valid.
			$account->triggerLastSigninMessage();
			return $account;
		} catch (Exception $e) { clan_error_log('Exception fetching account ' . $idee . ' from session.', $e); } while (0);
		// No valid account found from the cookie.
		return null;
	}

//
// !Saved location
//

	static function stashLocation() {
		$_SESSION[self::SKEY_URL_TARGET] = $_SERVER['REQUEST_URI'];
	}

	static function popStashedLocation() {
		$loc = $_SESSION[self::SKEY_URL_TARGET];
		unset($_SESSION[self::SKEY_URL_TARGET]);
		return ( $loc ? $loc : '/' );
	}


	//
	// !Password
	//

	function confirmPassword($pswd) {
		if (!$pswd) { return false; }
		return password_verify($pswd, $this->password);
	}

	function setPassword($pswd) {
		$this->password = password_hash($pswd, PASSWORD_DEFAULT);
	}

//
// !Hashes
//

	/** Return hash code suitable for use in URL parameters. */
	function getActionHash($date, $action) {
		$elems = [ $GLOBALS[g_SITECODE], $this->idee, $this->username, $date, $action, ];
		return substr(sha1(implode('-', $elems)), 0, 7);
	}

	/** Returns query items suitable for composing in a URL, containing a date, an action and a hash code. */
	function getActionHashQuery($action) {
		$date = date('YmdHis');
		return [ KEY_DATE=>$date, KEY_ACTION=>$action, KEY_HASH=>$this->getActionHash($date, $action), ];
	}

	/** Examines $_REQUEST and confirms date, action, and hash match. */
	function confirmActionHashQuery($query=null, $cutoff=null) {
		if (!$query) { $query = $_REQUEST; }
		$hash = $this->getActionHash($_REQUEST[KEY_DATE], $_REQUEST[wFormBuilder::KEY_ACTION]);
		if ($hash != $_REQUEST[KEY_HASH]) { return false; }
		if ($cutoff && strtotime($_REQUEST[KEY_DATE]) < $cutoff) { return false; }
		return true;
	}

}

?>
