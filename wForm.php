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

//require_once 'wCommon/wStandard.php';

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
}

/**
* Class for creating and parsing forms.
* NOTE: Avoid using hyphens in the element names in the form, it makes javascript harder to access things by name.
* NOTE: The "id" is set for form elements using the supplied "name"; so keep names unique just like you would id's.
*/
class wFormBuilder {

// Constants for storing form errors and values in $_SESSION.
	const SKEY_ERRORS = 'form-errors';
	const SKEY_VALUES = 'form-values';

// Constants for controlling how form data will be scrubbed.
	const ENCODE_HTML = 'encode-html';
	const STRIP_ALL_TAGS = 'strip-all-tags';
	const MUST_EXIST = 'must-exist';
	const LOWER_CASE = 'lower-case';
	const FILTER_EMAIL = 'filter-email';
	const POSITIVE = 'positive';
	const NON_NEGATIVE = 'non-negative';
	const FLAG_NONE = 'none'; // useful as a dummy action when the array needs to exist, but we don't have any flags to give it

/**
* An instance of wHTMLComposer for generating HTML.
* A new composer will be created when a new class instance is created, but users can replace it with their own if desired.
*/
	public $composer;

/**
* Creates a new wFormBuilder.
* Users of this class will typically subclass to provide project-specific functionality.
* This class will create its own internal wHTMLComposer to generate HTML.
* @param string $action the URL where the from will be sent
* @param string $method either 'post' or 'get', defaults to 'post'
*/
	function __construct($action=null, $method='post') {
		if (!$action) { $action = getURLPath(); }
		$this->action = $action;
		$this->method = $method;
		$this->composer = new wHTMLComposer();
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
* The message is wrapped in a SPAN tag with class 'error' and prepended with an m-dash,
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

/**
* Returns the value associated with a named form element, as it is stored in $_SESSION.
*/
	protected function sessionValue($name) { return $_SESSION[self::SKEY_VALUES][$name]; }

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
* Returns true if the sha1 hash value of $value matches the hash stored in $_POST[$name . '_hash_'].
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
		showLineEndings($value);
		$val = ($_POST[$name.'_hash_']==sha1($value));
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

//
// !Adding parts to the form
//

/**
* Adds text with a label. Use it for a value that isn't editable, but should be displayed consistent with  other editable fields.
* Uses the classes "lbl" and "inp".
* @param string $label the label for the text
* @param string $value the text itself
*/
	function addText($label, $value) {
		$this->composer->beginTag('p', 'lbl');
		$this->composer->addTag('label', null, null, $label);
		$this->composer->endTag();
		$this->composer->addTag('p', 'inp', null, $value);
	}

/**
* Adds a hidden field to the form.
* @param string $name name of the hidden field
* @param string $value value for the hidden field
* @param string $id optional id for the hidden field
*/
	function addHiddenField($name, $value) {
		$this->composer->addTag('input', null, array('type'=>'hidden', 'name'=>$name, 'value'=>$value, 'id'=>$name, ));
	}

/**
* Adds one or more hidden fields to the form, using the specified $keys to retreive values from $_REQUEST.
* @param array $keys a list of keys that exist in $_REQUEST
*/
	function addHiddenKeys($keys=array()) {
		foreach (array_filter($keys, function($k) { return $_REQUEST[$k]; }) as $key) { $this->addHiddenField($key, $_REQUEST[$key]); }
	}

/**
* Adds an input field with a label and optional help message.
* Each of those three will be wrapped in its own 'p' tag with the class "lbl", "help", and "inp" for each 'p' tag, respectively.
* Will also include an error message as part of the label if it has been set on $_SESSION.
* The single parameter $items passed to this method is an array of items that control how the label and text field will display. The contents are:
* <dl>
* <dt>name</dt><dd>form name of the text field, will also be used as the "for" parameter of the label</dd>
* <dt>value</dt><dd>value for field, if a session value exists it will override whatever is in $items['value']</dd>
* <dt>label</dt><dd>label to include with the text field</dd>
* <dt>label-id</dt><dd>optional id to put on the label</dd>
* <dt>help</dt><dd>text that will be wrapped in a 'p' tag between the label and the text field</dd>
* <dt>help-id</dt><dd>optional id to put on the help paragraph</dd>
* <dt>type</dt><dd>can be 'password', defaults to 'text'</dd>
* <dt>xattr</dt><dd>additional attributes to include in the 'input' tag, as an array of the form array('attrib-name'=>'attrib-value', ... )</dd>
* </dl>
*/
	function addInputField($items) {
		if ($items['label']) {
			$this->composer->beginTag('p', 'lbl');
			$this->composer->addTag('label', null, array('for'=>$items['name'], 'id'=>$items['label-id'], ), $items['label'] . self::getSessionError($items['name']));
			$this->composer->endTag();
		}
		if ($items['help']) { $this->composer->addTag('p', 'help', array('id'=>$items['help-id'], ), $items['help']); }
		if (!$items['type']) { $items['type'] = 'text'; }
		if ($this->sessionValue($items['name'])) { $items['value'] = $this->sessionValue($items['name']); }
		$this->composer->beginTag('p', 'lbl');
		$this->composer->addTag('input', null, array_merge(array('type'=>$items['type'], 'value'=>$items['value'], 'name'=>$items['name'], 'id'=>$items['name'], ), $items['xattr']));
		$this->composer->endTag();
	}

/**
*
*/
	function addTextArea($items) { // array of items such as: array('label'=>'', 'name'=>'', 'value'=>'', 'xattr'=>'', 'help'=>'', 'checksum'=>'', 'rows'=>'', )
		//NOTE: we use id="" in the text field (and construct it from the 'name') so that clicking on the label (which has the for="" attribute set) will activate the text field
		//NOTE: the value="" attribute is what will be pre-populated in the field when the form is displayed, and will pre-populate with stashed session value
		//NOTE: the name item identifies what will be sent back in the script, readable with $_POST[$name]
		//NOTE: to support the box-office payment fields, we have a 'post-input' item that will come after the input field before its closing <p> tag
		//NOTE: to shortcut processing, you can request a sha1 hash on the value be included -- on the processing side, if the sha1 matches we skip updating the field, if you specify a checksum, you must also include a 'value'
		if ($items['checksum']) { normalizeLineEndings($items['value']); showLineEndings($items['value']); $this->addHiddenField($items['name'].'_hash_', sha1(trim($items['value']))); }
		if ($this->sessionValue($items['name'])) { $items['value'] = $this->sessionValue($items['name']); }
		if ($items['label']) { $this->middle .= '<p class="lbl"><label for="' . $items['name'] . '">' . $items['label'] . '</label>' . self::getSessionError($items['name']) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help">' . $items['help'] . '</p>'; }
		if (!$items['rows']) { $items['rows'] = 5; }
		$this->middle .= '<p class="inp"><textarea name="' . $items['name'] . '" id="' . $items['name'] . '" rows="' . $items['rows'] . '" ' . $items['xattr'] . '>' . $items['value'] . '</textarea>' . $items['after-input'] . '</p>';
	}

/**
*
*/
	function addButtons($butts) { // array of button items, each item is an array such as: array('name'=>'', 'action'=>'', 'display'=>'', ) 'type' defaults to 'submit'; and 'name' defaults to KEY_ACTION; 'action' is the button's value; if no 'action', then 'name' and 'value' will be skipped
		$this->middle .= '<p>';
		foreach ($butts as $items) {
			if (!$items['type']) { $items['type'] = 'submit'; }
			if (!$items['name']) { $items['name'] = KEY_ACTION; }
			$this->middle .= '<button type="' . $items['type'] . '" id="' . $items['name'] . '" ' . ( $items['action'] ? 'name="' . $items['name'] . '" value="' . $items['action'] . '"' : '' ) . ' ' . $items['xattr'] . '>' . $items['display'] . '</button>';
		}
		$this->middle .= '</p>';
	}

/**
*
*/
	function addRadios($label, $name, $radios, $items=array()) { // array of radio items, each item is an array such as: array('value'=>'', 'selected'=>true/false, 'xattr'=>'', 'label'=>'', ); $label is the outer label for the entire group; $items can contain 'break' or 'help' or 'selected'
		//NOTE: PHP will return the value that is checked, readable with $_POST[$name]
		//NOTE: we create an id (using 'name-value' to hopefully be unique) to link the checkbox and the label so clicking on the label will toggle the checkbox
		//NOTE: replace $break with something else (such as &nbsp;) to control how the radio buttons flow
		if ($label) { $this->middle .= '<p class="lbl"><label>' . $label . '</label>' . self::getSessionError($name) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help">' . $items['help'] . '</p>'; }
		$break = $items['break'] or $break = '<br />';
		$this->middle .= '<p class="inp">';
		foreach ($radios as $radio) {
			if ($this->sessionValue($name)) { $radio['selected'] = ($this->sessionValue($name) == $radio['value']); }
			else if ($items['selected']) { $radio['selected'] = ($items['selected'] == $radio['value']); }
			$this->middle .= ( $doneOne ? ( $radio['break'] ? $radio['break'] : $break ) : '' ) . '<input type="radio" name="' . $name . '" value="' . $radio['value'] . '" id="' . $name . '-' . $radio['value'] . '" ' . ( $radio['selected'] ? 'checked="checked"' : '' ) . ' ' . $radio['xattr'] . '>&nbsp;<label for="' . $name . '-' . $radio['value'] . '">' . $radio['label'] . '</label>';
			$doneOne = true;
		}
		$this->middle .= '</p>';
	}

/**
*
*/
	function getRadio($label, $name, $items=array()) { // returns the code for a bare radio button, using $name and $label and optional $items=array('value'=>'', 'selected'=>true/false, 'array'=>true/false, 'xattr'=>'', ) // where value is the desired value to return in POST, select determines whether or not it is selected (and will be overridden by value, if any, in the session), and 'array' is a flag indicating whether or not the name should be suffixed with '[]'
		if (!$items['value']) { $items['value'] = 'true'; }
		if ($items['array'] && $this->sessionValue($name)) { $items['selected'] = in_array($items['value'], $this->sessionValue($name)); }
		else if ($this->sessionValue($name)) { $items['selected'] = ($this->sessionValue($name)==$items['value']); }
		else if ($_SESSION[self::SKEY_VALUES]) { $items['selected'] = false; }
		if ($this->sessionValue($name)) { $items['selected'] = ( $items['array'] ? in_array($items['value'], $this->sessionValue($name)) : $this->sessionValue($name)==$items['value'] ); }
		$id = $name . ( $items['array'] ? '-' . $items['value'] : '' );
		return '<input type="radio" name="' . $name . ( $items['array'] ? '[]' : '' ) . '" value="' . $items['value'] . '" id="' . $id . '" ' . ( $items['selected'] ? 'checked="checked"' : '' ) . ' ' . $items['xattr'] . '>&nbsp;<label for="' . $id . '">' . $label . '</label>';
	}

/**
*
*/
	function addSelect($label, $name, $menus, $items=array()) { // menus is a list of item arrays, such as array('value'=>'', 'label'=>'', 'selected'=>true/false, ) similar to radio buttons, in fact the same inputs will work for both radios and selects // 'label' defaults to 'value' if not supplied // $items can contain 'help','xattr', and 'selected'
		if ($label) { $this->middle .= '<p class="lbl"><label for="' . $name . '">' . $label . '</label>' . self::getSessionError($name) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help">' . $items['help'] . '</p>'; }
		$this->middle .= '<p class="inp"><select name="' . $name . '" id="' . $name . '" ' . $items['xattr'] . '>';
		foreach ($menus as $menu) {
			if (!$menu['label']) { $menu['label'] = $menu['value']; }
			if ($this->sessionValue($name)) { $menu['selected'] = ($this->sessionValue($name) == $menu['value']); }
			else if ($items['selected']) { $menu['selected'] = ($items['selected'] == $menu['value']); }
			$this->middle .= '<option value="' . $menu['value'] . '" ' . ( $menu['selected'] ? 'selected="selected"' : '' ) . '>' . $menu['label'] . '</option>';
		}
		$this->middle .= '</select></p>';
	}

/**
*
*/
	function addCheckboxes($label, $name, $boxes, $items=array()) { // boxes is an array of checkbox items, each item is an array such as: array('value'=>'', 'selected'=>true/false, 'xattr'=>'', 'label'=>'', 'break'=>'', ); $label is the outer label for the entire group; $items are other stuff, such as 'help' and 'break', that will display for the entire group; 'value' is the text that will be returned if the checkbox is marked, should be unique, and those values will be combined and returned as an array
		//NOTE: this works for one checkbox, but it is intended for a group of checkboxes and the values will be collected in an array under $_POST[$name]
		//NOTE: so the name of each checkbox will be $name.'[]'
		//NOTE: we create an id (using 'name-value' to hopefully be unique) to link the checkbox and the label so clicking on the label will toggle the checkbox
		if ($label) { $this->middle .= '<p class="lbl"><label>' . $label . '</label>' . self::getSessionError($name) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help">' . $items['help'] . '</p>'; }
		$break = $items['break'] or $break = '<br />';
		$this->middle .= '<p class="inp">';
		foreach ($boxes as $box) {
			if ($this->sessionValue($name)) { $box['selected'] = in_array($box['value'], $this->sessionValue($name)); }
			$this->middle .= ( $doneOne ? ( $box['break'] ? $box['break'] : $break ) : '' ) . '<input type="checkbox" name="' . $name . '[]" value="' . $box['value'] . '" id="' . $name . '-' . $box['value'] . '" ' . ( $box['selected'] ? 'checked="checked"' : '' ) . ' ' . $box['xattr'] . '>&nbsp;<label for="' . $name . '-' . $box['value'] . '">' . $box['label'] . '</label>';
			$doneOne = true;
		}
		$this->middle .= '</p>';
	}

/**
*
*/
	function addCheckbox($label, $name, $items=array()) { // for a single checkbox, items is an array such as: array('value'=>'', 'selected'=>true/false, 'xattr'=>'', 'label'=>'', 'break'=>'', 'help'=>'', ); $label is the outer label for the form row;
		//NOTE: this works for one checkbox, or for a group of checkboxes
		//NOTE: for a group, make the name the same for each checkbox, with a trailing [] and PHP will treat them as an array, returning in the array the 'value' for any checked boxes
		//NOTE: we create an id (using 'name') to link the checkbox and the label so clicking on the label will toggle the checkbox
		if ($label) { $this->middle .= '<p class="lbl"><label>' . $label . '</label>' . self::getSessionError($name) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help">' . $items['help'] . '</p>'; }
		$this->middle .= '<p class="inp">';
		if (!$items['value']) { $items['value'] = 'true'; }
		if ($this->sessionValue($name)) { $items['selected'] = ($this->sessionValue($name)==$items['value']); } else if ($_SESSION[self::SKEY_VALUES]) { $items['selected'] = false; } // false doesn't work as a sticky value, because $name won't be sent at all in $_POST
		$this->middle .= '<input type="checkbox" name="' . $name . '" value="' . $items['value'] . '" id="' . $name . '" ' . ( $items['selected'] ? 'checked="checked"' : '' ) . ' ' . $items['xattr'] . '>&nbsp;<label for="' . $name . '">' . $items['label'] . '</label>';
		$this->middle .= '</p>';
	}

/**
*
*/
	function getCheckbox($label, $name, $items=array()) { // returns the code for a bare checkbox, using $name and $label and optional $items=array('value'=>'', 'selected'=>true/false, 'array'=>true/false, 'xattr'=>'', ) // where value is the desired value to return in POST, select determines whether or not it is selected (and will be overridden by value, if any, in the session), and 'array' is a flag indicating whether or not the name should be suffixed with '[]'
		if (!$items['value']) { $items['value'] = 'true'; }
		if ($items['array'] && $this->sessionValue($name)) { $items['selected'] = in_array($items['value'], $this->sessionValue($name)); }
		else if ($this->sessionValue($name)) { $items['selected'] = ($this->sessionValue($name)==$items['value']); }
		else if ($_SESSION[self::SKEY_VALUES]) { $items['selected'] = false; }
		if ($this->sessionValue($name)) { $items['selected'] = ( $items['array'] ? in_array($items['value'], $this->sessionValue($name)) : $this->sessionValue($name)==$items['value'] ); }
		$id = $name . ( $items['array'] ? '-' . $items['value'] : '' );
		return '<input type="checkbox" name="' . $name . ( $items['array'] ? '[]' : '' ) . '" value="' . $items['value'] . '" id="' . $id . '" ' . ( $items['selected'] ? 'checked="checked"' : '' ) . ' ' . $items['xattr'] . '>&nbsp;<label for="' . $id . '">' . $label . '</label>';
	}

/**
*
*/
	function addCustom($string) { $this->middle .= $string; }

/**
* Adds a statement and example about entering dates.
* Uses the class 'eg' by default, but another can be supplied by callers or by subclassers.
* @param string $class the class to use for the date example, defaults to 'eg'
*/
	function addDateSample($class='eg') {
		$this->composer->addCustom('Enter dates using a YYYY-MM-DD HH:MM:SS format (24-hour clock). For example, today would be: ');
		$this->composer->addTag('span', $class, null, date('Y-m-d H:i:s'));
	}

/**
*
*/
	function getForm() {
		unset($_SESSION[self::SKEY_ERRORS]);
		unset($_SESSION[self::SKEY_VALUES]);
		return '<form method="' . $this->method . '" action="' . $this->action . '"' . ( $this->enctype ? ' enctype="' . $this->enctype . '"' : '' ) . '>' . $this->middle . '</form>';
	}

	// -----------------------------
	// !handling file uploads

/**
*
*/
	static function didUpload($name) { return $_FILES[$name]['size']>0 && $_FILES[$name]['tmp_name'] && $_FILES[$name]['name'] && $_FILES[$name]['error']!=UPLOAD_ERR_NO_FILE; } // we check up and down, left and right, that the user actually attempted to upload a file

/**
*
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
