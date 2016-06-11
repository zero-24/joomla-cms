<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_users
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * User controller class.
 *
 * @since  1.6
 */
class UsersControllerUser extends JControllerForm
{
 	/**
	 * Method to terminate an existing user's session.
	 *
	 * @return  void  True if the record can be added, an error object if not.
	 *
	 * @since   3.6
	 */
	public function endSession()
	{
		$userId = $this->input->getInt('user_id');
		$data   = array();

		if ($userId !== 0)
		{
			/** @var UsersModelUser $model */
			$model = $this->getModel('User');
			$model->destroyUsersSessions($userId);
			$data['success'] = JText::_('COM_USERS_LOGGED_OUT_SUCCESS');
		}
		else
		{
			$data['error'] = JText::_('COM_MESSAGES_ERR_INVALID_USER');
		}


		echo new JResponseJson($data);
		jexit();
	}

}
