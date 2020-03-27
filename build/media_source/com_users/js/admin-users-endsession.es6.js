/**
 * @copyright   Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

Joomla = window.Joomla || {};

((Joomla) => {
  'use strict';

  const endSessionOptions = Joomla.getOptions('js-users-endsession');

  function handleError(textStatus, error) {
    Joomla.renderMessages(Joomla.ajaxErrorsMessages(null, textStatus, error));
  }

  endSession.onclick = function (ev) {
    ev.preventDefault();

    var xhttp = new XMLHttpRequest();

    xhttp.open('POST', endSessionOptions.endsession_url, true);
    xhttp.setRequestHeader('Content-Type', 'application/json;charset=UTF-8"');
    xhttp.send(JSON.stringify({'user_id': endSessionOptions.user_id}));

    xhttp.onreadystatechange = function() {
      // We only react to final state
      if (this.readyState !== 4) {
        return;
      }

      if (this.status !== 200) {
        // Check that Tobias..
        return handleError(this.statusText, {});
      }

      var msg = {};
      var result = JSON.parse(this.responseText);

      if (!result) {
        return;
      }

      window.scrollTo(0, 0);

      if (result.success) {
        msg.success = [result.success]
        Joomla.renderMessages(msg);
        return;
      }

      if (result.error) {
        msg.error = [result.error];
        Joomla.renderMessages(msg);
        return;
      }
    };
  };
})(document, Joomla);
