window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_pass.save', function () {
        var input_curpasswd = rcube_find_object('_curpasswd');
        var input_newpasswd = rcube_find_object('_newpasswd');
        var input_confpasswd = rcube_find_object('_confpasswd');

        if (input_curpasswd && input_curpasswd.value == '') {
            rcmail.display_message(rcmail.gettext('ispconfig3_pass.nocurpassword'), 'error');
            input_curpasswd.focus();
        }
        else if (input_newpasswd && input_newpasswd.value == '') {
            rcmail.display_message(rcmail.gettext('ispconfig3_pass.nopassword'), 'error');
            input_newpasswd.focus();
        }
        else if (input_confpasswd && input_confpasswd.value == '') {
            rcmail.display_message(rcmail.gettext('ispconfig3_pass.passwordinconsistency'), 'error');
            input_confpasswd.focus();
        }
        else if (input_newpasswd && input_confpasswd && input_newpasswd.value != input_confpasswd.value) {
            rcmail.display_message(rcmail.gettext('ispconfig3_pass.passwordinconsistency'), 'error');
            input_newpasswd.focus();
        }
        else if (input_newpasswd.value.length < pw_min_length) {
            rcmail.display_message(rcmail.gettext('ispconfig3_pass.passwordminlength').replace("%d", pw_min_length), 'error');
            input_newpasswd.focus();
        }
        else {
            document.forms.pass_form.submit();
        }
    }, true);

    if (rcmail.env.action.startsWith('plugin.ispconfig3_pass')) {
        $('#pass-check').progressbar({
            value: 0
        });

        $('#newpasswd').keyup(function () {
            var passCheck = $("#pass-check");
            var passCheckValue = passCheck.find(".ui-progressbar-value");
            var score = chkPass($('#newpasswd').val(), pw_min_length);

            var color = 'DA4F49';
            if (score != 0) {
                if (score > 20 && score <= 40) {
                    color = 'FAA732';
                } else if (score > 40 && score <= 60) {
                    color = 'FAF332';
                } else if (score > 60 && score <= 80) {
                    color = '5BB7A9';
                } else if (score > 80) {
                    color = '5BB75B';
                }
            }

            passCheckValue.css({"background": '#' + color});
            passCheck.progressbar("option", {
                value: score
            });
        });

        $('input:not(:hidden)').first().focus();
    }
});