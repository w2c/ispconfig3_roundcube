if (window.rcmail) {
    rcmail.addEventListener('init', function (evt) {
        rcmail.register_command('plugin.ispconfig3_forward.save', function () {
            var input_address = rcube_find_object('_forwardingaddress');

            if (input_address.value == "") {
                parent.rcmail.display_message(rcmail.gettext('forwardingempty', 'ispconfig3_forward'), 'error');
            }
            else {
                if (!rcube_check_email(input_address.value, false)) {
                    parent.rcmail.display_message(rcmail.gettext('invalidaddress', 'ispconfig3_forward'), 'error');
                    input_address.focus();
                }
                else {
                    document.forms.forwardform.submit();
                }
            }
        }, true);
    })
}

rcmail.register_command('plugin.ispconfig3_forward.del', function (email) {
    if (confirm(rcmail.gettext('forwarddelconfirm', 'ispconfig3_forward'))) {
        rcmail.http_request('plugin.ispconfig3_forward.save', '_type=del&_forwardingaddress=' + email, true);
        forward_row_del(email);
    }

    return false;
}, true);

function forward_row_del(email) {
    document.getElementById('rule-table').deleteRow(document.getElementById('rule_' + email).rowIndex);
}