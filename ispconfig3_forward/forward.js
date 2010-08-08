if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
		rcmail.register_command('plugin.ispconfig3_forward.save', function()
		{ 
			var input_address = rcube_find_object('_forwardingaddress');  
			var input_enabled = rcube_find_object('_forwardingenabled');   

			if(input_address.value == "")
			{
				parent.rcmail.display_message(rcmail.gettext('forwardingempty','ispconfig3_forward'),'error');
				input_address.focus(); 
			}
			else
			{
				if(!rcube_check_email(input_address.value, false))
				{
					parent.rcmail.display_message(rcmail.gettext('invalidaddress','ispconfig3_forward'),'error');
					input_address.focus();     
				}
				else
				{
					document.forms.forwardform.submit();      
				}	
			}
		}, true);
	})
}