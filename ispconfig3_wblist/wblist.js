if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_wblist.save', function () {
      var input_email = rcube_find_object('_wblistemail');

      if (input_email.value == "") {
        parent.rcmail.display_message(rcmail.gettext('textempty', 'ispconfig3_wblist'), 'error');
      }
      else {
        document.forms.wblistform.submit();
      }
    }, true);
  })
}

rcmail.register_command('plugin.ispconfig3_wblist.del', function (id, type) {
  if (confirm(rcmail.gettext('wblistdelconfirm', 'ispconfig3_wblist'))) {
    rcmail.http_request('plugin.ispconfig3_wblist.del', '_id=' + id + '&_type=' + type, true);
    wb_row_del(id);
  }

  return false;
}, true);

function wb_edit(id, type) {
  window.location.href = '?_task=settings&_action=plugin.ispconfig3_wblist&_id=' + id + '&_type=' + type;
}

function wb_row_del(id) {
  document.getElementById('rule-table').deleteRow(document.getElementById('rule_' + id).rowIndex);
}