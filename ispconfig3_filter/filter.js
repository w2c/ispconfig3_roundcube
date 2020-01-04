window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_filter.save', function () {
        var input_name = rcube_find_object('_filtername');
        var input_searchterm = rcube_find_object('_filtersearchterm');

        if (input_name.value == '' || input_searchterm.value == '') {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_filter.textempty'), 'error');
        }
        else {
            document.forms.filter_form.submit();
        }
    }, true);

    rcmail.register_command('plugin.ispconfig3_filter.del', function (id) {
        rcmail.confirm_dialog(rcmail.gettext('ispconfig3_filter.filterdelconfirm'), 'delete', function(e, ref) {
            var lock = ref.set_busy(true, 'loading');
            ref.http_request('plugin.ispconfig3_filter.del', '_id=' + id, lock);
            document.getElementById('rule-table').deleteRow(document.getElementById('rule_' + id).rowIndex);
        });

        return false;
    }, true);

    if (rcmail.env.action.startsWith('plugin.ispconfig3_filter')) {
        $('input:not(:hidden)').first().focus();
    }
});

function filter_edit(id) {
    rcmail.location_href(rcmail.env.comm_path + '&_action=plugin.ispconfig3_filter&_id=' + id, window, true);
}