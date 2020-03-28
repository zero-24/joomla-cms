<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.ID4Me
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$js = "
	(function(document, Joomla)
	{
	  document.addEventListener('DOMContentLoaded', function()
	  {
		if (window.opener && window.opener.Joomla && window.opener.Joomla.ID4Me && window.opener.Joomla.ID4Me.verification)
		{
			window.opener.Joomla.ID4Me.verification('jform_id4me_identifier', 'jform_id4me_issuersub', {issuersub: '" . htmlspecialchars($issuersub, ENT_QUOTES, 'UTF-8') . "'});

			window.self.close();
		}
	  });
	})(document, Joomla);
";

Factory::getDocument()->addScriptDeclaration($js);
?>
