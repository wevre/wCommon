<?php
/**
* Helper class for displaying and parsing web forms.
*
* @copyright 2013-2015 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2013-03-02
*
* @version 1.0
*
*/

require_once 'wCommon/wStandard.php';

/**
* Error function used to log diagnostic messages and errors when testing checksums and when uploading files.
*/
function form_error_log($msg, $excp=null) { /*w_error_log(null, 'FORM', $msg, $excp);*/ }

/**
* Function to display different line endings. Used by debug logic in the checksum methods.
*/
function showLineEndings($string) { form_error_log(str_replace(array("\r\n", "\n\r", "\r", ), array("CRLF\n", "LFCR\n", "CR\n", ), $string)); }

/**
* Converts all line endings to UNIX format and reduces to no more than two in succession.
*/
function normalizeLineEndings(&$s) {
		$s = str_replace("\r\n", "\n", $s);
		$s = str_replace("\r", "\n", $s);
		$s = preg_replace("/\n{2,}/", "\n\n", $s);
		//showLineEndings($s);
}

/**
* Class for creating and parsing forms.
* NOTE: Avoid using hyphens in the element names in the form, it makes javascript harder to access things by name.
* NOTE: The "id" is set for form elements using the supplied "name"; so keep names unique just like you would id's.
*/
class wFormBuilder {

	public $fDebug = false;

// Constants for storing form errors and values in $_SESSION.
	const SKEY_ERRORS = 'form-errors';
	const SKEY_VALUES = 'form-values';

	const KEY_ACTION = 'a';

	const CLASS_LABEL = 'lbl';
	const CLASS_INPUT = 'inp';
	const CLASS_HELP = 'help';
	const CLASS_EG = 'eg';

// Constants for controlling how form data will be scrubbed.
	const ENCODE_HTML = 'encode-html'; // Converts `&`, `"`, `<` and `>` using htmlspecialchars(). Note it does not convert single quotes `'`
	const STRIP_ALL_TAGS = 'strip-all-tags';
	const NO_SPEC_CHARS = 'no-spec-chars';
	const MUST_EXIST = 'must-exist';
	const LOWER_CASE = 'lower-case';
	const FILTER_EMAIL = 'filter-email';
	const POSITIVE = 'positive';
	const NON_NEGATIVE = 'non-negative';
	const FLAG_NONE = 'none'; // Useful as a dummy action when the array needs to exist, but we don't have any flags to give it.

