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
defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserHelper;
use Joomla\Plugin\System\Webauthn\CredentialRepository;
use Throwable;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Ajax handler for akaction=challenge
 *
 * Generates the public key and challenge which is used by the browser when logging in with Webauthn. This is the bit
 * which prevents tampering with the login process and replay attacks.
 *
 * @since   4.0.0
 */
trait AjaxHandlerChallenge
{
	/**
	 * Returns the public key set for the user and a unique challenge in a Public Key Credential Request encoded as
	 * JSON.
	 *
	 * @return  string  A JSON-encoded object or JSON-encoded false if the username is invalid or no credentials stored
	 *
	 * @throws  Exception
	 *
	 * @since   4.0.0
	 */
	public function onAjaxWebauthnChallenge()
	{
		// Load the language files
		$this->loadLanguage();

		// Initialize objects
		/** @var CMSApplication $app */
		$app        = Factory::getApplication();
		$input      = $app->input;
		$repository = new CredentialRepository;

		// Retrieve data from the request
		$username  = $input->getUsername('username', '');
		$returnUrl = base64_encode(
			Factory::getApplication()->getSession()->get('plg_system_webauthn.returnUrl', Uri::current())
		);
		$returnUrl = $input->getBase64('returnUrl', $returnUrl);
		$returnUrl = base64_decode($returnUrl);

		// For security reasons the post-login redirection URL must be internal to the site.
		if (!Uri::isInternal($returnUrl))
		{
			// If the URL wasn't internal redirect to the site's root.
			$returnUrl = Uri::base();
		}

		Factory::getApplication()->getSession()->set('plg_system_webauthn.returnUrl', $returnUrl);

		// Do I have a username?
		if (empty($username))
		{
			return json_encode(false);
		}

		// Is the username valid?
		try
		{
			$userId = UserHelper::getUserId($username);
		}
		catch (Exception $e)
		{
			$userId = 0;
		}

		if ($userId <= 0)
		{
			return json_encode(false);
		}

		// Load the saved credentials into an array of PublicKeyCredentialDescriptor objects
		try
		{
			$userEntity  = new PublicKeyCredentialUserEntity(
				'', $repository->getHandleFromUserId($userId), ''
			);
			$credentials = $repository->findAllForUserEntity($userEntity);
		}
		catch (Exception $e)
		{
			return json_encode(false);
		}

		// No stored credentials?
		if (empty($credentials))
		{
			return json_encode(false);
		}

		$registeredPublicKeyCredentialDescriptors = [];

		/** @var PublicKeyCredentialSource $record */
		foreach ($credentials as $record)
		{
			try
			{
				$registeredPublicKeyCredentialDescriptors[] = $record->getPublicKeyCredentialDescriptor();
			}
			catch (Throwable $e)
			{
				continue;
			}
		}

		// Extensions
		$extensions = new AuthenticationExtensionsClientInputs;

		// Public Key Credential Request Options
		$publicKeyCredentialRequestOptions = new PublicKeyCredentialRequestOptions(
			random_bytes(32),
			60000,
			Uri::getInstance()->toString(['host']),
			$registeredPublicKeyCredentialDescriptors,
			PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
			$extensions
		);

		// Save in session. This is used during the verification stage to prevent replay attacks.
		Factory::getApplication()->getSession()->set(
			'plg_system_webauthn.publicKeyCredentialRequestOptions',
			base64_encode(serialize($publicKeyCredentialRequestOptions))
		);
		Factory::getApplication()->getSession()->set(
			'plg_system_webauthn.userHandle',
			$repository->getHandleFromUserId($userId)
		);
		Factory::getApplication()->getSession()->set('plg_system_webauthn.userId', $userId);

		// Return the JSON encoded data to the caller
		return json_encode(
			$publicKeyCredentialRequestOptions,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}
}
