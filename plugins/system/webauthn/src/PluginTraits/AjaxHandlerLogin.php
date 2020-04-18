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

use CBOR\Decoder;
use CBOR\OtherObject\OtherObjectManager;
use CBOR\Tag\TagObjectManager;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Plugin\System\Webauthn\CredentialRepository;
use Laminas\Diactoros\ServerRequestFactory;
use RuntimeException;
use Throwable;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;

/**
 * Ajax handler for akaction=login
 *
 * Verifies the response received from the browser and logs in the user
 *
 * @since  4.0.0
 */
trait AjaxHandlerLogin
{
	/**
	 * Returns the public key set for the user and a unique challenge in a Public Key Credential Request encoded as
	 * JSON.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @since   4.0.0
	 */
	public function onAjaxWebauthnLogin(): void
	{
		// Load the language files
		$this->loadLanguage();

		$returnUrl = Factory::getApplication()->getSession()->get('plg_system_webauthn.returnUrl', Uri::base());
		$userId    = Factory::getApplication()->getSession()->get('plg_system_webauthn.userId', 0);

		try
		{
			// Sanity check
			if (empty($userId))
			{
				throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_CREATE_INVALID_LOGIN_REQUEST'));
			}

			// Make sure the user exists
			$user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

			if ($user->id != $userId)
			{
				throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_CREATE_INVALID_LOGIN_REQUEST'));
			}

			// Validate the authenticator response
			$this->validateResponse();

			// Logging in the user
			$this->loginUser((int) $userId);
		}
		catch (Throwable $e)
		{
			Factory::getApplication()->getSession()->set('plg_system_webauthn.publicKeyCredentialRequestOptions', null);
			Factory::getApplication()->getSession()->set('plg_system_webauthn.userHandle', null);

			$response                = new AuthenticationResponse;
			$response->status        = Authentication::STATUS_UNKNOWN;
			$response->error_message = $e->getMessage();

			// This also enqueues the login failure message for display after redirection. Look for JLog in that method.
			$this->processLoginFailure($response, null, 'system');
		}
		finally
		{
			/**
			 * This code needs to run no matter if the login succeeded or failed. It prevents replay attacks and takes
			 * the user back to the page they started from.
			 */

			// Remove temporary information for security reasons
			Factory::getApplication()->getSession()->set('plg_system_webauthn.publicKeyCredentialRequestOptions', null);
			Factory::getApplication()->getSession()->set('plg_system_webauthn.userHandle', null);
			Factory::getApplication()->getSession()->set('plg_system_webauthn.returnUrl', null);
			Factory::getApplication()->getSession()->set('plg_system_webauthn.userId', null);

			// Redirect back to the page we were before.
			Factory::getApplication()->redirect($returnUrl);
		}
	}

	/**
	 * Have Joomla! process a login failure
	 *
	 * @param   AuthenticationResponse  $response    The Joomla! auth response object
	 * @param   AbstractApplication     $app         The application we are running in. Skip to
	 *                                               auto-detect (recommended).
	 * @param   string                  $logContext  Logging context (plugin name). Default:
	 *                                               system.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 *
	 * @since   4.0.0
	 */
	private function processLoginFailure(AuthenticationResponse $response,
		AbstractApplication $app = null,
		string $logContext = 'system'
	)
	{
		// Import the user plugin group.
		PluginHelper::importPlugin('user');

		if (!is_object($app))
		{
			$app = Factory::getApplication();
		}

		// Trigger onUserLoginFailure Event.
		/** @var CMSApplication $app */
		$app->triggerEvent('onUserLoginFailure', [(array) $response]);

		// If status is success, any error will have been raised by the user plugin
		$expectedStatus = Authentication::STATUS_SUCCESS;

		if ($response->status !== $expectedStatus)
		{
			// Everything logged in the 'jerror' category ends up being enqueued in the application message queue.
			Log::add($response->error_message, Log::WARNING, 'jerror');
		}
		else
		{
			/**
			 * The login failure was caused by a third party user plugin but it did not
			 * return any further information. Good luck figuring this one out...
			 */
		}

		return false;
	}

