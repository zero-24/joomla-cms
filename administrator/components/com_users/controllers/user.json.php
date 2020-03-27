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
use Joomla\CMS\Response\JsonResponse;

/**
 * User controller class.
 *
 * @since  __DEPLOY_VERSION__
 */
class UsersControllerUser extends JControllerForm
{
 	/**
	 * Method to terminate an existing user's session.
	 *
	 * @return  void  True if the record can be added, an error object if not.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function endSession()
	{
		$userId = (int) $this->input->getInt('user_id');
		$data   = array();

		// Make sure I can only do this when it is my own accout or I'm Superuser
		if (($userId !== 0) && (Factory::getUser()->id === $userId) || Factory::getUser()->authorise('core.admin'))
		{
			$model = $this->getModel('User');
			$model->destroyUsersSessions($userId);
			$data['success'] = Text::_('COM_USERS_LOGGED_OUT_SUCCESS');
		}

		$data['error'] = Text::_('COM_MESSAGES_ERR_INVALID_USER');

		echo new JsonResponse($data);
		jexit();
	}

}
