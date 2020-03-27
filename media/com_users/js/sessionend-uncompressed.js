/**
 * @package         Joomla.JavaScript
 * @copyright       Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Calls the sending process of the config class
 */
jQuery(document).ready(function ($)
{
	$('#end_session').click(function ()
	{
		var endSessionOptions = Joomla.getOptions('com_users_end_session');

		$.ajax({
			method: "POST",
			url: endSessionOptions.endsession_url,
			data: {'user_id': endSessionOptions.user_id},
			dataType: "json"
		})
		.fail(function (jqXHR, textStatus, error)
		{
			Joomla.renderMessages(Joomla.ajaxErrorsMessages(jqXHR, textStatus, error));
		})
		.done(function (response)
		{
			var msg = {};
			if (response.data)
			{
				if (response.data.hasOwnProperty('success'))
				{
					msg.success = [response.data.success];
				}

				if (response.data.hasOwnProperty('error'))
				{
					msg.error = [response.data.error];
					Joomla.renderMessages(msg);
					window.scrollTo(0, 0);
				}

				Joomla.renderMessages(msg);
			}

			window.scrollTo(0, 0);
		});
	});
});
