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
    
		$enabled      = get_input_value('_forwardingenabled', RCUBE_INPUT_POST);
		$address      = strtolower(get_input_value('_forwardingaddress', RCUBE_INPUT_POST)); 
		
    if($address == $user) {
      $rcmail->output->command('display_message', $this->gettext('forwardingloop'), 'error');
    } else if (!preg_match("/$this->EMAIL_ADDRESS_PATTERN/i", $address)) {
      $rcmail->output->command('display_message', $this->gettext('invalidaddress'), 'error');
    } else {
			
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
											 'uri'      => $rcmail->config->get('soap_url')));
		
			try {
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
	
				$filter_ar = explode("### End Forward\n\n",$mail_user[0]['custom_mailfilter']);				
				
				if($enabled == 0) {
					$address = "### cc \"!".$address."\"";
				} else {
					$address = "cc \"!".$address."\"";
				}
				
				if ($filter_ar[1] == '') {
					$filter = "### Start Forward\n".$address."\n### End Forward\n\n";
				} else {
					$filter = "### Start Forward\n".$address."\n### End Forward\n\n".$filter_ar[1];
				}
				
				$filter = utf8_encode($filter);
	
				//$params = array('custom_mailfilter' => $filter);
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
							'custom_mailfilter' => $filter);
				
				$uid = $client->client_get_id($session_id, $mail_user[0]['sys_userid']);
				$update = $client->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
				
				$client->logout($session_id);
				$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
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
			$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));  
			
			$client->logout($session_id);
			
		} catch (SoapFault $e) {
			$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		preg_match("/cc \"!([a-z0-9][a-z0-9-.+_]*@[a-z0-9]([a-z0-9-][.]?)*[a-z0-9]\.[a-z]{2,5})\"/", $mail_user[0]['custom_mailfilter'], $forward);
		
		if(strpos($mail_user[0]['custom_mailfilter'],'cc "!') !== false && strpos($mail_user[0]['custom_mailfilter'],'### cc "!') === false) {
			$enabled = 1;
		} else {
			$enabled = 0;
		}
	
		/*get settings end*/

    $rcmail->output->set_env('framed', true);

    // allow the following attributes to be added to the <table> tag
    $attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

    // return the complete edit form as table
    $out .= '<fieldset><legend>' . $this->gettext('forwarding') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    // show forward properties
		$field_id = 'forwardingaddress';
    $input_forwardingaddress = new html_inputfield(array('name' => '_forwardingaddress', 'id' => $field_id, 'value' => $forward[1], 'maxlength' => 320, 'size' => 40));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('forwardingaddress')),
                $input_forwardingaddress->show($forward[1])); 
	
    $field_id = 'forwardingenabled';
    $input_forwardingenabled = new html_checkbox(array('name' => '_forwardingenabled', 'id' => $field_id, 'value' => '1'));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('forwardingenabled')),
                $input_forwardingenabled->show($enabled));                                            

    $out .= "\n</table>";
    $out .= '<br />' . "\n";
    $out .= "</fieldset>\n";    

    $rcmail->output->add_gui_object('forwardform', 'forward-form');
    
    return $out;
  }
}
?>