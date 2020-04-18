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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Plugin\System\Webauthn\Exception\AjaxNonCmsAppException;
use RuntimeException;

/**
 * Allows the plugin to handle AJAX requests in the backend of the site, where com_ajax is not available when we are not
 * logged in.
 *
 * @since   4.0.0
 */
trait AjaxHandler
{
	/**
	 * Processes the callbacks from the passwordless login views.
	 *
	 * Note: this method is called from Joomla's com_ajax or, in the case of backend logins, through the special
	 * onAfterInitialize handler we have created to work around com_ajax usage limitations in the backend.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 *
	 * @since   4.0.0
	 */
	public function onAjaxWebauthn(): void
	{
		// Load the language files
		$this->loadLanguage();

		/** @var CMSApplication $app */
		$app   = Factory::getApplication();
		$input = $app->input;

		// Get the return URL from the session
		$returnURL = Factory::getApplication()->getSession()->get('plg_system_webauthn.returnUrl', Uri::base());
		$result = null;

		try
		{
			//Received AJAX callback

			if (!($app instanceof CMSApplication))
			{
				throw new AjaxNonCmsAppException;
			}

			$input    = $app->input;
			$akaction = $input->getCmd('akaction');
			$token    = Factory::getApplication()->getSession()->getToken();

			if ($input->getInt($token, 0) != 1)
			{
				throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
			}

			// Empty action? No bueno.
			if (empty($akaction))
			{
				throw new RuntimeException(Text::_('PLG_SYSTEM_WEBAUTHN_ERR_AJAX_INVALIDACTION'));
			}

			// Call the plugin event onAjaxWebauthnSomething where Something is the akaction param.
			$eventName = 'onAjaxWebauthn' . ucfirst($akaction);

			$results = $app->triggerEvent($eventName, []);
			$result = null;

			foreach ($results as $r)
			{
				if (is_null($r))
				{
					continue;
				}

				$result = $r;

				break;
			}
		}
		catch (AjaxNonCmsAppException $e)
		{
			//This is not a CMS application", Log::NOTICE);

			$result = null;
		}
		catch (Exception $e)
		{
			// Callback failure, redirecting to $returnURL
			Factory::getApplication()->getSession()->set('plg_system_webauthn.returnUrl', null);
			$app->enqueueMessage($e->getMessage(), 'error');
			$app->redirect($returnURL);

			return;
		}

		if (!is_null($result))
		{
			switch ($input->getCmd('encoding', 'json'))
			{
				default:
				case 'json':
					// Callback complete, returning JSON
					echo json_encode($result);

					break;

				case 'jsonhash':
					// Callback complete, returning JSON inside ### markers
					echo '###' . json_encode($result) . '###';

					break;

				case 'raw':
					// Callback complete, returning raw response
					echo $result;

					break;

				case 'redirect':
					$modifiers = '';

					if (isset($result['message']))
					{
						$type = isset($result['type']) ? $result['type'] : 'info';
						$app->enqueueMessage($result['message'], $type);

						$modifiers = " and setting a system message of type $type";
					}

					if (isset($result['url']))
					{
						// Callback complete, performing redirection to {$result['url']}{$modifiers}
						$app->redirect($result['url']);
					}

					// Callback complete, performing redirection to {$result}{$modifiers}
					$app->redirect($result);

					return;
					break;
			}

			$app->close(200);
		}

		// Null response from AJAX callback, redirecting to $returnURL
		Factory::getApplication()->getSession()->set('plg_system_webauthn.returnUrl', NULL);

		$app->redirect($returnURL);
	}
}
