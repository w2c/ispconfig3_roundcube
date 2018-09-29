window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_fetchmail.save', function () {
        var input_server = rcube_find_object('_fetchmailserver');
        var input_user = rcube_find_object('_fetchmailuser');
        var input_pass = rcube_find_object('_fetchmailpass');

        if (input_server.value == '' || input_user.value == '' || input_pass.value == '') {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_fetchmail.textempty'), 'error');
        }
        else {
            document.forms.fetchmail_form.submit();
        }
    }, true);

    rcmail.register_command('plugin.ispconfig3_fetchmail.del', function (id) {
        rcmail.confirm_dialog(rcmail.gettext('ispconfig3_fetchmail.fetchmaildelconfirm'), 'delete', function(e, ref) {
            var lock = ref.set_busy(true, 'loading');
            ref.http_request('plugin.ispconfig3_fetchmail.del', '_id=' + id, lock);
            document.getElementById('fetch-table').deleteRow(document.getElementById('fetch_' + id).rowIndex);
        });

        return false;
    }, true);
});

function fetchmail_edit(id) {
    rcmail.location_href(rcmail.env.comm_path + '&_action=plugin.ispconfig3_fetchmail&_id=' + id, window, true);
}