<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.ID4Me
 *
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Id4me\RP\Model\ClaimRequest;
use Id4me\RP\Model\ClaimRequestList;
use Id4me\RP\Model\OpenIdConfig;
use Id4me\RP\Model\UserInfo;
use Id4me\RP\Service;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Table\Table;
use Joomla\Plugin\System\id4me\ID4MeHttp;
use Joomla\Utilities\ArrayHelper;

/**
 * Plugin class for ID4me
 *
 * @since  1.0
 */
class PlgSystemId4me extends CMSPlugin
{
	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Application object.
	 *
	 * @var    CMSApplication
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app;

	/**
	 * Database object.
	 *
	 * @var    JDatabaseDriver
	 * @since  __DEPLOY_VERSION__
	 */
	protected $db;

	/**
	 * The ID4Me object
	 *
	 * @var type
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $id4me;

	/**
	 * The redirect URL for the login`
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	static $redirectLoginUrl = 'option=com_ajax&plugin=ID4MeLogin&format=raw';

	/**
	 * The url used to trigger the login request to the identity provider
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	static $formActionLoginUrl = 'index.php?option=com_ajax&plugin=ID4MePrepare&format=raw&client={client}';

	/**
	 * The redirect URL for the validation`
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	static $redirectValidateUrl = 'index.php?option=com_ajax&plugin=ID4MeValidation&format=raw';

	/**
	 * Do I need to I inject buttons? Automatically detected (i.e. disabled if I'm already logged in).
	 *
	 * @var     boolean|null
	 * @since   __DEPLOY_VERSION__
	 */
	protected $allowButtonDisplay = null;

