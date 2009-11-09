<?php

class ispconfig3_spam extends rcube_plugin
{
  public $task = 'settings';

  function init()
  {
    $this->register_action('plugin.ispconfig3_spam', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_spam.save', array($this, 'save'));
    $this->api->output->add_handler('spam_form', array($this, 'gen_form'));
	$this->api->output->add_handler('sectionname_spam', array($this, 'prefs_section_name'));
    $this->include_script('spam.js');
  }
   
  function init_html()
  {
    $rcmail = rcmail::get_instance();
    $rcmail->output->set_pagetitle($this->gettext('junk')); 
    $rcmail->output->send('ispconfig3_spam.spam');
    
  }
  
  function prefs_section_name()
  {
	  return $this->gettext('junk');
  }  
  
  function save()
  {
    
    $rcmail = rcmail::get_instance();
    
    $id      = get_input_value('_id', RCUBE_INPUT_POST);
	$priority      = strtolower(get_input_value('_priority', RCUBE_INPUT_POST));

	  if(!$id) {
		$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
										 'uri'      => $rcmail->config->get('soap_url')));
	
		try {
			
			$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
			$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
			
			$params = array(	'server_id' => $mail_user[0]['server_id'],
								'priority' => $priority,
								'policy_id' => '5',
								'email' => $rcmail->user->data['username'],
								'fullname' => $rcmail->user->data['username'],
								'local' => 'Y');
			
			$add = $client->mail_spamfilter_user_add($session_id, $mail_user[0]['sys_userid'], $params);
			
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
			
			$params = array(	'server_id' => $mail_user[0]['server_id'],
								'priority' => $priority,
								'policy_id' => $mail_user[0]['policy_id'],
								'email' => $rcmail->user->data['username'],
								'fullname' => $rcmail->user->data['username'],
								'local' => $mail_user[0]['local']);
			 
			$update = $client->mail_spamfilter_user_update($session_id, $id, $mail_user[0]['sys_userid'], $params);
		
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
	
	//get settings start
    $client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
                                     'uri'      => $rcmail->config->get('soap_url')));

	try {
		
		$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
		$spam = $client->mail_spamfilter_user_get($session_id, array('email' => $rcmail->user->data['username']));  
    
		$client->logout($session_id);
    $priority = $spam[0]['priority'];
    if (empty($priority)) {
      $priority = 5;
    }
		
	} catch (SoapFault $e) {
		$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
	}
	
	/*get settings end*/

    $rcmail->output->set_env('framed', true);

    // allow the following attributes to be added to the <table> tag
    $attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));
	
	$hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $spam[0]['id']));
	$out .= $hidden_id->show();
	
	$hidden_priority = new html_hiddenfield(array('name' => '_priority', 'value' => $priority, 'id' => 'priority'));
	$out .= $hidden_priority->show();
  
  $out .= "<script type=\"text/javascript\">
	$(function() {	
			 $('#slider').slider({
					range: \"min\",
					value: ".$priority.",
					min: 1,
					max: 10,
					step: 1,
					slide: function(event, ui) {
						$(\"#priority\").val(ui.value);
					}
			});
			$(\"#priority\").val($(\"#slider\").slider(\"value\"));
	});
</script>";
	
    // return the complete edit form as table
    $out .= '<fieldset><legend>' . $this->gettext('junk') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    // show slider
	//$input_autoresponderdate = new html_inputfield(array('name' => '_priority', 'readonly' => 'readonly', 'id' => 'priority', 'size' => 10));
    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('priority')),
                '<div id="slider"></div>');
	
	$out .= "<tr><td colspan='2'><ul id='numbers'>
									<li>1</li>
									<li style='padding-left:37px;'>2</li>
									<li style='padding-left:35px;'>3</li>
									<li style='padding-left:38px;'>4</li>
									<li style='padding-left:35px;'>5</li>
									<li style='padding-left:38px;'>6</li>
									<li style='padding-left:36px;'>7</li>
									<li style='padding-left:37px;'>8</li>
									<li style='padding-left:36px;'>9</li>
									<li style='padding-left:32px;'>10</li></ul></td></tr>\n";

    $out .= "\n</table>";
    $out .= '<br />' . "\n";
    $out .= "</fieldset>\n";    

    $rcmail->output->add_gui_object('spamform', 'spam-form');
    
    return $out;
  }
}
?>