window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_forward.save', function () {
        var input_address = rcube_find_object('_forwardingaddress');

        if (input_address.value == '') {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_forward.forwardingempty'), 'error');
        }
        else if (!rcube_check_email(input_address.value, false)) {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_forward.invalidaddress'), 'error');
            input_address.focus();
        }
        else {
            document.forms.forward_form.submit();
        }
    }, true);
    
    rcmail.register_command('plugin.ispconfig3_forward.del', function (email) {
        rcmail.confirm_dialog(rcmail.gettext('ispconfig3_forward.forwarddelconfirm'), 'delete', function(e, ref) {
            var lock = ref.set_busy(true, 'loading');
            ref.http_request('plugin.ispconfig3_forward.save', '_type=del&_forwardingaddress=' + email, lock);
            document.getElementById('rule-table').deleteRow(document.getElementById('rule_' + email).rowIndex);
        });

        return false;
    }, true);

    if (rcmail.env.action.startsWith('plugin.ispconfig3_forward')) {
        // Prevent implicit form submission using the enter key. Otherwise, one could bypass the input validation routines.
        // See https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#implicit-submission
        document.getElementById('forward_form').addEventListener('keypress', function(e) {
            var key = rcube_event.get_keycode(e);
            if (key === 13) {
                e.preventDefault();
            }
        });

        $('input:not(:hidden)').first().focus();
    }
});