<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Load JavaScript message titles
Text::script('ERROR');
Text::script('WARNING');
Text::script('NOTICE');
Text::script('MESSAGE');

// Add strings for JavaScript error translations.
Text::script('JLIB_JS_AJAX_ERROR_CONNECTION_ABORT');
Text::script('JLIB_JS_AJAX_ERROR_NO_CONTENT');
Text::script('JLIB_JS_AJAX_ERROR_OTHER');
Text::script('JLIB_JS_AJAX_ERROR_PARSE');
Text::script('JLIB_JS_AJAX_ERROR_TIMEOUT');
?>

<?php if (($this->item->id !== 0) && (Factory::getUser()->id === $this->item->id) || Factory::getUser()->authorise('core.admin')) : ?>
	<?php JHtml::script('com_users/sessionend.js', array('version' => 'auto', 'relative' => true)); ?>
	<?php Factory::getDocument()->addScriptOptions(
		'com_users_end_session',
		array(
			'endsession_url' => addslashes(JUri::base()) . 'index.php?option=com_users&task=user.endSession&format=json&' . JSession::getFormToken() . '=1',
			'user_id' => Factory::getUser()->id,
		)
	); ?>
	<button type="button" class="btn btn-small" id="end_session">
		<span><?php echo JText::_('COM_USERS_END_SESSION_ACTION_BUTTON'); ?></span>
	</button>
<?php endif; ?>
