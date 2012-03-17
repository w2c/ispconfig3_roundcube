<?php

class ispconfig3_pass extends rcube_plugin
{
	public $task = 'settings';
	private $rcmail_inst = NULL;
  private $required_plugins = array('ispconfig3_account');

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
 		$this->load_config();
		$this->add_texts('localization/', true);
		
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
          $soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));
					$session_id = $soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
					$mail_user = $soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

          $params = $mail_user[0];
          $params['password'] = $newpwd;

					$uid = $soap->client_get_id($session_id, $mail_user[0]['sys_userid']);
					$update = $soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
					$soap->logout($session_id);
					
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

		$out .= '<fieldset><legend>' . $this->gettext('password') . '</legend>' . "\n";

    $table = new html_table(array('cols' => 2, 'class' => 'propform'));
    
    if ($confirm)
		{
      $input_newpasswd = new html_passwordfield(array('name' => '_curpasswd', 'id' => 'curpasswd', 'size' => 20));
      $table->add('title', rep_specialchars_output($this->gettext('curpasswd')));
      $table->add('', $input_newpasswd->show());
    }

    $input_newpasswd = new html_passwordfield(array('name' => '_newpasswd', 'id' => 'newpasswd', 'size' => 20));
    $table->add('title', rep_specialchars_output($this->gettext('newpasswd')));
    $table->add('', $input_newpasswd->show());
    
    $input_confpasswd = new html_passwordfield(array('name' => '_confpasswd', 'id' => 'confpasswd', 'size' => 20));
    $table->add('title', rep_specialchars_output($this->gettext('confpasswd')));
    $table->add('', $input_confpasswd->show());

    $out .= $table->show();    
		$out .= "</fieldset>\n";
		
		return $out;
	}
}
?>