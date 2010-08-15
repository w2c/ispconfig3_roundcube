<?php

class ispconfig3_autoreply extends rcube_plugin
{
	public $task = 'settings';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));

		$this->register_action('plugin.ispconfig3_autoreply', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_autoreply.save', array($this, 'save'));
		
		$this->api->output->add_handler('autoreply_form', array($this, 'gen_form'));
		$this->api->output->add_handler('sectionname_autoreply', array($this, 'prefs_section_name'));
		
		$this->include_script('autoreply.js');
	}

	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('acc_autoreply')); 
		$this->rcmail_inst->output->send('ispconfig3_autoreply.autoreply');
	}

	function prefs_section_name()
	{
		return $this->gettext('acc_autoreply');
	}

	function save()
	{ 
		$user = $this->rcmail_inst->user->data['username'];
		$enabled = get_input_value('_autoreplyenabled', RCUBE_INPUT_POST);
		$body = get_input_value('_autoreplybody', RCUBE_INPUT_POST);
		$startdate = get_input_value('_autoreplystarton', RCUBE_INPUT_POST);
		$enddate = get_input_value('_autoreplyendby', RCUBE_INPUT_POST);
		
		if(!$enabled)
			$enabled = 'n';
		else
			$enabled = 'y';

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

			$params = array('server_id' => $mail_user[0]['server_id'],
							'email' => $this->rcmail_inst->user->data['username'],
							'name' => $mail_user[0]['name'],
							'uid' => $mail_user[0]['uid'],
							'gid' => $mail_user[0]['gid'],
							'maildir' => $mail_user[0]['maildir'],
							'quota' => $mail_user[0]['quota'],
							'homedir' => $mail_user[0]['homedir'],							
							'autoresponder' => $enabled,
							'autoresponder_text' => $body,
							'autoresponder_start_date' => $startdate,
							'autoresponder_end_date' => $enddate,
							'move_junk' => $mail_user[0]['move_junk'],
							'custom_mailfilter' => $mail_user[0]['custom_mailfilter'],
							'postfix' => $mail_user[0]['postfix'],
							'access' => $mail_user[0]['access'],
							'disableimap' => $mail_user[0]['disableimap'],
							'disablepop3' => $mail_user[0]['disablepop3'],
							'disabledeliver' => $mail_user[0]['disabledeliver'],
							'disablesmtp' => $mail_user[0]['disablesmtp']);

			$update = $this->soap->mail_user_update($session_id, $mail_user[0]['sys_userid'], $mail_user[0]['mailuser_id'], $params);
			$this->soap->logout($session_id);
			
			$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		$this->init_html();
	}

	function gen_form()
	{
		$this->rcmail_inst->output->add_label('ispconfig3_autoreply.textempty'); 

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));  
			$this->soap->logout($session_id);
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		$enabled     = $mail_user[0]['autoresponder'];
		
		if ($enabled == 'y')
			$enabled = 1;
		else
			$enabled = 0;

		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$hidden_priority = new html_hiddenfield(array('name' => '_priority', 'value' => $priority, 'id' => 'priority'));
		$out .= $hidden_priority->show();

		$out .= '<fieldset><legend>' . $this->gettext('acc_autoreply') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		$field_id = 'autoreplybody';
		$input_autoreplybody = new html_textarea(array('name' => '_autoreplybody', 'id' => $field_id, 'cols' => 48, 'rows' => 15));
		$out .= sprintf("<tr><td valign=\"top\" class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplymessage')),
						$input_autoreplybody->show($mail_user[0]['autoresponder_text']));

		$field_id = 'autoreplyenabled';
		$input_autoreplyenabled = new html_checkbox(array('name' => '_autoreplyenabled', 'id' => $field_id, 'value' => 1));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplyenabled')),
						$input_autoreplyenabled->show($enabled?1:0));

		$field_id = 'autoreplystarton';
		$input_autoreplystarton = new html_inputfield(array('name' => '_autoreplystarton', 'id' => $field_id, 'size' => 20));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplystarton')),
						$input_autoreplystarton->show($mail_user[0]['autoresponder_start_date']));

		$field_id = 'autoreplyendby';
		$input_autoreplyendby = new html_inputfield(array('name' => '_autoreplyendby', 'id' => $field_id, 'size' => 20));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplyendby')),
						$input_autoreplyendby->show($mail_user[0]['autoresponder_end_date']));

		$out .= "\n</table>";
		$out .= '<br />' . "\n";
		$out .= "</fieldset>\n";    

		$this->rcmail_inst->output->add_gui_object('autoreplyform', 'autoreply-form');

		return $out;
	}
}
?>