window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_wblist.save', function () {
        var input_email = rcube_find_object('_wblistemail');

        if (input_email.value == '') {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_wblist.textempty'), 'error');
        }
        // Test for user@exmaple.com or @exmaple.com syntax.
        else if (!rcube_check_email(input_email.value, false) && !rcube_check_email('example' + input_email.value, false)) {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_wblist.invalidaddress'), 'error');
            input_email.focus();
        }
        else {
            document.forms.wblist_form.submit();
        }
    }, true);

    rcmail.register_command('plugin.ispconfig3_wblist.del', function (id, type) {
        rcmail.confirm_dialog(rcmail.gettext('ispconfig3_wblist.wblistdelconfirm'), 'delete', function(e, ref) {
            var lock = ref.set_busy(true, 'loading');
            ref.http_request('plugin.ispconfig3_wblist.del', '_id=' + id + '&_type=' + type, lock);
            document.getElementById('rule-table').deleteRow(document.getElementById('rule_' + id).rowIndex);
        });

        return false;
    }, true);

    if (rcmail.env.action.startsWith('plugin.ispconfig3_wblist')) {
        // Prevent implicit form submission using the enter key. Otherwise, one could bypass the input validation routines.
        // See https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#implicit-submission
        document.getElementById('wblist_form').addEventListener('keypress', function(e) {
            var key = rcube_event.get_keycode(e);
            if (key === 13) {
                e.preventDefault();
            }
        });

        $('input:not(:hidden):first').focus();
    }
});

function wb_edit(id, type) {
    rcmail.location_href(rcmail.env.comm_path + '&_action=plugin.ispconfig3_wblist&_id=' + id + '&_type=' + type, window, true);
}