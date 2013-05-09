if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_spam.save', function () {
      document.forms.spamform.submit();
    }, true);
  })
}