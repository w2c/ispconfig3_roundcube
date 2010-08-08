if (window.rcmail)
{
	rcmail.addEventListener('init', function(evt)
	{
		var tab = $('<span>').attr('id', 'settingstabpluginispconfig3_account').addClass('tablink'); 
		var button = $('<a>').attr('href', rcmail.env.comm_path+'&_action=plugin.ispconfig3_account').html(rcmail.gettext('acc_acc','ispconfig3_account')).appendTo(tab);
		button.bind('click', function(e){ return rcmail.command('plugin.ispconfig3_account', this) });

		// add button and register commands
		rcmail.add_element(tab, 'tabs');
		rcmail.register_command('plugin.ispconfig3_account', function() { rcmail.goto_url('plugin.ispconfig3_account') }, true);     
	}
)}

if (rcmail.env.action == 'plugin.ispconfig3_account')
{
	rcmail.section_select = function(list)
	{
		var id = list.get_single_selection()

		if (id)
		{
			var add_url = '';
			var target = window;
			this.set_busy(true);

			if (this.env.contentframe && window.frames && window.frames[this.env.contentframe])
			{
				add_url = '&_framed=1';
				target = window.frames[this.env.contentframe];
			}
			
			if (id == 'general')
			{
				id = 'account.show';
			}

			target.location.href = this.env.comm_path+'&_action=plugin.ispconfig3_'+id+add_url;
		}

		return true;
	}
}