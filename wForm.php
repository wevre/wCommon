<?php
/**
* Help with displaying and parsing web forms.
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

// constants used to clean input
define('NO_SPEC_CHARS', 'no-spec-chars');
define('ENCODE_HTML', 'encode-html');
define('STRIP_ALL_TAGS', 'strip-all-tags');
define('MUST_EXIST', 'must-exist');
define('LOWER_CASE', 'lower-case');
define('FILTER_EMAIL', 'filter-email');
define('POSITIVE', 'positive');
define('NON_NEGATIVE', 'non-negative');
define('FLAG_NONE', 'none'); // useful as a dummy action when the array needs to exist, but we don't have any flags to give it

define('SKEY_FORM_ERRORS', 'form-errors');
define('SKEY_FORM_VALUES', 'form-values');

// -----------------------------
//! error function for checksums

function form_error_log($msg, $excp=null) { /*w_error_log(null, 'FORM', $msg, $excp);*/ }

//NOTE: avoid using hyphens in form names, it makes javascript harder to access things by name
//NOTE: the id is set for elements using the supplied 'name', so keep those unique

// -----------------------------
// Class for creating edit forms

class wFormBuilder {

	function __construct($action=null, $method='post') {
		if (!$action) { $action = getURLPath(); }
		$this->action = $action;
		$this->method = $method;
		$this->middle = '';
	}

	static function getDateSample() { return 'Enter dates using a YYYY-MM-DD HH:MM:SS format (24-hour clock). For example, today would be:<span class="eg">' . date('Y-m-d H:i:s') . '</span>'; }

	// -----------------------------
	//! values and errors in the $_SESSION

	static function setSessionError($name, $msg) { $_SESSION[SKEY_FORM_ERRORS][$name] = $msg; }

	static function getSessionError($name) { return ( $_SESSION[SKEY_FORM_ERRORS][$name] ? '<span class="error"> &#8212; ' . $_SESSION[SKEY_FORM_ERRORS][$name] . '</span>' : '' ); }

	static function setSessionValue($name, $value) { $_SESSION[SKEY_FORM_VALUES][$name] = $value; }

	function sessionValue($name) { return $_SESSION[SKEY_FORM_VALUES][$name]; }

	static function grabSessionValues($values=null) { $_SESSION[SKEY_FORM_VALUES] = ( $values ? $values : $_POST ); }

	// -----------------------------
	//! checking POST values

	static function testUniqueKey($cnxn, $gname, $value, $name) { // finds the value of $_POST[$name] and checks if it is a unique key in the database, setting an error if it is not
		if (!$cnxn->isUniqueKey($gname, $value)) { self::setSessionError($name, 'Must be unique'); return false; }
		else { return true; }
	}

	static function cleanValue($value, $flags=array()) {
		$value = trim($value) or $value = null; // we don't want empty string, change it to null
		if (in_array(NO_SPEC_CHARS, $flags) && $value!=urlencode($value)) { return array(null, 'No special characters or spaces'); }
		if (in_array(MUST_EXIST, $flags) && !$value) { return array(null, "Can't be blank"); }
		if (in_array(LOWER_CASE, $flags) || in_array(FILTER_EMAIL, $flags)) { $value = strtolower($value); }
		if (in_array(ENCODE_HTML, $flags)) { $value = htmlspecialchars($value); }
		if (in_array(STRIP_ALL_TAGS, $flags)) { $value = strip_tags($value); }
		if ($value && in_array(FILTER_EMAIL, $flags)) { if (!($value = filter_var($value, FILTER_VALIDATE_EMAIL))) { return array(null, 'Provide a valid email'); } }
		return array(( $value ? $value : null ), null);
	}

	static function cleanInput($name, $flags=array()) {
		list($value, $error_string) = self::cleanValue($_POST[$name], $flags);
		if ($error_string) { self::setSessionError($name, $error_string); }
		return $value;
	}

	static function getNumber($name, $flags=array()) { // returns false if can't get number and meet flag'd criteria
		$value = trim($_POST[$name]);
		if ($value=='') { if (in_array(MUST_EXIST, $flags)) { self::setSessionError($name, "Can't be blank"); return false; } else { return null; } } // null is okay (desired, even) unless it must exist
		if (!is_numeric($value)) { self::setSessionError($name, 'Must be a number'); return false; }
		if (in_array(POSITIVE, $flags) && $value<=0) { self::setSessionError($name, 'Must be greater than 0'); return false; }
		if (in_array(NON_NEGATIVE, $flags) && $value<0) { self::setSessionError($name, 'Must not be negative'); return false; }
		return $value;
	}

	static function testChecksum($name, $value) { // we know $_POST[$name], but the caller needs opportunity to pass in a trimmed/cleaned value
		if (!$value) { return false; }
		normalizeLineEndings($value);
		showLineEndings($value);
		$val = ($_POST[$name.'_hash_']==sha1($value));
		form_error_log('for name ' . $name . ' checksum matches? ' . ( $val ? 'YES' : 'NO' ));
		return $val;
	}

