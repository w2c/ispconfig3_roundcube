if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_filter.save', function () {
      var input_name = rcube_find_object('_filtername');
      var input_searchterm = rcube_find_object('_filtersearchterm');

      if (input_name.value == "" || input_searchterm.value == "") {
        parent.rcmail.display_message(rcmail.gettext('textempty', 'ispconfig3_filter'), 'error');
      }
      else {
        document.forms.filterform.submit();
      }
    }, true);
  })
}

rcmail.register_command('plugin.ispconfig3_filter.del', function (id) {
  if (confirm(rcmail.gettext('filterdelconfirm', 'ispconfig3_filter'))) {
    rcmail.http_request('plugin.ispconfig3_filter.del', '_id=' + id, true);
    filter_row_del(id);
  }

  return false;
}, true);

function filter_edit(id) {
  window.location.href = '?_task=settings&_action=plugin.ispconfig3_filter&_id=' + id;
}

function filter_row_del(id) {
  document.getElementById('rule-table').deleteRow(document.getElementById('rule_' + id).rowIndex);
}