	/**
	 * Logs in a user to the site, bypassing the authentication plugins.
	 *
	 * @param   int                  $userId  The user ID to log in
	 * @param   AbstractApplication  $app     The application we are running in. Skip to
	 *                                        auto-detect (recommended).
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   4.0.0
	 */
	private function loginUser(int $userId, AbstractApplication $app = null): void
	{
		// Fake a successful login message
		if (!is_object($app))
		{
			$app = Factory::getApplication();
		}

		$isAdmin = $app->isClient('administrator');
		/** @var User $user */
		$user    = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

		// Does the user account have a pending activation?
		if (!empty($user->activation))
		{
			throw new RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
		}

		// Is the user account blocked?
		if ($user->block)
		{
			throw new RuntimeException(Text::_('JGLOBAL_AUTH_ACCESS_DENIED'));
		}

		$statusSuccess = Authentication::STATUS_SUCCESS;

		$response           = new AuthenticationResponse;
		$response->status   = $statusSuccess;
		$response->username = $user->username;
		$response->fullname = $user->name;
		$response->error_message = '';
		$response->language      = $user->getParam('language');
		$response->type          = 'Passwordless';

		if ($isAdmin)
		{
			$response->language = $user->getParam('admin_language');
		}

		/**
		 * Set up the login options.
		 *
		 * The 'remember' element forces the use of the Remember Me feature when logging in with Webauthn, as the
		 * users would expect.
		 *
		 * The 'action' element is actually required by plg_user_joomla. It is the core ACL action the logged in user
		 * must be allowed for the login to succeed. Please note that front-end and back-end logins use a different
		 * action. This allows us to provide the social login button on both front- and back-end and be sure that if a
		 * used with no backend access tries to use it to log in Joomla! will just slap him with an error message about
		 * insufficient privileges - the same thing that'd happen if you tried to use your front-end only username and
		 * password in a back-end login form.
		 */
		$options = [
			'remember' => true,
			'action'   => 'core.login.site',
		];

		if ($isAdmin)
		{
			$options['action'] = 'core.login.admin';
		}

		// Run the user plugins. They CAN block login by returning boolean false and setting $response->error_message.
		PluginHelper::importPlugin('user');

		/** @var CMSApplication $app */
		$results = $app->triggerEvent('onUserLogin', [(array) $response, $options]);

		// If there is no boolean FALSE result from any plugin the login is successful.
		if (in_array(false, $results, true) == false)
		{
			// Set the user in the session, letting Joomla! know that we are logged in.
			$app->getSession()->set('user', $user);

			// Trigger the onUserAfterLogin event
			$options['user']         = $user;
			$options['responseType'] = $response->type;

			// The user is successfully logged in. Run the after login events
			$app->triggerEvent('onUserAfterLogin', [$options]);

			return;
		}

		// If we are here the plugins marked a login failure. Trigger the onUserLoginFailure Event.
		$app->triggerEvent('onUserLoginFailure', [(array) $response]);

		// Log the failure
		Log::add($response->error_message, Log::WARNING, 'jerror');

		// Throw an exception to let the caller know that the login failed
		throw new RuntimeException($response->error_message);
	}


