if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_fetchmail.save', function () {
      var input_server = rcube_find_object('_fetchmailserver');
      var input_user = rcube_find_object('_fetchmailuser');
      var input_pass = rcube_find_object('_fetchmailpass');

      if (input_server.value == "" || input_user.value == "" || input_pass.value == "") {
        parent.rcmail.display_message(rcmail.gettext('textempty', 'ispconfig3_fetchmail'), 'error');
      }
      else {
        document.forms.fetchmailform.submit();
      }
    }, true);
  })
}

rcmail.register_command('plugin.ispconfig3_fetchmail.del', function (id) {
  if (confirm(rcmail.gettext('fetchmaildelconfirm', 'ispconfig3_fetchmail'))) {
    rcmail.http_request('plugin.ispconfig3_fetchmail.del', '_id=' + id, true);
    fetchmail_row_del(id);
  }

  return false;
}, true);

function fetchmail_edit(id) {
  window.location.href = '?_task=settings&_action=plugin.ispconfig3_fetchmail&_id=' + id;
}

function fetchmail_row_del(id) {
  document.getElementById('fetch-table').deleteRow(document.getElementById('fetch_' + id).rowIndex);
}