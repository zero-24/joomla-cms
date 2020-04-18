<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Webauthn
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Webauthn\PluginTraits;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Plugin\System\Webauthn\Helper\Joomla;
use Joomla\Registry\Registry;

/**
 * Add extra fields in the User Profile page.
 *
 * This class only injects the custom form fields. The actual interface is rendered through JFormFieldWebauthn.
 *
 * @see JFormFieldWebauthn::getInput()
 *
 * @since   4.0.0
 */
trait UserProfileFields
{
	/**
	 * Adds additional fields to the user editing form
	 *
	 * @param   Form   $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 *
	 * @since   4.0.0
	 */
	public function onContentPrepareForm(Form $form, $data)
	{
		// This feature only applies to HTTPS sites.
		if (!Uri::getInstance()->isSsl())
		{
			return true;
		}

		// Check we are manipulating a valid form.
		if (!($form instanceof Form))
		{
			return true;
		}

		$name = $form->getName();

		if (!in_array($name, ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			return true;
		}

		// Get the user ID
		$id = null;

		if (is_array($data))
		{
			$id = isset($data['id']) ? $data['id'] : null;
		}
		elseif (is_object($data) && is_null($data) && ($data instanceof Registry))
		{
			$id = $data->get('id');
		}
		elseif (is_object($data) && !is_null($data))
		{
			$id = isset($data->id) ? $data->id : null;
		}

		$user = empty($id) ? Factory::getApplication()->getIdentity() : Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

		// Make sure the loaded user is the correct one
		if ($user->id != $id)
		{
			return true;
		}

		// Make sure I am either editing myself OR I am a Super User
		if (!$this->canEditUser($user))
		{
			return true;
		}

		// Add the fields to the form.
		// Injecting WebAuthn Passwordless Login fields in user profile edit page
		Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
		$this->loadLanguage();
		$form->loadFile('webauthn', false);

		return true;
	}

	/**
	 * Is the current user allowed to edit the social login configuration of $user? To do so I must
	 * either be editing my own account OR I have to be a Super User.
	 *
	 * @param   User  $user  The user you want to know if we're allowed to edit
	 *
	 * @return  boolean
	 *
	 * @since   4.0.0
	 */
	private function canEditUser(User $user = null): bool
	{
		// I can edit myself
		if (empty($user))
		{
			return true;
		}

		// Guests can't have social logins associated
		if ($user->guest)
		{
			return false;
		}

		// Get the currently logged in used
		try
		{
			$myUser = Factory::getApplication()->getIdentity();
		}
		catch (Exception $e)
		{
			// Cannot get the application; no user, therefore no edit privileges.
			return false;
		}

		// Same user? I can edit myself
		if ($myUser->id == $user->id)
		{
			return true;
		}

		// To edit a different user I must be a Super User myself. If I'm not, I can't edit another user!
		if (!$myUser->authorise('core.admin'))
		{
			return false;
		}

		// I am a Super User editing another user. That's allowed.
		return true;
	}
}
