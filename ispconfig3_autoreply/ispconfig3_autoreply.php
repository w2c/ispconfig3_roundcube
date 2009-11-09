<?php

class ispconfig3_autoreply extends rcube_plugin
{
  public $task = 'settings';

  function init()
  {
	$this->add_texts('localization/', true);
    $rcmail = rcmail::get_instance();

    $this->register_action('plugin.ispconfig3_autoreply', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_autoreply.save', array($this, 'save'));
    $this->api->output->add_handler('autoreply_form', array($this, 'gen_form'));
	$this->api->output->add_handler('sectionname_autoreply', array($this, 'prefs_section_name'));
    $this->include_script('autoreply.js');
  }

  function init_html()
  {
    $rcmail = rcmail::get_instance();
    $rcmail->output->set_pagetitle($this->gettext('autoreply')); 
    $rcmail->output->send('ispconfig3_autoreply.autoreply');
    
  }
  
  function prefs_section_name()
  {
	  return $this->gettext('autoreply');
  }
  
  function save()
  { 
    $rcmail = rcmail::get_instance();

    $user        = $rcmail->user->data['username'];
    $enabled     = get_input_value('_autoreplyenabled', RCUBE_INPUT_POST);
    if(!$enabled) {
      $enabled = 'n';
	} else {
	  $enabled = 'y';
	}
    $body        = get_input_value('_autoreplybody', RCUBE_INPUT_POST);
            
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
						'autoresponder' => $enabled,
						'autoresponder_text' => $body);
		 
		$update = $client->mail_user_update($session_id, $mail_user[0]['sys_userid'], $mail_user[0]['mailuser_id'], $params);
	
		$client->logout($session_id);
		$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
		
	} catch (SoapFault $e) {
		$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
	}

    $this->init_html();
  
  }

  function gen_form()
  {
    $rcmail = rcmail::get_instance();

    // add some labels to client
    $rcmail->output->add_label(
      'ispconfig3_autoreply.autoreply',
      'ispconfig3_autoreply.textempty',
      'ispconfig3_autoreply.and'      
    ); 
/*auslesen start*/
    $client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
                                     'uri'      => $rcmail->config->get('soap_url')));

	try {
		
		$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
		$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));  
		 
		$client->logout($session_id);
		
	} catch (SoapFault $e) {
		$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
	}
	
    $enabled     = $mail_user[0]['autoresponder'];
	if ($enabled == 'y') {
		$enabled = 1;
	} else {
		$enabled = 0;
	}
    $body        = $mail_user[0]['autoresponder_text'];
/*auslesen end*/

    $rcmail->output->set_env('framed', true);
    
    // allow the following attributes to be added to the <table> tag
    $attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

    // return the complete edit form as table
    $out .= '<fieldset><legend>' . $this->gettext('autoreply') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    // show autoreply properties
    $field_id = 'autoreplybody';
    $input_autoreplybody = new html_textarea(array('name' => '_autoreplybody', 'id' => $field_id, 'cols' => 48, 'rows' => 15));

    $out .= sprintf("<tr><td valign=\"top\" class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('autoreplymessage')),
                $input_autoreplybody->show($body));
               
    $field_id = 'autoreplyenabled';
    $input_autoreplyenabled = new html_checkbox(array('name' => '_autoreplyenabled', 'id' => $field_id, 'value' => 1));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('autoreplyenabled')),
                $input_autoreplyenabled->show($enabled?1:0));                                                

    $out .= "\n</table>";
    $out .= '<br />' . "\n";
    $out .= "</fieldset>\n";    

    $rcmail->output->add_gui_object('autoreplyform', 'autoreply-form');
    
    return $out;
  }
}

?>