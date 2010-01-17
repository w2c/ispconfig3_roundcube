<?php

class ispconfig3_fetchmail extends rcube_plugin
{
  public $task = 'settings';

  function init()
  {
		$this->_load_config();
	  $this->add_texts('localization/', true);
    $rcmail = rcmail::get_instance();

    $this->register_action('plugin.ispconfig3_fetchmail', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_fetchmail.save', array($this, 'save'));
		$this->register_action('plugin.ispconfig3_fetchmail.del', array($this, 'del'));
    $this->api->output->add_handler('fetchmail_form', array($this, 'gen_form'));
		$this->api->output->add_handler('fetchmail_table', array($this, 'gen_table'));
	  $this->api->output->add_handler('sectionname_fetchmail', array($this, 'prefs_section_name'));
    $this->include_script('fetchmail.js');
  }
	
	function _load_config()
  {
    $rcmail = rcmail::get_instance();
    $config = "plugins/ispconfig3_fetchmail/config/config.inc.php";
    if(file_exists($config))
      include $config;
    else if(file_exists($config . ".dist"))
      include $config . ".dist";
    if(is_array($rcmail_config)){
      $arr = array_merge($rcmail->config->all(),$rcmail_config);
      $rcmail->config->merge($arr);
    }
  } 

  function init_html()
  {
    $rcmail = rcmail::get_instance();
    $rcmail->output->set_pagetitle($this->gettext('fetchmail')); 
    $rcmail->output->send('ispconfig3_fetchmail.fetchmail');
    
  }
  
  function prefs_section_name()
  {
	  return $this->gettext('fetchmail');
  }
	
  function del()
	{
		$rcmail = rcmail::get_instance();
		$id     = get_input_value('_id', RCUBE_INPUT_GET);
		
		if ($id != 0 || $id != '') {
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																				 'uri'      => $rcmail->config->get('soap_url')));
		
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_fetchmail = $client->mail_fetchmail_get($session_id, $id);  
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
			if ($mail_fetchmail['destination'] == $rcmail->user->data['username']) {
				try {
					
					$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
					$delete = $client->mail_fetchmail_delete($session_id, $id);
				
					$client->logout($session_id);
					$rcmail->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
				} catch (SoapFault $e) {
					$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
				}
			}
		}
	}
	
  function save()
  { 
    $rcmail = rcmail::get_instance();

		$id     = get_input_value('_id', RCUBE_INPUT_POST);
		$serverid     = get_input_value('_serverid', RCUBE_INPUT_POST);
		
    $destination        = $rcmail->user->data['username'];
    $typ     = get_input_value('_fetchmailtyp', RCUBE_INPUT_POST);
		$server     = get_input_value('_fetchmailserver', RCUBE_INPUT_POST);
		$user     = get_input_value('_fetchmailuser', RCUBE_INPUT_POST);
		$pass     = get_input_value('_fetchmailpass', RCUBE_INPUT_POST);
		$delete     = get_input_value('_fetchmaildelete', RCUBE_INPUT_POST);
		$enabled     = get_input_value('_fetchmailenabled', RCUBE_INPUT_POST);
		
		if(!$delete) {
      $delete = 'n';
		} else {
			$delete = 'y';
		}
		
    if(!$enabled) {
      $enabled = 'n';
		} else {
			$enabled = 'y';
		}
		
		if($id == 0 || $id == '') {		
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																				 'uri'      => $rcmail->config->get('soap_url')));
			
			$limit = $rcmail->config->get('fetchmail_limit');
			
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_fetchmail = $client->mail_fetchmail_get($session_id, array('destination' => $destination));  
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
			if(count($mail_fetchmail) < $limit) {
				try {
					
					$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
					
					$params = array('server_id' => $serverid,
									'type' => $typ,
									'source_server' => $server,
									'source_username' => $user,
									'source_password' => $pass,							
									'source_delete' => $delete,
									'destination' => $destination,
									'active' => $enabled);
					
					$add = $client->mail_fetchmail_add($session_id, $mail_fetchmail[$id]['server_id'], $params);
				
					$client->logout($session_id);
					$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
					
				} catch (SoapFault $e) {
					$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
				}
			} else {
				$rcmail->output->command('display_message', 'Error: '.$this->gettext('fetchmaillimitreached'), 'error');
			}
		} else {
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																	 'uri'      => $rcmail->config->get('soap_url')));
						
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_fetchmail = $client->mail_fetchmail_get($session_id, $id);  
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}

	    if ($mail_fetchmail['destination'] == $destination) {		
				try {
					
					$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
					
					$params = array('server_id' => $serverid,
									'type' => $typ,
									'source_server' => $server,
									'source_username' => $user,
									'source_password' => $pass,							
									'source_delete' => $delete,
									'destination' => $destination,
									'active' => $enabled);
					
					$add = $client->mail_fetchmail_update($session_id, $id, $mail_fetchmail['sys_userid'], $params);
				
					$client->logout($session_id);
					$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
					
				} catch (SoapFault $e) {
					$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
				}
			} else {
				$rcmail->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');
			}
		}
    $this->init_html();
  
  }

  function gen_form()
  {
    $rcmail = rcmail::get_instance();
		$id = get_input_value('_id', RCUBE_INPUT_GET);

    // add some labels to client
    $rcmail->output->add_label(
			'ispconfig3_fetchmail.fetchmaildelconfirm',
			'ispconfig3_fetchmail.textempty'
    ); 
		if ($id != '' || $id != 0) {
			/*auslesen start*/
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																			 'uri'      => $rcmail->config->get('soap_url')));
	
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_fetchmail = $client->mail_fetchmail_get($session_id, $id);  
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
	    
			$enabled     = $mail_fetchmail['active'];
			$delete     = $mail_fetchmail['source_delete'];
			
	    if ($mail_fetchmail['destination'] != $rcmail->user->data['username']) {
				$rcmail->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');	
				
				$enable = 'n';
				$delete = 'n';
				$mail_fetchmail['mailget_id'] = '';
				$mail_fetchmail['server_id'] = '';
				$mail_fetchmail['type'] = '';
				$mail_fetchmail['source_server'] = '';
				$mail_fetchmail['source_username'] = '';
				$mail_fetchmail['source_delete'] = '';
			}
	
		}
		
		if ($delete == 'y') {
			$delete = 1;
		} else {
			$delete = 0;
		}
		if ($enabled == 'y') {
			$enabled = 1;
		} else {
			$enabled = 0;
		}
		 
	  /*auslesen end*/

    $rcmail->output->set_env('framed', true);
    
    // allow the following attributes to be added to the <table> tag
    $attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

    // return the complete edit form as table
    $out .= '<fieldset><legend>' . $this->gettext('fetchmail') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    // show fetchmail properties
		$hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $mail_fetchmail['mailget_id']));
		$out .= $hidden_id->show();
	
		$hidden_serverid = new html_hiddenfield(array('name' => '_serverid', 'value' => $mail_fetchmail['server_id']));
		$out .= $hidden_serverid->show();
		
		$field_id = 'fetchmailtyp';
    $input_fetchmailtyp = new html_select(array('name' => '_fetchmailtyp', 'id' => $field_id));
		$input_fetchmailtyp->add(array('POP3','IMAP'), array('pop3','imap'));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('fetchmailtyp')),
                $input_fetchmailtyp->show($mail_fetchmail['type']));
		
		$field_id = 'fetchmailserver';
    $input_fetchmailserver = new html_inputfield(array('name' => '_fetchmailserver', 'id' => $field_id, 'maxlength' => 320, 'size' => 40));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('fetchmailserver')),
                $input_fetchmailserver->show($mail_fetchmail['source_server']));
		
		$field_id = 'fetchmailuser';
    $input_fetchmailuser = new html_inputfield(array('name' => '_fetchmailuser', 'id' => $field_id, 'maxlength' => 320, 'size' => 40));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('username')),
                $input_fetchmailuser->show($mail_fetchmail['source_username']));
		
		$field_id = 'fetchmailpass';
    $input_fetchmailpass = new html_passwordfield(array('name' => '_fetchmailpass', 'id' => $field_id, 'maxlength' => 320, 'size' => 40, 'autocomplete' => 'off'));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('password')),
                $input_fetchmailpass->show($mail_fetchmail['source_password']));
     
		$field_id = 'fetchmaildelete';
    $input_fetchmaildelete = new html_checkbox(array('name' => '_fetchmaildelete', 'id' => $field_id, 'value' => '1'));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('fetchmaildelete')),
                $input_fetchmaildelete->show($delete));
		 
    $field_id = 'fetchmailenabled';
    $input_fetchmailenabled = new html_checkbox(array('name' => '_fetchmailenabled', 'id' => $field_id, 'value' => '1'));

    $out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
                $field_id,
                rep_specialchars_output($this->gettext('fetchmailenabled')),
                $input_fetchmailenabled->show($enabled));                                                

    $out .= "\n</table>";
    $out .= '<br />' . "\n";
    $out .= "</fieldset>\n";    

    $rcmail->output->add_gui_object('fetchmailform', 'fetchmail-form');
    
    return $out;
  }
	
	function gen_table($attrib)
	{
		$rcmail = rcmail::get_instance();
    $rcmail->output->set_env('framed', true);
		
		$out = '<fieldset><legend>'.$this->gettext('fetchmail_entries').' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
		
		$fetch_table = new html_table(array('id' => 'fetch-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$fetch_table->add_header(array('width' => '388px'), $this->gettext('fetchmailserver'));
		$fetch_table->add_header(array('width' => '16px'), '');
		$fetch_table->add_header(array('width' => '16px'), '');
		
		$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																			 'uri'      => $rcmail->config->get('soap_url')));
	
		try {
			
			$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
			$fetchmail = $client->mail_fetchmail_get($session_id, array('destination' => $rcmail->user->data['username']));
			
			for ( $i = 0; $i < count($fetchmail); $i++ )
			{
				$class = ( $class == 'odd' ? 'even' : 'odd' );
				
				if($fetchmail[$i]['mailget_id'] == get_input_value('_id', RCUBE_INPUT_GET)) {
					$class = 'selected';
				}
				
				$fetch_table->set_row_attribs(array('class' => $class,'id' => 'fetch_'.$fetchmail[$i]['mailget_id']));
				$this->_fetch_row($fetch_table,$fetchmail[$i]['source_server'],$fetchmail[$i]['active'],$fetchmail[$i]['mailget_id'],$attrib);
			}
			
			if(count($fetchmail) == 0)
			{
				$fetch_table->add(array('colspan' => '3'), rep_specialchars_output($this->gettext('nofetch')));
				$fetch_table->set_row_attribs(array('class' => 'odd'));
				$fetch_table->add_row();
			}
			
			$client->logout($session_id);
			
		} catch (SoapFault $e) {
			$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
		
		$out .= "<div id=\"fetch-cont\">".$fetch_table->show()."</div>\n";
		$out .= '<br />' . "\n";       
    $out .= "</fieldset>\n";
		return $out;
	}
	
	private function _fetch_row($fetch_table,$name,$active,$id,$attrib)
  {
	  $fetch_table->add(array('class' => 'fetch','onclick' => 'edit('.$id.');'), $name);
	  
	  $enable_button = html::img(array('src' => $attrib['enableicon'], 'alt' => $this->gettext('enabled'), 'border' => 0));
	  $disable_button = html::img(array('src' => $attrib['disableicon'], 'alt' => $this->gettext('disabled'), 'border' => 0));
	  
	  if($active == 'y') {
		  $status_button = $enable_button;
	  } else {
		  $status_button = $disable_button;
	  }
	  
	  $fetch_table->add(array('class' => 'control'), '&nbsp;'.$status_button);
	  
	  $del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_fetchmail.del', 'prop' => $id, 'type' => 'image', 'image' => $attrib['deleteicon'], 'alt' => $this->gettext('delete'), 'title' => $this->gettext('delete')));
	  $fetch_table->add(array('class' => 'control'), $del_button);

	  return $fetch_table;
  }
}

?>