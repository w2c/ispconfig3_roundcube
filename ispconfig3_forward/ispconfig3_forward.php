<?php

class ispconfig3_forward extends rcube_plugin
{
  public $task = 'settings';
  public $EMAIL_ADDRESS_PATTERN = '([a-z0-9][a-z0-9\-\.\+\_]*@[a-z0-9]([a-z0-9\-][.]?)*[a-z0-9]\\.[a-z]{2,5})';

  function init()
  {
    $this->add_texts('localization/', true);
    $this->register_action('plugin.ispconfig3_forward', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_forward.save', array($this, 'save'));
    $this->api->output->add_handler('forward_form', array($this, 'gen_form'));
	$this->api->output->add_handler('sectionname_forward', array($this, 'prefs_section_name'));
    $this->include_script('forward.js');
  }
   
  function init_html()
  {
    $rcmail = rcmail::get_instance();
    $rcmail->output->set_pagetitle($this->gettext('forwarding')); 
    $rcmail->output->send('ispconfig3_forward.forward');
    
  }
  
  function prefs_section_name()
  {
	  return $this->gettext('forwarding');
  }  
  
  function save()
  {
    $rcmail = rcmail::get_instance();
    
    $id      = get_input_value('_id', RCUBE_INPUT_POST);
	$enabled      = get_input_value('_forwardingenabled', RCUBE_INPUT_POST);
	$address      = strtolower(get_input_value('_forwardingaddress', RCUBE_INPUT_POST));
	
    if(!$enabled) {
      $enabled = 'n';
	} else {
	  $enabled = 'y';
	}
	
    if($address == $user){
      $rcmail->output->command('display_message', $this->gettext('forwardingloop'), 'error');
    }
    else if (!preg_match("/$this->EMAIL_ADDRESS_PATTERN/i", $address)) {
      $rcmail->output->command('display_message', $this->gettext('invalidaddress'), 'error');
    }
    else{
	  if(!$id) {
		$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
										 'uri'      => $rcmail->config->get('soap_url')));
	
		try {
			
			$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
			$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
			
			$params = array('server_id' => $mail_user[0]['server_id'],
							'source' => $rcmail->user->data['username'],
							'destination' => $address,
							'type' => 'forward',
							'active' => $enabled);
			
			$update = $client->mail_forward_add($session_id, $mail_user[0]['sys_userid'], $params);
			
			$forward = $client->mail_forward_get($session_id, $params); 
			$rcmail->user->save_prefs(array('forward' => $forward[0]['forwarding_id']));
			
			$client->logout($session_id);
			$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
			
		} catch (SoapFault $e) {
			$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
	  } else {
		$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
									 'uri'      => $rcmail->config->get('soap_url')));
	
		try {
			
			$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
			$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
			
			$params = array('server_id' => $mail_user[0]['server_id'],
							'sys_userid' => $mail_user[0]['sys_userid'],
							'sys_groupid' => $mail_user[0]['sys_groupid'],
							'source' => $rcmail->user->data['username'],
							'destination' => $address,
							'type' => 'forward',
							'active' => $enabled);
			 
			$update = $client->mail_forward_update($session_id, $id, $mail_user[0]['sys_userid'], $params);
		
			$client->logout($session_id);
			$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
			
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
	$user = $rcmail->user->get_prefs();
	
    // add some labels to client
    $rcmail->output->add_label(
      'ispconfig3_forward.forwarding',    
      'ispconfig3_forward.invalidaddress',
      'ispconfig3_forward.forwardingloop',
	  'ispconfig3_forward.forwardingempty'
    );
	
	/*get settings start*/
    $client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
                                     'uri'      => $rcmail->config->get('soap_url')));

	try {
		
		$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
		$forward = $client->mail_forward_get($session_id, array('forwarding_id' => $user['forward']));  
		 
		$client->logout($session_id);
		
	} catch (SoapFault $e) {
		$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
	}
	
    $enabled     = $forward[0]['active'];
	if ($enabled == 'y') {
		$enabled = 1;
	} else {
		$enabled = 0;
	}
	
	/*get settings end*/

    $rcmail->output->set_env('framed', true);

    // allow the following attributes to be added to the <table> tag
    $attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));
	
	$hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $user['forward']));
	$out .= $hidden_id->show();

    // return the complete edit form as table
    $out .= '<fieldset><legend>' . $this->gettext('forwarding') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    // show forward properties
	$field_id = 'forwardingaddress';
    $input_forwardingaddress = new html_inputfield(array('name' => '_forwardingaddress', 'id' => $field_id, 'value' => $forward[0]['destination'], 'maxlength' => 320, 'size' => 40));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('forwardingaddress')),
                $input_forwardingaddress->show($forward[0]['destination'])); 
	
    $field_id = 'forwardingenabled';
    $input_forwardingenabled = new html_checkbox(array('name' => '_forwardingenabled', 'id' => $field_id, 'value' => $enabled?1:0));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('forwardingenabled')),
                $input_forwardingenabled->show($enabled?1:0));                                            

    $out .= "\n</table>";
    $out .= '<br />' . "\n";
    $out .= "</fieldset>\n";    

    $rcmail->output->add_gui_object('forwardform', 'forward-form');
    
    return $out;
  }
}
?>