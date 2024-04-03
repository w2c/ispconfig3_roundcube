window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_autoreply.save', function () {
        var input_text = rcube_find_object('_autoreplybody');
        var input_subject = rcube_find_object('_autoreplysubject');
        var input_enabled = rcube_find_object('_autoreplyenabled');

        if (input_text.value == '' && input_enabled.checked) {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_autoreply.textempty'), 'error');
            input_text.focus();
        }
        else if (input_subject.value == '') {
            parent.rcmail.display_message(rcmail.gettext('ispconfig3_autoreply.textempty'), 'error');
            input_subject.focus();
        }
        else {
            document.forms.autoreply_form.submit();
        }
    }, true);

    if (rcmail.env.action.startsWith('plugin.ispconfig3_autoreply')) {
        $('#autoreplystarton').datetime({
            chainTo: '#autoreplyendby'
        });

        $('input:not(:hidden)').first().focus();
    }
});