	/**
	 * Have I already injected CSS and JavaScript? Prevents double inclusion of the same files.
	 *
	 * @var     boolean
	 * @since   __DEPLOY_VERSION__
	 */
	private $injectedCSSandJS = false;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array   $config    An array that holds the plugin configuration
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
	}

	/**
	 * Creates the ID4Me service
	 *
	 * @return  Service
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function ID4MeHandler(): Service
	{
		if (empty(self::$id4me))
		{
			$http = new ID4MeHttp;

			self::$id4me = new Service($http);
		}

		return self::$id4me;
	}

	/**
	 * Should I allow this plugin to add a WebAuthn login button?
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function mustDisplayButton(): bool
	{
		if (is_null($this->allowButtonDisplay))
		{
			$this->allowButtonDisplay = false;

			/**
			 * Do not add a Id4me login button when are already logged in
			 */
			if (!$this->app->getIdentity()->guest)
			{
				return false;
			}

			/**
			 * Don't try to show a button if we can't figure out if this is a front- or backend page (it's probably a
			 * CLI or custom application).
			 */
			if (!$this->app->isClient('administrator') && !$this->app->isClient('site'))
			{
				return false;
			}

			/**
			 * Only display a button on HTML output
			 */
			if ($this->app->getDocument()->getType() != 'html')
			{
				return false;
			}

			/**
			 * ID4Me should only be limited to HTTPS Sites. This is an intended
			 * security-related limitation, not an issue with this plugin!
			 */
			if (!Uri::getInstance()->isSsl())
			{
				return false;
			}

			// All checks passed; we should allow displaying a WebAuthn login button
			$this->allowButtonDisplay = true;
		}

		return $this->allowButtonDisplay;
	}

	/**
	 * Creates additional login buttons
	 *
	 * @param   string  $form             The HTML ID of the form we are enclosed in
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 *
	 * @see  AuthenticationHelper::getLoginButtons()
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserLoginButtons(string $form): array
	{
		// If we determined we should not inject a button return early
		if (!$this->mustDisplayButton())
		{
			return [];
		}

		// Load the language files
		$this->loadLanguage();

		// Load necessary CSS and Javascript files
		$this->addLoginCSSAndJavascript();

		// Unique ID for this button (allows display of multiple modules on the page)
		$randomId = 'plg_system_id4me-' . UserHelper::genRandomPassword(12) . '-' . UserHelper::genRandomPassword(8);

		// Set up the ajax callback
		$formAction = str_replace(
			'/administrator/',
			'/',
			Route::_(
				str_replace(
					'{client}',
					$this->app->getName(),
					self::$formActionLoginUrl
				) . $this->app->getSession()->getToken() . '=1'
			)
		);

		return [
			[
				'label'      => 'PLG_SYSTEM_ID4ME_LOGIN_LABEL',
				'formAction' => $formAction,
				'id'         => $randomId,
				'image'      => 'plg_system_id4me/id4me-start-login.svg',
				'imageDesc'  => Text::_('PLG_SYSTEM_ID4ME_LOGIN_DESC'),
				'class'      => 'plg_system_id4me_login_button',
			],
		];
	}

	/**
	 * Injects the WebAuthn CSS and Javascript for frontend logins, but only once per page load.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function addLoginCSSAndJavascript(): void
	{
		if ($this->injectedCSSandJS)
		{
			return;
		}

		// Set the "don't load again" flag
		$this->injectedCSSandJS = true;

//		HTMLHelper::_('stylesheet', 'plg_system_id4me/id4me.css', ['relative' => true]);
//		HTMLHelper::_('script', 'plg_system_id4me/id4me.min.js', ['relative' => true]);

		/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
		$wa = $this->app->getDocument()->getWebAssetManager();

		if (!$wa->assetExists('style', 'plg_system_id4me.button'))
		{
			$wa->registerStyle('plg_system_id4me.button', 'plg_system_id4me/id4me.css');
		}

		if (!$wa->assetExists('script', 'plg_system_id4me.login'))
		{
			$wa->registerScript('plg_system_id4me.login', 'plg_system_id4me/id4me.min.js', [], ['defer' => true], ['core']);
		}

		$wa->useStyle('plg_system_id4me.button');
		$wa->useScript('plg_system_id4me.login');

		// Load language strings client-side
		Text::script('PLG_SYSTEM_WEBAUTHN_ERR_CANNOT_FIND_USERNAME');
		Text::script('PLG_SYSTEM_WEBAUTHN_ERR_EMPTY_USERNAME');
		Text::script('PLG_SYSTEM_WEBAUTHN_ERR_INVALID_USERNAME');

		// Store the current URL as the default return URL after login (or failure)
		Joomla::setSessionVar('returnUrl', Uri::current(), 'plg_system_webauthn');
	}


	/**
	 * Using this event we add our JaveScript and CSS code to add the id4me button to the login form.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onBeforeRender(): void
	{
		$allowedLoginClient = (string) $this->params->get('allowed_login_client', 'site');

		// For now we hardcode site as only solution that is working
		$allowedLoginClient = 'site';

		if ((($allowedLoginClient === 'both'
			|| $this->app->isClient($allowedLoginClient))
			&& Factory::getUser()->guest))
		{
			// Load the language string
			Text::script('PLG_SYSTEM_ID4ME_LOGIN_LABEL');

			// Load the layout with the JavaScript and CSS
			require PluginHelper::getLayoutPath('system', 'id4me', 'login');
			return;
		}

		if (in_array($this->app->input->get('option'), ['com_users', 'com_admin'])
			&& in_array($this->app->input->get('view'), ['profile', 'user'])
			&& $this->app->input->get('layout') == 'edit')
		{
			// Load the layout with the JavaScript and CSS
			require PluginHelper::getLayoutPath('system', 'id4me', 'profile');
			return;
		}
	}

	/**
	 * Wrapper method to cache the client loading
	 *
	 * @param   OpenIdConfig  $openIdConfig
	 * @param   boolean $login
	 *
	 * @return  \Id4me\RP\Model\Client;
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getID4MEClient(OpenIdConfig $openIdConfig, $login = false)
	{
		return $this->ID4MeHandler()->register($openIdConfig, $this->app->get('sitename'), $this->getValidateUrl($login), 'web');
	}

	/**
	 * Get a cached version from the client, if available
	 *
	 * @param   type $authorityName  The authority as ID
	 * @param   OpenIdConfig $openIdConfig
	 * @param   type $login
	 *
	 * @return  \Id4me\RP\Model\Client
	 */
	protected function getCachedID4MeClient($authorityName, OpenIdConfig $openIdConfig, $login = false)
	{
		$options = [
			// One month
			'lifetime'     => 2592000,
			'storage'      => 'file',
			'defaultgroup' => 'id4me',
			'caching'      => true
		];

		$cache  = Cache::getInstance('callback', $options);
		$client = $cache->get([$this, 'getID4MEClient'], [$openIdConfig, $login], $authorityName . '-' . (int) $login);

		// Delete cache if loading didn't work
		if ($client === false)
		{
			$cache->clean();

			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_LOGIN_FAILED'), 'error');
			$this->app->redirect('index.php');

			return false;
		}

		return $client;
	}

	/**
	 * This com_ajax Endpoint detects, based on the identifier, the Issuer and redirects to the login page of the Issuer
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onAjaxID4MePrepare()
	{
		$identifier = $this->app->input->getString('id4me-identifier');
		$this->app->setUserState('id4me.identifier', $identifier);

		$joomlaUser = $this->getUserByIdentifier();

		if (!($joomlaUser instanceof User) || (empty($joomlaUser->id) && $this->registrationEnabled() === false))
		{
			// We don't have an user associated to this identifier and we don't allow registration.
			$this->app->enqueueMessage(Text::sprintf('PLG_SYSTEM_ID4ME_NO_JOOMLA_USER_FOR_IDENTIFIER', $identifier), 'error');
			$this->app->redirect('index.php');

			return;
		}

		// Get the client form the current URL
		$requestedLoginClient = (string) Uri::getInstance()->getVar('client');
		$allowedLoginClient   = (string) $this->params->get('allowed_login_client', 'site');

		if ($allowedLoginClient != 'both' && $allowedLoginClient != $requestedLoginClient)
		{
			// We don't allow ID4me login to this client.
			$this->app->enqueueMessage(Text::sprintf('PLG_SYSTEM_ID4ME_NO_LOGIN_CLIENT', $requestedLoginClient), 'error');
			$this->app->redirect('index.php');

			return;
		}

		$this->app->setUserState('id4me.client', $requestedLoginClient);

		$authorityName = $this->ID4MeHandler()->discover($identifier);

		$openIdConfig = $this->ID4MeHandler()->getOpenIdConfig($authorityName);

		$client = $this->getCachedID4MeClient($authorityName, $openIdConfig, true);

		if ($client === false)
		{
			return false;
		}

		$state        = UserHelper::genRandomPassword(100);

		$this->app->setUserState('id4me.clientInfo', (object) $client);
		$this->app->setUserState('id4me.openIdConfig', $openIdConfig);
		$this->app->setUserState('id4me.state', $state);

		$claims = null;

		// We register, so we need some fields
		if (empty($joomlaUser->id))
		{
			$claims = new ClaimRequestList(
                new ClaimRequest('given_name', true),
                new ClaimRequest('family_name', true),
                new ClaimRequest('name', true),
                new ClaimRequest('email', true)
            );
		}

        $authorizationUrl = $this->ID4MeHandler()->getAuthorizationUrl($openIdConfig,
				$client->getClientId(), $identifier, $client->getActiveRedirectUri(),
				$state, null, $claims
        );

		if (!$authorizationUrl)
		{
			// We don't have an authorization URL so we can't do anything here.
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_NO_AUTHORIZATION_URL'), 'error');
			$this->app->redirect('index.php');
		}

		$this->app->redirect($authorizationUrl);
	}

	/**
	 * This com_ajax Endpoint detects, based on the identifier, the Issuer and redirects to the login page of the Issuer
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onAjaxID4MeValidation()
	{
		$identifier = $this->app->input->getString('id4me-identifier');
		$this->app->setUserState('id4me.identifier', $identifier);

		$authorityName = $this->ID4MeHandler()->discover($identifier);

		$openIdConfig = $this->ID4MeHandler()->getOpenIdConfig($authorityName);

		$client = $this->getCachedID4MeClient($authorityName, $openIdConfig);

		if ($client === false)
		{
			return false;
		}

		$state = UserHelper::genRandomPassword(100);

		$this->app->setUserState('id4me.clientInfo', (object) $client);
		$this->app->setUserState('id4me.openIdConfig', $openIdConfig);
		$this->app->setUserState('id4me.state', $state);

        $authorizationUrl = $this->ID4MeHandler()->getAuthorizationUrl($openIdConfig, $client->getClientId(), $identifier, $client->getActiveRedirectUri(), $state);

		if (!$authorizationUrl)
		{
			// We don't have an authorization URL so we can't do anything here.
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_NO_AUTHORIZATION_URL'), 'error');
			$this->app->redirect('index.php');
		}

		$this->app->redirect($authorizationUrl);
	}

	/**
	 * Endpoint for the final login/registration process
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onAjaxID4MeLogin()
	{
		$code = $this->app->input->get('code');
		$state = $this->app->input->get('state');

		$isAdmin = $this->app->getUserState('id4me.client') === 'administrator';

		$client = $this->app->getUserState('id4me.clientInfo');
		$openIdConfig = $this->app->getUserState('id4me.openIdConfig');
		$identifier = $this->app->getUserState('id4me.identifier');

		// Prevent __PHP_Incomplete_Class
		$client = unserialize(serialize($client));
		$openIdConfig = unserialize(serialize($openIdConfig));

		$authorizedAccessTokens = $this->ID4MeHandler()->getAuthorizationTokens($openIdConfig, $code, $client);

		$decodedToken = $authorizedAccessTokens->getIdTokenDecoded();

		$joomlaUser = $this->getUserByIdentifier();

		$home = $this->app->getMenu()->getDefault();

		if (!($joomlaUser instanceof User) || $state != $this->app->getUserState('id4me.state') || $decodedToken->getId4meIdentifier() != $identifier)
		{
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_LOGIN_FAILED'), 'error');
			$this->app->redirect(Route::link($isAdmin ? 'administrator' : 'site', 'index.php' . ($isAdmin ? '' : '?Itemid=' . (int) $home->id), false));
		}

		$userInfo = $this->ID4MeHandler()->getUserInfo($openIdConfig, $client, $authorizedAccessTokens);

		// The user does not exists than lets register him in the frontend
		if (!$isAdmin && empty($joomlaUser->id) && $this->registrationEnabled())
		{
			$joomlaUser = $this->registerUser($userInfo);
		}

		$issuersub = $decodedToken->getIss() . '#' . $decodedToken->getSub();

		if ($joomlaUser instanceof User && $joomlaUser->id > 0 && $issuersub == $joomlaUser->id4me_issuersub)
		{
			// Dirty hack to allow backend registration
			if ($isAdmin)
			{
				Factory::$application = CMSApplication::getInstance('administrator');
			}

			$app = $this->app;

			$dispatcher = new JEventDispatcher;

			// Load user plugins
			PluginHelper::importPlugin('user', null, true, $dispatcher);

			// Login options
			$options = array(
				'autoregister' => false,
				'remember'     => false,
				'action'       => 'core.login.site',
				'redirect_url' => Route::_('index.php?Itemid=' . (int) $home->id, false),
			);

			if ($isAdmin)
			{
				$options['action']       = 'core.login.admin';
				$options['group']        = 'Public Backend';

				// Router is broken for subfolders, so we have to create the path manually
				$options['redirect_url'] = rtrim(Uri::base(true), '/') . '/administrator/index.php?option=com_cpanel';
			}

			// OK, the credentials are authenticated and user is authorised. Let's fire the onLogin event.
			$results = $dispatcher->trigger('onUserLogin', array((array) $joomlaUser, $options));

			/*
			 * If any of the user plugins did not successfully complete the login routine
			 * then the whole method fails.
			 *
			 * Any errors raised should be done in the plugin as this provides the ability
			 * to provide much more information about why the routine may have failed.
			 */
			if (in_array(false, $results, true) == false)
			{
				$options['user'] = Factory::getUser();
				$options['responseType'] = 'id4me';

				// The user is successfully logged in. Run the after login events
				$dispatcher->trigger('onUserAfterLogin', array($options));
				$app->redirect($options['redirect_url']);

				return;
			}
		}

		// We don't have an authorization URL so we can't do anything here.
		$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_LOGIN_FAILED'), 'error');
		$this->app->redirect(Route::link($isAdmin ? 'administrator' : 'site', 'index.php' . ($isAdmin ? '' : '?Itemid=' . (int) $home->id), false));
	}

	/**
	 * Endpoint for the final login/registration process
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onAjaxID4MeVerification()
	{
		$code = $this->app->input->get('code');
		$state = $this->app->input->get('state');

		$client = $this->app->getUserState('id4me.clientInfo');
		$openIdConfig = $this->app->getUserState('id4me.openIdConfig');
		$identifier = $this->app->getUserState('id4me.identifier');

		// Prevent __PHP_Incomplete_Class
		$client = unserialize(serialize($client));
		$openIdConfig = unserialize(serialize($openIdConfig));

		$authorizedAccessTokens = $this->ID4MeHandler()->getAuthorizationTokens($openIdConfig, $code, $client);

		$decodedToken = $authorizedAccessTokens->getIdTokenDecoded();

		$home = $this->app->getMenu()->getDefault();

		if ($state != $this->app->getUserState('id4me.state') || $decodedToken->getId4meIdentifier() != $identifier)
		{
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_LOGIN_FAILED'), 'error');
			$this->app->redirect(Route::_('index.php?Itemid=' . (int) $home->id, false));
		}

		$issuersub = $decodedToken->getIss() . '#' . $decodedToken->getSub();

		require PluginHelper::getLayoutPath('system', 'id4me', 'verification');
	}

	/**
	 * Add id4me field to the user edit form
	 *
	 * @param   JForm  $form  The form to be altered.
	 * @param   mixed  $data  The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentPrepareForm(JForm $form, $data)
	{
		// Check for the user edit forms
		if (!in_array($form->getName(), ['com_admin.profile', 'com_users.user', 'com_users.profile', 'com_users.registration']))
		{
			return true;
		}

		$form->load('
			<form>
				<fieldset name="id4me" label="PLG_SYSTEM_ID4ME_FIELDSET_LABEL">
					<field
						name="id4me_identifier"
						type="text"
						label="PLG_SYSTEM_ID4ME_IDENTIFIER_LABEL"
						description="PLG_SYSTEM_ID4ME_IDENTIFIER_DESC"
					/>
					<field
						name="id4me_issuersub"
						type="hidden"
						label="PLG_SYSTEM_ID4ME_ISSUERSUB_LABEL"
						description="PLG_SYSTEM_ID4ME_ISSUERSUB_DESC"
					/>
				</fieldset>
			</form>'
		);
	}

	/**
	 * Runs on content preparation
	 *
	 * @param   string  $context  The context for the data
	 * @param   object  $data     An object containing the data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onContentPrepareData($context, $data)
	{
		// Check for the user edit forms
		if (!in_array($context, $this->supportedContext) || !is_object($data) || empty($data->id))
		{
			return true;
		}

		try
		{
			$id4mes = $this->loadID4MeData($data->id);

			foreach ($id4mes as $id4me)
			{
				$id4mekey = str_replace('id4me.', 'id4me_', $id4me->profile_key);

				$data->$id4mekey = $id4me->profile_value;
			}
		}
		catch (\RuntimeException $e)
		{
			// We can not read the field but this is not critial the field will just be empty
		}
	}

	/**
	 * Method is called before user data is stored in the database
	 *
	 * @param   array    $user   Holds the old user data.
	 * @param   boolean  $isnew  True if a new user is stored.
	 * @param   array    $data   Holds the new user data.
	 *
	 * @return  boolean
	 * @throws  InvalidArgumentException When the ID4me Identifier is already assigned to another user.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function onUserBeforeSave($user, $isnew, $data)
	{
		$identifier = $data['id4me_identifier'];

		if (!empty($identifier))
		{
			$query = $this->db->getQuery(true)
				->select('*')
				->from('#__user_profiles')
				->where($this->db->quoteName('user_id') . ' <> ' . (int) $data['id'])
				->where($this->db->quoteName('profile_key') . ' = ' . $this->db->quote('id4me.identifier'))
				->where($this->db->quoteName('profile_value') . ' = ' . $this->db->quote($identifier));

			$this->db->setQuery($query);

			try
			{
				$rows = (int) $this->db->loadObjectList();
			}
			catch (\RuntimeException $e)
			{
				$this->app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');

				return false;
			}

			foreach ($rows as $row)
			{
				if ($row->profile_value === $identifier && (empty($data['id'] || $row->user_id != $data['id'])))
				{
					// The identifier is already used
					$this->app->enqueueMessage(Text::sprintf('PLG_SYSTEM_ID4ME_IDENTIFIER_ALREADY_USED', $identifier));

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Saves user id4me data to the database
	 *
	 * @param   array    $data    entered user data
	 * @param   boolean  $isNew   true if this is a new user
	 * @param   boolean  $result  true if saving the user worked
	 * @param   string   $error   error message
	 *
	 * @return  boolean
	 *
	 *  @since   __DEPLOY_VERSION__
	 */
	public function onUserAfterSave($data, $isNew, $result, $error)
	{
		$user_id     = ArrayHelper::getValue($data, 'id', 0, 'int');
		$identifier  = ArrayHelper::getValue($data, 'id4me_identifier');
		$issuersub   = ArrayHelper::getValue($data, 'id4me_issuersub');

		$this->deleteID4ME($user_id);

		$entry1 = new stdClass;

		$entry1->user_id = (int) $user_id;
		$entry1->profile_key = 'id4me.identifier';
		$entry1->profile_value = $identifier;
		$entry1->ordering = 1;

		$entry2 = new stdClass;

		$entry2->user_id = (int) $user_id;
		$entry2->profile_key = 'id4me.issuersub';
		$entry2->profile_value = $issuersub;
		$entry2->ordering = 1;

		try
		{
			$this->db->insertObject('#__user_profiles', $entry1);
			$this->db->insertObject('#__user_profiles', $entry2);
		}
		catch (\RuntimeException $e)
		{
			$this->app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');

			return false;
		}

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array    $user     Holds the user data
	 * @param   boolean  $success  True if user was succesfully stored in the database
	 * @param   string   $msg      Message
	 *
	 * @return  boolean
	 */
	public function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success)
		{
			return false;
		}

		$user_id = ArrayHelper::getValue($user, 'id', 0, 'int');

		if ($user_id)
		{
			$this->deleteID4ME($user_id);
		}

		return true;
	}

	/**
	 * Deletes all id4me profile fields
	 *
	 * @param   int  $user_id  The user ID
	 *
	 * @return  boolean  True on success otherwise false
	 */
	protected function deleteID4ME($user_id)
	{
		try
		{
			$query = $this->db->getQuery(true)
					->delete($this->db->quoteName('#__user_profiles'))
					->where($this->db->quoteName('user_id') . ' = ' . (int) $user_id)
					->where($this->db->quoteName('profile_key') . ' LIKE ' . $this->db->quote($this->db->escape('id4me.', true) . '%', false));

			$this->db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			$this->_subject->setError($e->getMessage());

			return false;
		}

		return true;
	}

	/**
	 * Get the Joomla User by Id4Me Identifier
	 *
	 * @return  mixed  Returns the Joomla User for the Id4Me Identifier or false in case of an error
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getUserByIdentifier()
	{
		$query = $this->db->getQuery(true)
				->select($this->db->quoteName(['p.user_id']))
				->from($this->db->quoteName('#__user_profiles', 'p'))
				->from($this->db->quoteName('#__users', 'u'))
				->where($this->db->quoteName('u.id') . ' = ' . $this->db->quoteName('p.user_id'))
				->where($this->db->quoteName('p.profile_value') . ' = ' . $this->db->quote($this->app->getUserState('id4me.identifier')))
				->where($this->db->quoteName('p.profile_key') . ' = ' . $this->db->quote('id4me.identifier'));

		$this->db->setQuery($query);

		try
		{
			$userId = (int) $this->db->loadResult();
			$rows = (int) $this->db->getNumRows($this->db->execute());
		}
		catch (\RuntimeException $e)
		{
			$this->app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');

			return false;
		}

		if ($rows > 1)
		{
			// For some reason we have more than one result this is an critial error
			$this->app->enqueueMessage(Text::_('PLG_SYSTEM_ID4ME_IDENTIFIER_AMBIGOUOUS'), 'error');

			return false;
		}

		return $this->loadUser($userId);
	}

	protected function loadUser($user_id)
	{
		$user = Factory::getUser($user_id);

		$user->id4me_identifier = '';
		$user->id4me_issuersub = '';

		if (!empty($user->id))
		{
			$id4mes = $this->loadID4MeData($user_id);

			foreach ($id4mes as $id4me)
			{
				$id4mekey = str_replace('id4me.', 'id4me_', $id4me->profile_key);

				$user->$id4mekey = $id4me->profile_value;
			}
		}

		return $user;
	}

	protected function loadID4MeData($user_id)
	{
		$query = $this->db->getQuery(true)
				->select($this->db->quoteName(['profile_value', 'profile_key']))
				->from($this->db->quoteName('#__user_profiles'))
				->where($this->db->quoteName('user_id') . ' = ' . (int) $user_id)
				->where($this->db->quoteName('profile_key') . ' IN(' . implode(',', $this->db->quote(['id4me.identifier', 'id4me.issuersub'])) . ')');

		$this->db->setQuery($query);

		try
		{
			return $this->db->loadObjectList();
		}
		catch (\RuntimeException $e)
		{
			// We can not read the field but this is not critial the field will just be empty
		}

		return [];
	}

	/**
	 * Register the user as Joomla User with the data provided
	 *
	 * @param   UserInfo   $userInfo   The user Info result from the id4me API
	 *
	 * @return  User  Returns the newly created Joomla User or false in case there is an error
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function registerUser(UserInfo $userInfo)
	{
		$params     = ComponentHelper::getParams('com_users');
		$table      = Table::getInstance('User', 'JTable');
		$identifier = $this->app->getUserState('id4me.identifier');

		$user = [
			'username' => $identifier,
			'email' => $userInfo->getEmailVerified() ?: $userInfo->getEmail(),
			'name' => $userInfo->getName() ?: $userInfo->getGivenName() . ' ' . $userInfo->getFamilyName()
		];

		$user['groups'] = [$params->get('new_usertype', $params->get('guest_usergroup', 1))];

		$result = $table->save($user);

		if ($result)
		{
			$idprofile = new stdClass;

			$idprofile->user_id = (int) $table->id;
			$idprofile->profile_key = 'id4me.identifier';
			$idprofile->profile_value = $identifier;
			$idprofile->ordering = 1;

			$issuersub = new stdClass;

			$issuersub->user_id = (int) $table->id;
			$issuersub->profile_key = 'id4me.issuersub';
			$issuersub->profile_value = $userInfo->getIss() . '#' . $userInfo->getSub();
			$issuersub->ordering = 1;

			try
			{
				$this->db->insertObject('#__user_profiles', $idprofile);
				$this->db->insertObject('#__user_profiles', $issuersub);
			}
			catch (\RuntimeException $e)
			{
				$this->app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');

				return false;
			}
		}

		return $this->loadUser($table->id);
	}

	/**
	 * Check if the id4me registation is allowed or not
	 *
	 * @return  bool  True if the registration is enabeld false in other cases
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function registrationEnabled()
	{
		// Get the global value as boolean
		$comUsersRegistation = (bool) ComponentHelper::getParams('com_users')->get('allowUserRegistration', 0);

		// Get the plugin configuration
		$id4MeRegistation = $this->params->get('allow_registration', '');

		// We are in `Use Global` mode so lets return the global setting
		if (!is_numeric($id4MeRegistation))
		{
			return $comUsersRegistation;
		}

		// Id4Me registration is enabled and the global setting too.
		if ($id4MeRegistation > 0 && $comUsersRegistation)
		{
			return true;
		}

		// In all other cases we don't enable registration
		return false;
	}

	/**
	 * Returns the validation URL
	 *
	 * @return   string  The validation URL
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getValidateUrl($login = false)
	{
		$validateUrl = Uri::getInstance();
		$validateUrl->setQuery(self::$redirectLoginUrl);
		$validateUrl->setScheme('https');

		if ($login)
		{
			$validateUrl->setVar('plugin', 'ID4MeLogin');
		}
		else
		{
			$validateUrl->setVar('plugin', 'ID4MeVerification');
		}

		return $validateUrl->toString();
	}
}
