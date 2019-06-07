<!doctype html>
<!--

	This document is a sample template that works with `Template` class defined
	in `Template.php`. It is meant to be copied and modified slightly for your
	particular project, although not much modification is needed to get started.

-->
<html lang="en-US">
	<head>
		<meta charset="UTF-8" />
		<title>{HEAD-TITLE}</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0" /><!-- BEGIN BLK-HEAD-ELEM -->
		{HEAD-ELEM}<!-- END BLK-HEAD-ELEM -->
	</head>
	<body<!-- BEGIN BLK-BODY-ATTR --> {BODY-ATTR}<!-- END BLK-BODY-ATTR -->>
		<header class="header">{HEADER}</header><!-- BEGIN BLK-NAV-BAR -->
		{NAV-BAR}<!-- END BLK-NAV-BAR -->
		<section class="main"><!-- BEGIN BLK-MAIN-ITEM -->
			{MAIN-ITEM}<!-- END BLK-MAIN-ITEM -->
		</section><!-- BEGIN BLK-FOOTER -->
		<footer class="footer">{FOOTER}</footer><!-- END BLK-FOOTER --><!-- BEGIN WRAP-MESSAGES -->
		<div class="msg" class="mwrap"><!-- BEGIN BLK-MSG -->
			<div id="{MSG-ID}">
				{MSG}
			</div><!-- END BLK-MSG -->
		</div><!-- END WRAP-MESSAGES -->
	</body>
</html>
