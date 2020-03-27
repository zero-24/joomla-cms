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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

?>

<?php if (($this->item->id !== 0) && (Factory::getUser()->id === $this->item->id) || Factory::getUser()->authorise('core.admin')) : ?>
	<?php HTMLHelper::script('com_users/admin-users-endsession.js', ['version' => 'auto', 'relative' => true]); ?>
	<?php Factory::getDocument()->addScriptOptions(
		'js-users-endsession',
		array(
			'endsession_url' => addslashes(Uri::base()) . 'index.php?option=com_users&task=user.endSession&format=json&' . Session::getFormToken() . '=1',
			'user_id' => Factory::getUser()->id,
		)
	); ?>
	<button type="button" class="btn btn-small" id="end_session">
		<span>
			<?php echo Text::_('COM_USERS_END_SESSION_ACTION_BUTTON'); ?>
		</span>
	</button>
<?php endif; ?>
