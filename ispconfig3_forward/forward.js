if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_forward.save', function () {
      document.forms.forwardform.submit();
    }, true);
  })
}