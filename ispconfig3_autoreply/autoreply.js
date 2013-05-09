if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {
    $('#autoreplystarton').datetime(
      {
        chainTo: '#autoreplyendby',
      });
    rcmail.register_command('plugin.ispconfig3_autoreply.save', function () {
      var input_text = rcube_find_object('_autoreplybody');
      var input_subject = rcube_find_object('_autoreplysubject');
      var input_enabled = rcube_find_object('_autoreplyenabled');

      if ((input_text.value == "") && (input_enabled.checked == true)) {
        parent.rcmail.display_message(rcmail.gettext('textempty', 'ispconfig3_autoreply'), 'error');
        input_text.focus();
      }
      else if ((input_subject.value == "")) {
        parent.rcmail.display_message(rcmail.gettext('textempty', 'ispconfig3_autoreply'), 'error');
        input_subject.focus();
      }
      else {
        document.forms.autoreplyform.submit();
      }
    }, true);
  })
}