if (window.rcmail) {
  rcmail.addEventListener('init', function(evt) {

    rcmail.register_command('plugin.ispconfig3_pass.save', function() { 
    var input_curpasswd = rcube_find_object('_curpasswd');
    var input_newpasswd = rcube_find_object('_newpasswd');
    var input_confpasswd = rcube_find_object('_confpasswd');
    if (input_curpasswd && input_curpasswd.value=='') {
	    rcmail.display_message(rcmail.gettext('nocurpassword', 'ispconfig3_pass'),'error');
	    input_curpasswd.focus();
    } else if (input_newpasswd && input_newpasswd.value=='') {
	    rcmail.display_message(rcmail.gettext('nopassword', 'ispconfig3_pass'),'error');
	    input_newpasswd.focus();
    } else if (input_confpasswd && input_confpasswd.value=='') {
	    rcmail.display_message(rcmail.gettext('passwordinconsistency', 'ispconfig3_pass'),'error');
	    input_confpasswd.focus();
    } else if ((input_newpasswd && input_confpasswd) && (input_newpasswd.value != input_confpasswd.value)) {
	    rcmail.display_message(rcmail.gettext('passwordinconsistency', 'ispconfig3_pass'),'error');
	    input_newpasswd.focus();
    } else if ((input_newpasswd.value.length < pw_min_length)) {
	    rcmail.display_message(rcmail.gettext('passwordminlength', 'ispconfig3_pass').replace("%d",pw_min_length),'error');
	    input_newpasswd.focus();	    
    } else {
	    document.forms.passform.submit();
    }
    }, true);
  })
}