	static function confirmDate($name, $mustExist=false) { // grab a date POSTed in a form, returns false if can't parse required date
		$stamp = strtotime($_POST[$name]);
		if (!$stamp && ($_POST[$name] || $mustExist)) { self::setSessionError($name, "Enter a valid date"); return false; } // if something is there that we can't parse, or if must exist, set an error
		else if (!$stamp) { return null; }
		else { return date('Y-m-d H:i:s', $stamp); }
	}

	static function splitLines($name) { return array_filter(explode("\n", $_POST[$name]), function($s) { return !empty(trim($s)); } ); } // returns only non-empty lines, but keys are preserved so error messages can refer to correct line number

	static function scanFaves($value) {
		// check for replacements we might want to make
		return preg_replace('/(\d )([a|p]m)([^a-zA-Z]|$)/', '\1<span class="ampm">\2</span>\3', $value);
	}

	// -----------------------------
	//! adding parts to the form

	function addText($label, $value) {
		$this->middle .= '<p class="lbl"><label>' . $label . '</label></p><p class="inp">' . $value . '</p>';
	}

	function addHiddenField($name, $value, $id=null) {
		$this->middle .= '<input type="hidden" name="' . $name . '" value="' . $value . '"' . ( $id ? ' id="' . $id . '"' : '' ) . ' />';
	}