	/**
	 * Validate the authenticator response sent to us by the browser.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   4.0.0
	 */
	private function validateResponse(): void
	{
		// Initialize objects
		/** @var CMSApplication $app */
		$app                  = Factory::getApplication();
		$input                = $app->input;
		$credentialRepository = new CredentialRepository;

		// Retrieve data from the request and session
		$data = $input->getBase64('data', '');
		$data = base64_decode($data);

		if (empty($data))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		$publicKeyCredentialRequestOptions = $this->getPKCredentialRequestOptions();

		// Cose Algorithm Manager
		$coseAlgorithmManager = new Manager;
		$coseAlgorithmManager->add(new ECDSA\ES256);
		$coseAlgorithmManager->add(new ECDSA\ES512);
		$coseAlgorithmManager->add(new EdDSA\EdDSA);
		$coseAlgorithmManager->add(new RSA\RS1);
		$coseAlgorithmManager->add(new RSA\RS256);
		$coseAlgorithmManager->add(new RSA\RS512);

		// Create a CBOR Decoder object
		$otherObjectManager = new OtherObjectManager;
		$tagObjectManager   = new TagObjectManager;
		$decoder            = new Decoder($tagObjectManager, $otherObjectManager);

		// Attestation Statement Support Manager
		$attestationStatementSupportManager = new AttestationStatementSupportManager;
		$attestationStatementSupportManager->add(new NoneAttestationStatementSupport);
		$attestationStatementSupportManager->add(new FidoU2FAttestationStatementSupport($decoder));

		/*
		$attestationStatementSupportManager->add(
			new AndroidSafetyNetAttestationStatementSupport(
				HttpFactory::getHttp(), 'GOOGLE_SAFETYNET_API_KEY', new RequestFactory
			)
		);
		*/
		$attestationStatementSupportManager->add(new AndroidKeyAttestationStatementSupport($decoder));
		$attestationStatementSupportManager->add(new TPMAttestationStatementSupport);
		$attestationStatementSupportManager->add(new PackedAttestationStatementSupport($decoder, $coseAlgorithmManager));

		// Attestation Object Loader
		$attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager, $decoder);

		// Public Key Credential Loader
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader, $decoder);

		// The token binding handler
		$tokenBindingHandler = new TokenBindingNotSupportedHandler;

		// Extension Output Checker Handler
		$extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler;

		// Authenticator Assertion Response Validator
		$authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
			$credentialRepository,
			$decoder,
			$tokenBindingHandler,
			$extensionOutputCheckerHandler,
			$coseAlgorithmManager
		);

		// We init the Symfony Request object
		$request = ServerRequestFactory::fromGlobals();

		// Load the data
		$publicKeyCredential = $publicKeyCredentialLoader->load($data);
		$response            = $publicKeyCredential->getResponse();

		// Check if the response is an Authenticator Assertion Response
		if (!$response instanceof AuthenticatorAssertionResponse)
		{
			throw new RuntimeException('Not an authenticator assertion response');
		}

		// Check the response against the attestation request
		$userHandle = Factory::getApplication()->getSession()->get('plg_system_webauthn.userHandle', null);
		/** @var AuthenticatorAssertionResponse $authenticatorAssertionResponse */
		$authenticatorAssertionResponse = $publicKeyCredential->getResponse();
		$authenticatorAssertionResponseValidator->check(
			$publicKeyCredential->getRawId(),
			$authenticatorAssertionResponse,
			$publicKeyCredentialRequestOptions,
			$request,
			$userHandle
		);
	}

	/**
	 * Retrieve the public key credential request options saved in the session. If they do not exist or are corrupt it
	 * is a hacking attempt and we politely tell the hacker to go away.
	 *
	 * @return  PublicKeyCredentialRequestOptions
	 *
	 * @since   4.0.0
	 */
	private function getPKCredentialRequestOptions(): PublicKeyCredentialRequestOptions
	{
		$encodedOptions = Factory::getApplication()->getSession()->get('plg_system_webauthn.publicKeyCredentialRequestOptions', null);

		if (empty($encodedOptions))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		try
		{
			$publicKeyCredentialCreationOptions = unserialize(base64_decode($encodedOptions));
		}
		catch (Exception $e)
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		if (!is_object($publicKeyCredentialCreationOptions)
			|| !($publicKeyCredentialCreationOptions instanceof PublicKeyCredentialRequestOptions))
		{
			throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_CREATE_INVALID_LOGIN_REQUEST'));
		}

		return $publicKeyCredentialCreationOptions;
	}
}