	const HASH_SUFFIX = '-hash';
	const HASH_LEN = -7; // Will be used with substr function to take the last n characters of the hash.

/**
* An instance of wHTMLComposer for generating HTML.
* A new composer will be created when a new class instance is created, but users can replace it with their own if desired.
*/
	public $composer; //TODO: This variable name should be $cp because that is my standard tag for a Composer.

/**
* Creates a new wFormBuilder.
* Users of this class will typically subclass to provide project-specific functionality.
* This class will create its own internal wHTMLComposer to generate HTML.
* @param string $action the URL where the from will be sent
* @param string $method either 'post' or 'get', defaults to 'post'
*/
	function __construct($attribs=[]) {
		if (!$attribs['action']) { $attribs['action'] = getURLPath(); }
		if (!$attribs['method']) { $attribs['method'] = 'post'; }
		$this->composer = new wHTMLComposer();
		$this->composer->beginElement('form', $attribs);
	}

//
// !Values and errors in $_SESSION
//

/**
* Sets a session error for a given form name.
* @param string $name a name of a form element with which the error will be associated
* @param string $msg the error message
*/
	static function setSessionError($name, $msg) { $_SESSION[self::SKEY_ERRORS][$name] = $msg; }

/**
* Returns an error message, if it exists, associated with a form name.
* The message is wrapped in a SPAN element with class 'error' and prepended with an m-dash,
* because it is intended to be attached to the label for the form element.
*/
	static function getSessionError($name) { return wrapIfCe($_SESSION[self::SKEY_ERRORS][$name], '<span class="error"> &#8212; ', '</span>'); }

/**
* Sets the value in $_SESSION for the name of a form element.
* This method sets one value for one name, but the more typical case is to grab all the form values and store them in the session so they will be sticky.
* This method, then, is used for one-off cases when the value needs to be, for example, overridden or set to null.
* @param string $name the name of a form element with which the value will be associated
* @param string $value the value to associate with the named form element
* @see grabSessionValues()
*/
	static function setSessionValue($name, $value) { $_SESSION[self::SKEY_VALUES][$name] = $value; }

/** Clears the value in $_SESSION for the supplied $name. */
	static function clearSessionValue($name) { unset($_SESSION[self::SKEY_VALUES][$name]); }

/**
* Returns the value associated with a named form element, as it is stored in $_SESSION.
*/
	static function sessionValue($name) { return $_SESSION[self::SKEY_VALUES][$name]; }

/**
* Grabs the values in $_POST and saves them in $_SESSION.
*/
	static function grabSessionValues() { $_SESSION[self::SKEY_VALUES] = $_POST; }

//
// !Checking $_POST values
//

/**
* Retrieves the value of $_POST[$name] and checks if it is a unique key in the $cnxn database.
* Sets an error if the value is not a unique key.
* See github.com/wevrem/dStruct.
* @param object $cnxn a dConnection object that represents a connection to a dStruct database
* @param string $gname dStruct category for which to test for a unique key
* @param string $value the value to test for uniqueness
* @param string $name the form name associated with this value; also where an error will be set if needed
*/
	static function testUniqueKey($cnxn, $gname, $value, $name) { // finds the value of $_POST[$name] and checks if it is a unique key in the database, setting an error if it is not
		if (!$cnxn->isUniqueKey($gname, $value)) { self::setSessionError($name, 'Must be unique'); return false; }
		else { return true; }
	}

/**
* Returns a pair of items: (a) value that has been scrubbed according to $flags; and (b) an optional error message.
* @param string $value a value to be cleaned
* @param array $flags an array of constants that control the scrubbing
*/
	protected static function cleanValue($value, $flags=array()) {
		$value = trim($value) or $value = null; // we don't want empty string, change it to null
		if (in_array(self::NO_SPEC_CHARS, $flags) && $value!=urlencode($value)) { return array(null, 'No special characters or spaces'); }
		if (in_array(self::MUST_EXIST, $flags) && !$value) { return array(null, "Can't be blank"); }
		if (in_array(self::LOWER_CASE, $flags) || in_array(self::FILTER_EMAIL, $flags)) { $value = strtolower($value); }
		if (in_array(self::ENCODE_HTML, $flags)) { $value = htmlspecialchars($value); }
		if (in_array(self::STRIP_ALL_TAGS, $flags)) { $value = strip_tags($value); }
		if ($value && in_array(self::FILTER_EMAIL, $flags)) { if (!($value = filter_var($value, FILTER_VALIDATE_EMAIL))) { return array(null, 'Provide a valid email'); } }
		return array(( $value ? $value : null ), null);
	}

/**
* Retrieves the named form element at $_POST[$name] and cleans it according to $flags.
* If any of the cleaning fails, an error will be set for $name and the method returns null.
* @return a string that represents the cleaned value, or null if cleaning failed
* @param string $name a named form element
* @param array $flags list of constants that control the scrubbing
*/
	static function cleanInput($name, $flags=array()) {
		list($value, $error_string) = self::cleanValue($_POST[$name], $flags);
		if ($error_string) { self::setSessionError($name, $error_string); }
		return $value;
	}

/**
* Retrieves the named form element at $_POST[$name] and converts it to a number according to $flags.
* If a number can't be converted, and the MUST_EXIST flag is not specified, then it is okay to return null.
* If conversion fails, session errors will be set associated with $name.
* @return number if conversion is successful
* @return false if conversion fails and MUST_EXIST flag was specified.
* @return null if conversion failes but existing is not required
* @param string $name a named form element
* @param array $flags list of constants to control the conversion; valid values are MUST_EXIST, POSITIVE, NON_NEGATIVE
*/
	static function getNumber($name, $flags=array()) {
		$value = trim($_POST[$name]);
		if ($value=='') { if (in_array(self::MUST_EXIST, $flags)) { self::setSessionError($name, "Can't be blank"); return false; } else { return null; } } // null is okay (desired, even) unless it must exist
		if (!is_numeric($value)) { self::setSessionError($name, 'Must be a number'); return false; }
		if (in_array(self::POSITIVE, $flags) && $value<=0) { self::setSessionError($name, 'Must be greater than 0'); return false; }
		if (in_array(self::NON_NEGATIVE, $flags) && $value<0) { self::setSessionError($name, 'Must not be negative'); return false; }
		return $value;
	}

/**
* Returns true if the sha1 hash value of $value matches the hash stored in $_POST[$name . HASH_SUFFIX].
* This method will first normalize the line endings of $value before testing for a matching hash.
* Typical use looks something like this, where we test if the hashes match and if not, update the value on our internal object.
* <code>
* wFormBuilder::testChecksum(POST_ESSAY, $val = trim($_POST[POST_ESSAY])) or $myObject->essay = $val;
* </code>
* In the above example, if the hashes match, then what we already have stored in our internal object is current, no need to update.
* This is especially useful if a changed value triggers a lot of extra processing.
* If the hashes match, it means we round-tripped the form with no updates to this particular field and we can skip the extra processing.
* @param string $name a form element name
* @param string $value the value to examine
*/
	static function testChecksum($name, $value) {
		if (!$value) { return false; }
		normalizeLineEndings($value);
		$val = ($_POST[$name . self::HASH_SUFFIX] == substr(sha1($value), self::HASH_LEN));
		form_error_log('for name ' . $name . ' checksum matches? ' . ( $val ? 'YES' : 'NO' ));
		return $val;
	}

/**
* Grab a date string from the $_POST data and return a valid timestamp if possible, else return false.
* On failure, will set session errors associated with $name.
* @param string $name a form element name
* @param bool $mustExist flag to control whether the date must exist or can be blank, defaults to false == can be blank
*/
	static function confirmDate($name, $mustExist=false) {
		$stamp = strtotime($_POST[$name]);
		if (!$stamp && ($_POST[$name] || $mustExist)) { self::setSessionError($name, "Enter a valid date"); return false; }
		else if (!$stamp) { return null; }
		else { return date('Y-m-d H:i:s', $stamp); }
	}

//
// !Other form-related things
//

/**
* Splits and returns as an array of strings the value found in $_POST[$name].
* Only non-empty lines are returned, but original line numbers (as key) are preserved in the array.
* @param string $name a form element name
*/
	static function splitLines($name) { return array_filter(explode("\n", $_POST[$name]), function($s) { return !empty(trim($s)); } ); }

/**
* Scans text for potential replacements.
* Currently the only replacement dealt with is am/pm next to numbers are converted to uppercase, using the class "ampm".
* @param string value the value to convert
*/
	static function scanFaves($value) {
		// check for replacements we might want to make
		return preg_replace('/(\d )([a|p]m)([^a-zA-Z]|$)/', '\1<span class="ampm">\2</span>\3', $value);
	}

/**
* Adds a statement and example about entering dates.
* Uses the class 'eg' by default, but another can be supplied by callers or by subclassers.
* @param string $class the class to use for the date example, defaults to 'eg'
*/
	static function getDateSample() {
		return 'Enter dates using a YYYY-MM-DD HH:MM:SS format (24-hour clock). For example, today would be: <span class="' . static::CLASS_EG . '">' . date('Y-m-d H:i:s') . '</span>';
	}

//
// !Adding parts to the form
//

/**
* Adds text with a label. Use it for a value that isn't editable, but should be displayed consistent with  other editable fields.
* @param string $label the label for the text
* @param string $value the text itself
*/
	function addText($label, $value) {
		$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
		$this->composer->addElement('label', [], $label);
		$this->composer->endElement();
		$this->composer->addElement('p', array('class'=>static::CLASS_INPUT), $value);
	}

/**
* Adds a hidden field to the form.
* @param string $name name of the hidden field
* @param string $value value for the hidden field
*/
	function addHiddenField($name, $value) {
		if (is_null($value)) { return; }
		$this->composer->addElement('input', array('type'=>'hidden', 'name'=>$name, 'value'=>$value, 'id'=>$name, ));
	}

/**
* Adds one or more hidden fields to the form, using the specified $keys to retreive values from $_REQUEST.
* @param array $keys a list of keys that exist in $_REQUEST
* @uses addHiddenField()
*/
	function addHiddenKeys($keys=[]) {
		array_key_map([ $this, 'addHiddenField' ], filter_request($keys));
	}

/**
* Adds an input field with (optional) label and (optional) help message.
* Each of those three will be wrapped in its own 'p' element using the HTML classes CLASS_LABEL, CLASS_HELP, and CLASS_INPUT.
* Will also include an error message as part of the label if it has been set on $_SESSION.
* The single parameter $items passed to this method is an array of items that control how the label and text field will display.
* For most of the entries in the $items array, the keys represent typical attributes for the 'input' element. The contents are:
* <dl>
* <dt>type</dt><dd>can be 'password', defaults to 'text'</dd>
* <dt>value</dt><dd>value for field, if a session value exists it will override whatever is passed in $items['value']</dd>
* <dt>name</dt><dd>form name of the text field, will also be used as the 'for' attribute of the label and the default 'id' attribute for the text field</dd>
* <td>id</dt><dd>The 'id' for the text field. If not supplied, it will be the same and 'name'.</dd>
* <dt>label</dt><dd>label to include with the text field</dd>
* <dt>label-id</dt><dd>optional id to put on the label</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' element between the label and the text field</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* <dt>xattr</dt><dd>additional attributes to include in the 'input' element, as an array of the form array('attrib-name'=>'attrib-value', ... )</dd>
* </dl>
*/
	function addInputField($items) {
		if ($items['label']) {
			$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
			$this->composer->addElement('label', array('for'=>$items['name'], 'id'=>$items['label-id'], ), $items['label'] . self::getSessionError($items['name']));
			$this->composer->endElement();
		}
		if ($items['help']) { $this->composer->addElement('div', array('class'=>static::CLASS_HELP, 'id'=>$items['help-id'], ), $items['help']); }
		// Make sure 'type', 'value', 'name', and 'id' are present.
		if (!$items['type']) { $items['type'] = 'text'; }
		if (!$items['value']) { $items['value'] = null; }
		if (self::sessionValue($items['name'])) { $items['value'] = htmlspecialchars(self::sessionValue($items['name']), ENT_QUOTES); }
		if (!$items['id']) { $items['id'] = $items['name']; }
		$this->composer->beginElement('p', array('class'=>static::CLASS_INPUT));
		$this->composer->addElement('input', array_merge(array_intersect_key($items, array_flip(['type', 'value', 'name', 'id'])), (array)$items['xattr']));
		$this->composer->endElement();
	}

/**
* Adds a text area with (optional) label and (optional) help message.
* Each of those three will be wrapped in its own 'p' element using the HTML classes CLASS_LABEL, CLASS_HELP, and CLASS_INPUT.
* Will also include an error message as part of the label if it has been set on $_SESSION.
* The single parameter $items passed to this method is an array of items that control how the label and text area will display.
* For most of the entries in the $items array, the keys represent typical attributes for the 'textarea' element. The contents are:
* <dl>
* <dt>value</dt><dd>value for the text area, if a session value exists it will override whatever is passed in $items['value']</dd>
* <dt>name</dt><dd>form name of the text area, will also be used as the 'for' attribute of the label and the default 'id' attribute for the text area</dd>
* <td>id</dt><dd>The 'id' for the text area. If not supplied, it will be the same and 'name'.</dd>
* <td>rows</dt><dd>value for the 'rows' attribute of the text area, defaults to 5</dd>
* <td>checksum</dt><dd>If present, a sha1 hash will be calculated on 'value' and included in the form as a hidden field. When the form is processed, if no changes have been made (meaning the original hash and the one calculated on the POST data match) then the receiving script can skip updating database fields.</dd>
* <dt>label</dt><dd>label to include with the text area</dd>
* <dt>label-id</dt><dd>optional id to put on the label</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' element between the label and the text area</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* <dt>xattr</dt><dd>additional attributes to include in the 'textarea' element, as an array of the form array('attrib-name'=>'attrib-value', ... )</dd>
* </dl>
*/
	function addTextArea($items) {
		if ($items['checksum']) {
			normalizeLineEndings($items['value']);
			$this->addHiddenField($items['name'] . self::HASH_SUFFIX, substr(sha1(trim($items['value'])), self::HASH_LEN));
		}
		if ($items['label']) {
			$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
			$this->composer->addElement('label', array('for'=>$items['name'], 'id'=>$items['label-id'], ), $items['label'] . self::getSessionError($items['name']));
			$this->composer->endElement();
		}
		if ($items['help']) { $this->composer->addElement('div', array('class'=>static::CLASS_HELP, 'id'=>$items['help-id'], ), $items['help']); }
		if (!$items['rows']) { $items['rows'] = 5; }
		if (!$items['id']) { $items['id'] = $items['name']; }
		if (self::sessionValue($items['name'])) { $items['value'] = htmlspecialchars(self::sessionValue($items['name']), ENT_QUOTES); }
		$this->composer->beginElement('p', array('class'=>static::CLASS_INPUT));
		$this->composer->addElement('textarea', array_merge(array_intersect_key($items, array_flip(['type', 'name', 'id', 'rows'])), (array)$items['xattr']), $items['value']);
		$this->composer->endElement();
	}

/**
* Adds one or more buttons to the form.
* All added buttons will be wrapped in a single 'p' element.
* The single parameter $butts is an array of button items: each button items is itself an array containing info to set up the button. The following list is for a single button items array.
* <dl>
* <dt>type</dt><dd>type attribute for the button, defaults to 'submit'</dd>
* <dt>id</dt><dd>will default to a concatenation of name-value, so as to be unique</dd>
* <dt>name</dt><dd>form name of the button, defaults to KEY_ACTION</dd>
* <dt>value</dt><dd>Value for the button, goes with the name as the value passed back in the form POST data</dd>
* <dt>content</dt><dd>title of the button that will display to the user</dd>
* <dt>xattr</dt><dd>additional attributes to include in the 'button' element, as an array of the form array('attrib-name'=>'attrib-value', ... )</dd>
* </dl>
*/
	function addButtons($butts) {
		$this->composer->beginElement('p');
		foreach ($butts as $items) {
			if (!$items['type']) { $items['type'] = 'submit'; }
			if (!$items['name']) { $items['name'] = self::KEY_ACTION; }
			if (!$items['id']) { $items['id'] = $items['name'] . '-' . $items['value']; }
			$this->composer->addElement('button', array_merge(array_intersect_key($items, array_flip(['type', 'id', 'name', 'value'])), (array)$items['xattr']), $items['content']);
		}
		$this->composer->endElement();
	}

/**
* Adds a group of radio items to the form.
* @param string $label outer label for the whole group
* @param string $name form name for the group, whatever radio is selected, its `value` will be returned in $_POST[$name]
* @param array $radios array of radio items, each item is an array described below
* @param array $items array of additional attributes that apply to the group
*
* Some attributes are automatic: 'type' will be set to 'radio', 'id' will be a concatenation of 'name' and the radio item's 'value'.
*
* Each element of the $radios array is itself an array, which contains the following:
* <dl>
* <dt>value</dt><dd>what is returned in $_POST[$name] if this radio button is checked</dd>
* <dt>selected</dt><dd>flag indicating if this radio button should be checked initially. Can be overridden by `selected` in the $items parameter, or by a session value.</dd>
* <dt>label</dt><dd>optional, if supplied is the label that will appear next to the radio button</dd>
* <dt>label-id</dt><dd>optional id to put on the radio button's label</dd>
* <dt>xattr</dt><dd>optional additional attributes to include with the radio button</dd>
* <dt>break</dt><dd>optional break to use after this radio button, if not supplied, will revert to the 'break' value included in $items</dd>
* </dl>
*
* The items array may contain the following keys to control the radio group:
* <dl>
* <dt>break</dt><dd>string to insert in between radio buttons, defaults to br element</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' element between the label and the radio group</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* <dt>label-id</dt><dd>optional id to put on the outer label</dd>
* <dt>selected</dt><dd>optional, which button should be checked initially. Must match the `value` of one of the $radios entries. Will be overridden by a session value, if one is set.</dd>
* </dl>
*/
	function addRadios($label, $name, $radios, $items=array()) {
		if ($label) {
			$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
			$this->composer->addElement('label', array('id'=>$items['label-id'], ), $label . self::getSessionError($name));
			$this->composer->endElement();
		}
		if ($items['help']) { $this->composer->addElement('div', array('class'=>static::CLASS_HELP, 'id'=>$items['help-id'], ), $items['help']); }
		$break = $items['break'] or $break = '<br />';
		$this->composer->beginElement('p', array('class'=>static::CLASS_INPUT));
		foreach ($radios as $radio) {
			if (self::sessionValue($name)) { $radio['selected'] = (self::sessionValue($name) == $radio['value']); }
			else if (!is_null($items['selected'])) { $radio['selected'] = ($items['selected'] == $radio['value']); }
			$radio['type'] = 'radio';
			$radio['name'] = $name;
			$radio['id'] = $name . '-' . $radio['value'];
			$radio['checked'] = ( $radio['selected'] ? 'checked' : null );
			if ($doneOne) { $this->composer->addCustom( $radio['break'] ? $radio['break'] : $break ); }
			$this->composer->addElement('input', array_merge(array_intersect_key($radio, array_flip(['type', 'name', 'value', 'id', 'checked'])), (array)$radio['xattr']));
			if ($radio['label']) {
				if (!$this->composer->fIndent) { $this->composer->addCustom('&nbsp;'); }
				$this->composer->addElement('label', array('for'=>$radio['id'], 'id'=>$radio['label-id']), $radio['label']);
			}
			$doneOne = true;
		}
		$this->composer->endElement(); // <P>
	}

/**
* Adds a menu to the form.
* The inputs are designed so that they will also work for the addRadios() method above.
* @param string $label outer label for the menu
* @param string $name form name for the menu, whatever option is selected, its `value` will be returned in $_POST[$name]
* @param array $menus array of menu items, each item is an array described below
* @param array $items array of additional attributes that apply to the menu
*
* Each element of the $menu array is itself an array, which contains the following:
* <dl>
* <dt>value</dt><dd>what is returned in $_POST[$name] if this menu option is selected</dd>
* <dt>selected</dt><dd>flag indicating if this menu option should be selected initially. Can be overridden by `selected` in the $items parameter, or by a session value.</dd>
* <dt>label</dt><dd>text used as the visible label for the menu option, defaults to 'value'</dd>
* </dl>
*
* The items array may contain the following keys to control the menu:
* <dl>
* <dt>id</dt><dd>optional id attribute for the select element</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' element between the label and the menu</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* <dt>label-id</dt><dd>optional id to put on the outer label</dd>
* <dt>selected</dt><dd>optional, which menu option should be selected initially. Must match the `value` of one of the $menu entries. Will be overridden by a session value, if one is set.</dd>
* </dl>
*/
	function addSelect($label, $name, $menus, $items=array()) {
		if ($label) {
			$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
			$this->composer->addElement('label', array('id'=>$items['label-id'], ), $label . self::getSessionError($name));
			$this->composer->endElement();
		}
		if ($items['help']) { $this->composer->addElement('div', array('class'=>static::CLASS_HELP, 'id'=>$items['help-id'], ), $items['help']); }
		$this->composer->beginElement('p', array('class'=>static::CLASS_INPUT));
		$this->composer->beginElement('select', array_merge(array('name'=>$name, 'id'=>$items['id'], ), (array)$items['xattr']));
		foreach ($menus as $menu) {
			if (self::sessionValue($name)) { $menu['selected'] = ( self::sessionValue($name) == $menu['value'] ? 'selected' : null ); }
			else if (!is_null($items['selected'])) { $menu['selected'] = ( $items['selected'] == $menu['value'] ? 'selected' : null ); }
			$menu['selected'] = ( $menu['selected'] ? 'selected' : null );
			if (!$menu['label']) { $menu['label'] = $menu['value']; }
			$this->composer->addElement('option', array_intersect_key($menu,array_flip(['value', 'selected'])), $menu['label']);
		}
		$this->composer->endElement();
		$this->composer->endElement();
	}

/**
* Adds an array of checkbox items to the form.
* @param string $label outer label for the whole group
* @param string $name form name for the group, whichever checkboxes are selected, their `value` will be returned in the array at $_POST[$name]
* @param array $boxes array of checkbox items, each item is an array described below
* @param array $items array of additional attributes that apply to the group
*
* Some attributes are automatic: 'type' will be set to 'checkbox', 'id' will be a concatenation of 'name' and the checkbox item's 'value'.
*
* Each element of the $boxes array is itself an array, which contains the following:
* <dl>
* <dt>value</dt><dd>what is returned in the array at $_POST[$name] if this checkbox is checked</dd>
* <dt>selected</dt><dd>flag indicating if this checkbox should be checked initially. Can be overridden by `selected` in the $items parameter, or by a session value.</dd>
* <dt>label</dt><dd>optional, if supplied is the label that will appear next to the checkbox</dd>
* <dt>label-id</dt><dd>optional id to put on the checkbox's label</dd>
* <dt>xattr</dt><dd>optional additional attributes to include with the checkbox</dd>
* <dt>break</dt><dd>optional break to use after the checkbox, if not supplied, will revert to the 'break' value included in $items</dd>
* </dl>
*
* The items array may contain the following keys to control the checkbox group:
* <dl>
* <dt>break</dt><dd>string to insert in between checkboxes, defaults to br element</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' element between the label and the checkbox group</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* <dt>label-id</dt><dd>optional id to put on the outer label</dd>
* <dt>selected</dt><dd>optional, which checkbox should be checked initially. Must match the `value` of one of the $boxes entries. Will be overridden by a session value, if one is set.</dd>
* </dl>
*/
	function addCheckboxes($label, $name, $boxes, $items=array()) {
		if ($label) {
			$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
			$this->composer->addElement('label', array('id'=>$items['label-id'], ), $label . self::getSessionError($name));
			$this->composer->endElement();
		}
		if ($items['help']) { $this->composer->addElement('div', array('class'=>static::CLASS_HELP, 'id'=>$items['help-id'], ), $items['help']); }
		$break = $items['break'] or $break = '<br />';
		$this->composer->beginElement('p', array('class'=>static::CLASS_INPUT));
		foreach ($boxes as $box) {
			if (self::sessionValue($name)) { $box['selected'] = (in_array($box['value'], self::sessionValue($name))); }
			else if ($items['selected']) { $box['selected'] = ($items['selected'] == $box['value']); }
			$box['type'] = 'checkbox';
			$box['name'] = $name . '[]';
			$box['id'] = $name . '-' . $box['value'];
			$box['checked'] = ( $box['selected'] ? 'checked' : null );
			if ($doneOne) { $this->composer->addCustom( $box['break'] ? $box['break'] : $break ); }
			$this->composer->addElement('input', array_merge(array_intersect_key($box, array_flip(['type', 'name', 'value', 'id', 'checked'])), (array)$box['xattr']));
			if ($box['label']) {
				if (!$this->composer->fIndent) { $this->composer->addCustom('&nbsp;'); }
				$this->composer->addElement('label', array('for'=>$box['id'], 'id'=>$box['label-id']), $box['label']);
			}
			$doneOne = true;
		}
		$this->composer->endElement();
	}

/**
* Adds a single checkbox to the form.
* @param string $label outer label for the checkbox
* @param string $name form name for the checkbox, if the checkbox is selected, `value` will be returned in $_POST[$name]
* @param array $items array of attributes to control the checkbox
*
* Some attributes are automatic: 'type' will be set to 'checkbox', 'id' will be a concatenation of 'name' and 'value'.
*
* Note, if the name has trailing brackets, `[]`, then PHP will return an array for $_POST[$name] that contains the values for any checked boxes that share the name.
* You can also do `name[key]` as the name, and the checkbox value will be in $_POST[$name][$key].
*
* The $items array contains entries with the following keys:
* <dl>
* <dt>value</dt><dd>what is returned in $_POST[$name] if the checkbox is checked, defaults to 'true'</dd>
* <dt>selected</dt><dd>flag indicating if this checkbox should be checked initially. Can be overridden by a session value.</dd>
* <dt>label</dt><dd>optional, if supplied is the label that will appear next to the checkbox</dd>
* <dt>label-id</dt><dd>optional id to put on the checkbox's label</dd>
* <dt>outer-label-id</dt><dd>optional id to put on the outer label</dd>
* <dt>xattr</dt><dd>optional additional attributes to include with the checkbox</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' element between the label and the checkbox</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* </dl>
*/
	function addCheckbox($label, $name, $items=array()) {
		if ($label) {
			$this->composer->beginElement('p', array('class'=>static::CLASS_LABEL));
			$this->composer->addElement('label', array('id'=>$items['outer-label-id'], ), $label . self::getSessionError($name));
			$this->composer->endElement();
		}
		if ($items['help']) { $this->composer->addElement('div', array('class'=>static::CLASS_HELP, 'id'=>$items['help-id'], ), $items['help']); }
		$this->composer->beginElement('p', array('class'=>static::CLASS_INPUT));
		if (!$items['value']) { $items['value'] = 'true'; }
		if (self::sessionValue($name)) { $items['selected'] = in_array($items['value'], (array)self::sessionValue($name)); }
		else if ($_SESSION[self::SKEY_VALUES]) { $items['selected'] = false; } // The notion of 'unchecked' can't be sticky, because for an unchecked box the name/value pair won't be saved in the session at all. Thus if session values exist but don't include the value for this checkbox, we interpret that as a sticky 'unchecked'.
		$items['type'] = 'checkbox';
		$items['name'] = $name;
		$items['id'] = $name . '-' . $items['value'];
		$items['checked'] = ( $items['selected'] ? 'checked' : null );
		$this->composer->addElement('input', array_merge(array_intersect_key($items, array_flip(['type', 'name', 'value', 'id', 'checked'])), (array)$items['xattr']));
		if ($items['label']) {
			if (!$this->composer->fIndent) { $this->composer->addCustom('&nbsp;'); }
			$this->composer->addElement('label', array('for'=>$items['id'], 'id'=>$items['label-id']), $items['label']);
		}
		$this->composer->endElement();
	}

/**
* Adds a custom string to the internal HTML string.
*/
	function addCustom($string) { $this->composer->addCustom($string); }

/**
* Returns the HTML string that was created with prior calls to all the other functions in this class.
*/
	function getForm() {
		unset($_SESSION[self::SKEY_ERRORS]);
		unset($_SESSION[self::SKEY_VALUES]);
		$this->composer->endElement(); //<FORM>
		return $this->composer->getHTML();
	}

//
// !File uploads
//

/**
* Checks up/down/left/right that the user actually uploaded a file.
*/
	static function didUpload($name) { return $_FILES[$name]['size']>0 && $_FILES[$name]['tmp_name'] && $_FILES[$name]['name'] && $_FILES[$name]['error']!=UPLOAD_ERR_NO_FILE; }

/**
*
* //TODO: we need to allow the document types to be a parameter, not hard-coded.
*/
	static function handleFileUpload($name) {
		global $client;
		do {
			// error checking on the uploaded file
			if (!$_FILES[$name]) { break; }
			if (!is_uploaded_file($_FILES[$name]['tmp_name'])) { form_error_log('didUpload: file at ' . $_FILES[$name]['tmp_name'] . ' is not an uploaded file'); break; }
			if (!in_array($_FILES[$name]['type'], array('image/png', 'image/gif', 'image/jpeg', 'image/jpg', 'image/pjpeg'))) { self::setSessionError($name, 'Upload a PNG, JPEG, or GIF file only'); break; }
			return $_FILES[$name]['tmp_name'];
		} while (0);
		// set an error if non c’è
		if (!$_SESSION[self::SKEY_ERRORS][$name]) switch ($_FILES[$name]['error']) {
			case UPLOAD_ERR_INI_SIZE :
			case UPLOAD_ERR_FORM_SIZE : self::setSessionError($name, 'Image file is too large. Please upload a smaller file.'); break;
			case UPLOAD_ERR_PARTIAL : self::setSessionError($name, 'Image file was only partially uploaded'); break;
			case UPLOAD_ERR_NO_FILE : self::setSessionError($name, 'No image file was uploaded'); break; // we shouldn't get this one, because we screened for it earlier
			default : self::setSessionError($name, 'An error occurred. Please try again. If you keep getting an error, please contact the web master.'); break;
		}
		return null;
	}

}

?>