	function addInputField($items) { // array of items such as: array('label'=>'', 'name'=>'', 'value'=>'', 'type'=>'', 'xattr'=>'', 'help'=>'', ) 'type' can be 'password' or defaults to 'text'
		//NOTE: we use id="" in the text field (and construct it from the 'name') so that clicking on the label (which has the for="" attribute set) will activate the text field
		//NOTE: the value="" attribute is what will be pre-populated in the field when the form is displayed, and will pre-populate with stashed session value
		//NOTE: the name item identifies what will be sent back in the script, readable with $_POST[$name]
		//NOTE: to support the box-office payment fields, we have a 'post-input' item that will come after the input field before its closing <p> tag
		if ($items['label']) { $this->middle .= '<p class="lbl"><label for="' . $items['name'] . '" ' . ( $items['label-id'] ? ' id="' . $items['label-id'] . '"' : '' ) . '>' . $items['label'] . '</label>' . self::getSessionError($items['name']) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help"' . ( $items['help-id'] ? ' id="' . $items['help-id'] . '"' : '' ) . '>' . $items['help'] . '</p>'; }
		if (!$items['type']) { $items['type'] = 'text'; }
		if ($this->sessionValue($items['name'])) { $items['value'] = $this->sessionValue($items['name']); }
		$this->middle .= '<p class="inp"><input type="' . $items['type'] . '" value="' . $items['value'] . '" name="' . $items['name'] . '" id="' . $items['name'] . '" ' . $items['xattr'] . ' />' . $items['after-input'] . '</p>';
	}

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

	function addButtons($butts) { // array of button items, each item is an array such as: array('name'=>'', 'action'=>'', 'display'=>'', ) 'type' defaults to 'submit'; and 'name' defaults to KEY_ACTION; 'action' is the button's value; if no 'action', then 'name' and 'value' will be skipped
		$this->middle .= '<p>';
		foreach ($butts as $items) {
			if (!$items['type']) { $items['type'] = 'submit'; }
			if (!$items['name']) { $items['name'] = KEY_ACTION; }
			$this->middle .= '<button type="' . $items['type'] . '" id="' . $items['name'] . '" ' . ( $items['action'] ? 'name="' . $items['name'] . '" value="' . $items['action'] . '"' : '' ) . ' ' . $items['xattr'] . '>' . $items['display'] . '</button>';
		}
		$this->middle .= '</p>';
	}

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

	function getRadio($label, $name, $items=array()) { // returns the code for a bare radio button, using $name and $label and optional $items=array('value'=>'', 'selected'=>true/false, 'array'=>true/false, 'xattr'=>'', ) // where value is the desired value to return in POST, select determines whether or not it is selected (and will be overridden by value, if any, in the session), and 'array' is a flag indicating whether or not the name should be suffixed with '[]'
		if (!$items['value']) { $items['value'] = 'true'; }
		if ($items['array'] && $this->sessionValue($name)) { $items['selected'] = in_array($items['value'], $this->sessionValue($name)); }
		else if ($this->sessionValue($name)) { $items['selected'] = ($this->sessionValue($name)==$items['value']); }
		else if ($_SESSION[SKEY_FORM_VALUES]) { $items['selected'] = false; }
		if ($this->sessionValue($name)) { $items['selected'] = ( $items['array'] ? in_array($items['value'], $this->sessionValue($name)) : $this->sessionValue($name)==$items['value'] ); }
		$id = $name . ( $items['array'] ? '-' . $items['value'] : '' );
		return '<input type="radio" name="' . $name . ( $items['array'] ? '[]' : '' ) . '" value="' . $items['value'] . '" id="' . $id . '" ' . ( $items['selected'] ? 'checked="checked"' : '' ) . ' ' . $items['xattr'] . '>&nbsp;<label for="' . $id . '">' . $label . '</label>';
	}

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

	function addCheckbox($label, $name, $items=array()) { // for a single checkbox, items is an array such as: array('value'=>'', 'selected'=>true/false, 'xattr'=>'', 'label'=>'', 'break'=>'', 'help'=>'', ); $label is the outer label for the form row;
		//NOTE: this works for one checkbox, or for a group of checkboxes
		//NOTE: for a group, make the name the same for each checkbox, with a trailing [] and PHP will treat them as an array, returning in the array the 'value' for any checked boxes
		//NOTE: we create an id (using 'name') to link the checkbox and the label so clicking on the label will toggle the checkbox
		if ($label) { $this->middle .= '<p class="lbl"><label>' . $label . '</label>' . self::getSessionError($name) . '</p>'; }
		if ($items['help']) { $this->middle .= '<p class="help">' . $items['help'] . '</p>'; }
		$this->middle .= '<p class="inp">';
		if (!$items['value']) { $items['value'] = 'true'; }
		if ($this->sessionValue($name)) { $items['selected'] = ($this->sessionValue($name)==$items['value']); } else if ($_SESSION[SKEY_FORM_VALUES]) { $items['selected'] = false; } // false doesn't work as a sticky value, because $name won't be sent at all in $_POST
		$this->middle .= '<input type="checkbox" name="' . $name . '" value="' . $items['value'] . '" id="' . $name . '" ' . ( $items['selected'] ? 'checked="checked"' : '' ) . ' ' . $items['xattr'] . '>&nbsp;<label for="' . $name . '">' . $items['label'] . '</label>';
		$this->middle .= '</p>';
	}

	function getCheckbox($label, $name, $items=array()) { // returns the code for a bare checkbox, using $name and $label and optional $items=array('value'=>'', 'selected'=>true/false, 'array'=>true/false, 'xattr'=>'', ) // where value is the desired value to return in POST, select determines whether or not it is selected (and will be overridden by value, if any, in the session), and 'array' is a flag indicating whether or not the name should be suffixed with '[]'
		if (!$items['value']) { $items['value'] = 'true'; }
		if ($items['array'] && $this->sessionValue($name)) { $items['selected'] = in_array($items['value'], $this->sessionValue($name)); }
		else if ($this->sessionValue($name)) { $items['selected'] = ($this->sessionValue($name)==$items['value']); }
		else if ($_SESSION[SKEY_FORM_VALUES]) { $items['selected'] = false; }
		if ($this->sessionValue($name)) { $items['selected'] = ( $items['array'] ? in_array($items['value'], $this->sessionValue($name)) : $this->sessionValue($name)==$items['value'] ); }
		$id = $name . ( $items['array'] ? '-' . $items['value'] : '' );
		return '<input type="checkbox" name="' . $name . ( $items['array'] ? '[]' : '' ) . '" value="' . $items['value'] . '" id="' . $id . '" ' . ( $items['selected'] ? 'checked="checked"' : '' ) . ' ' . $items['xattr'] . '>&nbsp;<label for="' . $id . '">' . $label . '</label>';
	}

	function addCustom($string) { $this->middle .= $string; }

	function getForm() {
		unset($_SESSION[SKEY_FORM_ERRORS]);
		unset($_SESSION[SKEY_FORM_VALUES]);
		return '<form method="' . $this->method . '" action="' . $this->action . '"' . ( $this->enctype ? ' enctype="' . $this->enctype . '"' : '' ) . '>' . $this->middle . '</form>';
	}

	// -----------------------------
	//! handling file uploads

	static function didUpload($name) { return $_FILES[$name]['size']>0 && $_FILES[$name]['tmp_name'] && $_FILES[$name]['name'] && $_FILES[$name]['error']!=UPLOAD_ERR_NO_FILE; } // we check up and down, left and right, that the user actually attempted to upload a file

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
		if (!$_SESSION[SKEY_FORM_ERRORS][$name]) switch ($_FILES[$name]['error']) {
			case UPLOAD_ERR_INI_SIZE :
			case UPLOAD_ERR_FORM_SIZE : self::setSessionError($name, 'Image file is too large. Please upload a smaller file.'); break;
			case UPLOAD_ERR_PARTIAL : self::setSessionError($name, 'Image file was only partially uploaded'); break;
			case UPLOAD_ERR_NO_FILE : self::setSessionError($name, 'No image file was uploaded'); break; // we shouldn't get this one, because we screened for it earlier
			default : self::setSessionError($name, 'An error occurred. Please try again. If you keep getting an error, please contact the web master.'); break;
		}
		return null;
	}

} // end of wFormBuilder definition

function showLineEndings($string) { form_error_log(str_replace(array("\r\n", "\n\r", "\r", ), array("CRLF\n", "LFCR\n", "CR\n", ), $string)); }

function normalizeLineEndings(&$s) { // convert all line endings to UNIX format and reduce to no more than two in succession
		$s = str_replace("\r\n", "\n", $s);
		$s = str_replace("\r", "\n", $s);
		$s = preg_replace("/\n{2,}/", "\n\n", $s);
}

?>
