<?php

class ispconfig3_pass extends rcube_plugin
{
  public $task = 'settings';

  function init()
  {
    $this->_load_config();
	$this->add_texts('localization/', true);
    $rcmail = rcmail::get_instance();
    
    $this->register_action('plugin.ispconfig3_pass', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_pass.save', array($this, 'save'));
    $this->api->output->add_handler('pass_form', array($this, 'gen_form'));
	$this->api->output->add_handler('sectionname_pass', array($this, 'prefs_section_name'));
    $this->include_script('pass.js');
  }
  
  function _load_config()
  {
    $rcmail = rcmail::get_instance();
    $config = "plugins/ispconfig3_pass/config/config.inc.php";
    if(file_exists($config))
      include $config;
    else if(file_exists($config . ".dist"))
      include $config . ".dist";
    if(is_array($rcmail_config)){
      $arr = array_merge($rcmail->config->all(),$rcmail_config);
      $rcmail->config->merge($arr);
    }
  }  

  function prefs_section_name()
  {
	  return $this->gettext('password');
  }
  
  function init_html()
  {
    $rcmail = rcmail::get_instance(); 
    $rcmail->output->set_pagetitle($this->gettext('password')); 
    $rcmail->output->send('ispconfig3_pass.pass');
  }
  
  function save()
  {
    $rcmail = rcmail::get_instance();

    $confirm = $rcmail->config->get('password_confirm_current');
    
    if (($confirm && !isset($_POST['_curpasswd'])) || !isset($_POST['_newpasswd']))
      $rcmail->output->command('display_message', $this->gettext('nopassword'), 'error');
    else {
      $curpwd = get_input_value('_curpasswd', RCUBE_INPUT_POST);
      $newpwd = get_input_value('_newpasswd', RCUBE_INPUT_POST);
      if ($confirm && $rcmail->decrypt($_SESSION['password']) !=  $curpwd)
        $rcmail->output->command('display_message', $this->gettext('passwordincorrect'), 'error');
      else {    
		$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
                                     'uri'      => $rcmail->config->get('soap_url')));

		try {
			
			$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
			$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
			
			$params = array('server_id' => $mail_user[0]['server_id'],
						'email' => $rcmail->user->data['username'],
						'quota' => $mail_user[0]['quota'],
						'maildir' => $mail_user[0]['maildir'],
						'homedir' => $mail_user[0]['homedir'],							
						'uid' => $mail_user[0]['uid'],
						'gid' => $mail_user[0]['gid'],
						'postfix' => $mail_user[0]['postfix'],
						'disableimap' => $mail_user[0]['disableimap'],
						'disablepop3' => $mail_user[0]['disablepop3'],
						'autoresponder' => $mail_user[0]['autoresponder'],
						'autoresponder_text' => $mail_user[0]['autoresponder_text'],
						'password' => $newpwd);
			
			$uid = $client->client_get_id($session_id, $mail_user[0]['sys_userid']);
			$update = $client->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
		
			$client->logout($session_id);
			$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
			$_SESSION['password'] = $rcmail->encrypt($newpwd);
        	$rcmail->user->data['password'] = $_SESSION['password'];   
			
		} catch (SoapFault $e) {
			$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
	  }
    }

    $this->init_html();
  }

  function gen_form()
  {
    $rcmail = rcmail::get_instance();

    $confirm = $rcmail->config->get('password_confirm_current');
    
    // add password min length to client
    $pwl = $rcmail->config->get('password_min_length');   
    if(!empty($pwl)){
      $pwl = max(6,$pwl);
    }
    else{
      $pwl = 6;
    }
        
    // add some labels to client
    $rcmail->output->add_label(
      'ispconfig3_pass.nopassword',
      'ispconfig3_pass.nocurpassword',
      'ispconfig3_pass.passwordinconsistency',
      'ispconfig3_pass.changepasswd',
      'ispconfig3_pass.passwordminlength'
    );

    $rcmail->output->add_script('var pw_min_length =' . $pwl . ';');
    $rcmail->output->set_env('framed', true);

    // allow the following attributes to be added to the <table> tag
    $attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

    // return the complete edit form as table
    $out .= '<fieldset><legend>' . $this->gettext('password') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    if ($confirm) {
      // show current password selection
      $field_id = 'curpasswd';
      $input_newpasswd = new html_passwordfield(array('name' => '_curpasswd', 'id' => $field_id, 'size' => 20));
  
      $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label></td><td>%s</td></tr>\n",
                  $field_id,
                  rep_specialchars_output($this->gettext('curpasswd')),
                  $input_newpasswd->show());
    }

    // show new password selection
    $field_id = 'newpasswd';
    $input_newpasswd = new html_passwordfield(array('name' => '_newpasswd', 'id' => $field_id, 'size' => 20));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label></td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('newpasswd')),
                $input_newpasswd->show());

    // show confirm password selection
    $field_id = 'confpasswd';
    $input_confpasswd = new html_passwordfield(array('name' => '_confpasswd', 'id' => $field_id, 'size' => 20));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label></td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('confpasswd')),
                $input_confpasswd->show());

    $out .= "\n</table>";
    $out .= '<br />' . "\n";       
    $out .= "</fieldset>\n";

    $rcmail->output->add_gui_object('passform', 'pass-form');
    
    return $out;
    
  }
}

?>