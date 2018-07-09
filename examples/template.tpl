<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<!--
	This document is a sample template that works with the `Template` class defined in `Template.php`.
	It is meant to be copied and modified slightly for your particular project, although not much modification is needed to get started.
	There are two TODO's below:
		(1) Include the elements for the site's favicon. I don't typically "parameterize" that, meaning I don't control it with page-specific logic but just hard code it right into the template so it is the same for every page on the site. I've included some example elements below.
		(2) Include common page elements. I've also included samples below. Your header, navigation and footer sections would probably be very similar for all pages, but the "main" section would probably have further template blocks that give you flexibility for the various pages of your site.
-->

<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>{HEAD-TITLE}</title>

		<!-- TODO: Include rel links for favicon. -->
		<!--
		<link rel="shortcut icon" href="/images/logo-favicon.ico" type="image/x-icon"/>
		<link rel="icon" href="/images/logo-favicon.ico" type="image/x-icon" />
		<link rel="apple-touch-icon" href="/images/logo-touch.png" />
		-->

		<!-- BEGIN BLK-STYLE --><link rel="stylesheet" type="text/css" href="{STYLESHEET}" /><!-- END BLK-STYLE -->
		<!-- BEGIN BLK-INLINE-STYLE --><style type="text/css">{STYLE}</style><!-- END BLK-INLINE-STYLE -->
		<!-- BEGIN BLK-SCRIPT --><script type="text/javascript" src="{SCRIPTFILE}"></script><!-- END BLK-SCRIPT -->
		<!-- BEGIN BLK-INLINE-SCRIPT --><script type="text/javascript">{SCRIPT}</script><!-- END BLK-INLINE-SCRIPT -->
		<!-- BEGIN BLK-STYLE-IE --><!--[if lt IE 9]><link rel="stylesheet" type="text/css" href="{STYLESHEET}" /><![endif]--><!-- END BLK-STYLE-IE -->
		<meta name = "viewport" content = "width=device-width" />
	</head>
	<body {BODY-ATTR} onload="<!-- BEGIN BLK-ONLOADS -->{ONLOAD} <!-- END BLK-ONLOADS -->">

		<!-- TODO: Include common page elements. -->
		<!--
			<div id="page-head"> ... </div>
			<div id="page-nav"> ... </div>
			<div id="page-main"> ... probably more template blocks and template variables here ... </div>
			<div id="page-foot">&copy; {YEAR} My Awesome Site</div>
		-->

		<!-- BEGIN BLK-MESSAGES -->
		<!--
			The `Template` class has functionality for stashing messages in the SESSION, and then displaying them on the next loaded page.
			The "msg-wrap" id and "confirm" class below are placeholders. You can choose to set up style rules to flesh them out, or replace them completely, as there are no dependencies within `Template`.
			There is a dependency, however, on the template block `BLK-MSG` which is referenced in the `displayMessage()` method of `Template`.
			Note that for error messages, the default behavior of `Template` is to add an additional class (via the `MSG-XCLASS` template variable) to further style an error message. See the constructor for `Template` and the method `displayMessage()` for more details.
		-->
		<div id="msg-wrap"><!-- BEGIN BLK-MSG --><div class="confirm {MSG-XCLASS}">{MSG}</div><!-- END BLK-MSG --></div>
		<!-- END BLK-MESSAGES -->
	</body>
</html>
