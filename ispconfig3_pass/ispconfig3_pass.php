<?php

class ispconfig3_pass extends rcube_plugin
{
	public $task = 'settings';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
 		$this->load_config();
		$this->add_texts('localization/', true);
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));
		
		$this->register_action('plugin.ispconfig3_pass', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_pass.save', array($this, 'save'));
		
		$this->api->output->add_handler('pass_form', array($this, 'gen_form'));
		$this->api->output->add_handler('sectionname_pass', array($this, 'prefs_section_name'));
		
		$this->include_script('pass.js');
	}
	
	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('password')); 
		$this->rcmail_inst->output->send('ispconfig3_pass.pass');
	}

	function load_config()
	{
		$config = $this->home.'/config/config.inc.php';
		if(file_exists($config))
		{
			if(!$this->rcmail_inst->config->load_from_file($config))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config . ".dist"))
		{
			if(!$this->rcmail_inst->config->load_from_file($config . '.dist'))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
	}  

	function prefs_section_name()
	{
		return $this->gettext('password');
	}

	function save()
	{
		$confirm = $this->rcmail_inst->config->get('password_confirm_current');

		if (($confirm && !isset($_POST['_curpasswd'])) || !isset($_POST['_newpasswd']))
			$this->rcmail_inst->output->command('display_message', $this->gettext('nopassword'), 'error');
		else
		{
			$curpwd = get_input_value('_curpasswd', RCUBE_INPUT_POST);
			$newpwd = get_input_value('_newpasswd', RCUBE_INPUT_POST);
			if ($confirm && $this->rcmail_inst->decrypt($_SESSION['password']) !=  $curpwd)
				$this->rcmail_inst->output->command('display_message', $this->gettext('passwordincorrect'), 'error');
			else
			{
				try
				{
					$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
					$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

					$params = array('server_id' => $mail_user[0]['server_id'],
									'email' => $this->rcmail_inst->user->data['username'],
									'password' => $newpwd,
									'name' => $mail_user[0]['name'],
									'uid' => $mail_user[0]['uid'],
									'gid' => $mail_user[0]['gid'],
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

					$uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);
					$update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
					$this->soap->logout($session_id);
					
					$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
					
					$_SESSION['password'] = $this->rcmail_inst->encrypt($newpwd);
					
					$this->rcmail_inst->user->data['password'] = $_SESSION['password'];
				}
				catch (SoapFault $e)
				{
					$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
				}
			}
		}
		$this->init_html();
	}

	function gen_form()
	{
		$confirm = $this->rcmail_inst->config->get('password_confirm_current');
		$pwl = $this->rcmail_inst->config->get('password_min_length'); 
		  
		if(!empty($pwl))
			$pwl = max(6,$pwl);
		else
			$pwl = 6;

		$this->rcmail_inst->output->add_label('ispconfig3_pass.nopassword',
									'ispconfig3_pass.nocurpassword',
									'ispconfig3_pass.passwordinconsistency',
									'ispconfig3_pass.changepasswd',
									'ispconfig3_pass.passwordminlength');

		$this->rcmail_inst->output->add_script('var pw_min_length =' . $pwl . ';');
		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$out .= '<fieldset><legend>' . $this->gettext('password') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		if ($confirm)
		{
			$field_id = 'curpasswd';
			$input_newpasswd = new html_passwordfield(array('name' => '_curpasswd', 'id' => $field_id, 'size' => 20));

			$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label></td><td>%s</td></tr>\n",
							$field_id,
							rep_specialchars_output($this->gettext('curpasswd')),
							$input_newpasswd->show());
		}

		$field_id = 'newpasswd';
		$input_newpasswd = new html_passwordfield(array('name' => '_newpasswd', 'id' => $field_id, 'size' => 20));

		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label></td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('newpasswd')),
						$input_newpasswd->show());
						
		$field_id = 'confpasswd';
		$input_confpasswd = new html_passwordfield(array('name' => '_confpasswd', 'id' => $field_id, 'size' => 20));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label></td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('confpasswd')),
						$input_confpasswd->show());
						
		$out .= "\n</table>";
		$out .= '<br />' . "\n";       
		$out .= "</fieldset>\n";
		
		$this->rcmail_inst->output->add_gui_object('passform', 'pass-form');
		
		return $out;
	}
}
?>