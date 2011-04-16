<?php

class ispconfig3_forward extends rcube_plugin
{
	public $task = 'settings';
	public $EMAIL_ADDRESS_PATTERN = '([a-z0-9][a-z0-9\-\.\+\_]*@[a-z0-9]([a-z0-9\-][.]?)*[a-z0-9]\\.[a-z]{2,5})';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));
		
		$this->register_action('plugin.ispconfig3_forward', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_forward.save', array($this, 'save'));
		
		$this->api->output->add_handler('forward_form', array($this, 'gen_form'));
		$this->api->output->add_handler('sectionname_forward', array($this, 'prefs_section_name'));
		
		$this->include_script('forward.js');
	}

	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('acc_forward')); 
		$this->rcmail_inst->output->send('ispconfig3_forward.forward');
	}

	function prefs_section_name()
	{
		return $this->gettext('acc_forward');
	}  

	function save()
	{
		$address      = strtolower(get_input_value('_forwardingaddress', RCUBE_INPUT_POST)); 

		if($address == $this->rcmail_inst->user->data['username'])
			$this->rcmail_inst->output->command('display_message', $this->gettext('forwardingloop'), 'error');
		else
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

				$params = array('server_id' => $mail_user[0]['server_id'],
								'email' => $this->rcmail_inst->user->data['username'],
								'name' => $mail_user[0]['name'],
								'uid' => $mail_user[0]['uid'],
								'gid' => $mail_user[0]['gid'],
								'cc' => $address,
								'maildir' => $mail_user[0]['maildir'],
								'quota' => $mail_user[0]['quota'],
								'homedir' => $mail_user[0]['homedir'],							
								'autoresponder' => $mail_user[0]['autoresponder'],
								'autoresponder_text' => $mail_user[0]['autoresponder_text'],
								'autoresponder_start_date' => $mail_user[0]['autoresponder_start_date'],
								'autoresponder_end_date' => $mail_user[0]['autoresponder_end_date'],
								'move_junk' => $mail_user[0]['move_junk'],
								'custom_mailfilter' => $mail_user[0]['custom_mailfilter'],
								'postfix' => $mail_user[0]['postfix'],
								'access' => $mail_user[0]['access'],
								'disableimap' => $mail_user[0]['disableimap'],
								'disablepop3' => $mail_user[0]['disablepop3'],
								'disabledeliver' => $mail_user[0]['disabledeliver'],
								'disablesmtp' => $mail_user[0]['disablesmtp']);

				$update = $this->soap->mail_user_update($session_id, 0, $mail_user[0]['mailuser_id'], $params);
				$this->soap->logout($session_id);
				
				$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
		} 

		$this->init_html();
	}

	function gen_form()
	{
		$user = $this->rcmail_inst->user->get_prefs();

		$this->rcmail_inst->output->add_label('ispconfig3_forward.invalidaddress',
												'ispconfig3_forward.forwardingempty');

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

		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$out .= '<fieldset><legend>' . $this->gettext('acc_forward') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		$field_id = 'forwardingaddress';
		$input_forwardingaddress = new html_inputfield(array('name' => '_forwardingaddress', 'id' => $field_id, 'value' => $forward[1], 'maxlength' => 320, 'size' => 40));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('forwardingaddress')),
						$input_forwardingaddress->show($mail_user[0]['cc']));                                            

		$out .= "\n</table>";
		$out .= '<br />' . "\n";
		$out .= "</fieldset>\n";    

		$this->rcmail_inst->output->add_gui_object('forwardform', 'forward-form');

		return $out;
	}
}
?>