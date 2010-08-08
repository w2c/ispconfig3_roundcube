<?php

class ispconfig3_wblist extends rcube_plugin
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

		$this->register_action('plugin.ispconfig3_wblist', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_wblist.save', array($this, 'save'));
		$this->register_action('plugin.ispconfig3_wblist.del', array($this, 'del'));
		
		$this->api->output->add_handler('wblist_form', array($this, 'gen_form'));
		$this->api->output->add_handler('wblist_table', array($this, 'gen_table'));
		$this->api->output->add_handler('sectionname_wblist', array($this, 'prefs_section_name'));
		
		$this->include_script('wblist.js');
	}

	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('acc_wblist')); 
		$this->rcmail_inst->output->send('ispconfig3_wblist.wblist');
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
		return $this->gettext('acc_wblist');
	}

	function del()
	{
		$id = get_input_value('_id', RCUBE_INPUT_GET);

		if ($id != 0 || $id != '')
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
				
				if (get_input_value('_type', RCUBE_INPUT_GET) == "W")
					$wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, $id);
				else
					$wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, $id);
					
				if ($wblist['rid'] == $spam_user[0]['id'])
				{
					if (get_input_value('_type', RCUBE_INPUT_GET) == "W")
						$delete = $this->soap->mail_spamfilter_whitelist_delete($session_id, $id);
					else
						$delete = $this->soap->mail_spamfilter_blacklist_delete($session_id, $id);
						
					$this->rcmail_inst->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
				}

				$this->soap->logout($session_id);
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
		}
	}

	function save()
	{
		$id = get_input_value('_id', RCUBE_INPUT_POST);
		$type = get_input_value('_wblistwb', RCUBE_INPUT_POST);
		$email = get_input_value('_wblistemail', RCUBE_INPUT_POST);
		$priority = get_input_value('_wblistpriority', RCUBE_INPUT_POST);
		$enabled = get_input_value('_wblistenabled', RCUBE_INPUT_POST);

		if(!$enabled)
			$enabled = 'n';
		else
			$enabled = 'y';

		if($id == 0 || $id == '')
		{
			$limit = $this->rcmail_inst->config->get('wblist_limit');
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

				if ($spam_user[0]['id'] == '')
				{
					$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

					$params = array('server_id' => $mail_user[0]['server_id'],
									'priority' => '5',
									'policy_id' => '5',
									'email' => $this->rcmail_inst->user->data['username'],
									'fullname' => $this->rcmail_inst->user->data['username'],
									'local' => 'Y');
									
					$uid = $client->client_get_id($session_id, $mail_user[0]['sys_userid']);
					$add = $this->soap->mail_spamfilter_user_add($session_id, $uid, $params);
					$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
				}

				$wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, array('rid' => $spam_user[0]['id']));
				//$blist = $this->soap->mail_spamfilter_blacklist_get($session_id, array('rid' => $spam_user[0]['id']));
				//$wblist = array_merge($wlist, $blist);
				
				if(count($wblist) < $limit)
				{
					$params = array('sys_userid' => $spam_user[0]['sys_userid'],
									'sys_groupid' => $spam_user[0]['sys_groupid'],
									'server_id' => $spam_user[0]['server_id'],
									'rid' => $spam_user[0]['id'],
									'wb' => $type,
									'email' => $email,
									'priority' => $priority,
									'active' => $enabled);

					if ($type == "W")
						$add = $this->soap->mail_spamfilter_whitelist_add($session_id, 0, $params);
					else
						$add = $this->soap->mail_spamfilter_blacklist_add($session_id, 0, $params);
						
					$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
				}
				else
					$this->rcmail_inst->output->command('display_message', 'Error: '.$this->gettext('wblimitreached'), 'error');

				$this->soap->logout($session_id);
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
		}
		else
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
				$wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, $id);
				
				if ($wblist['rid'] == $spam_user[0]['id'])
				{
					$params = array('server_id' => $spam_user[0]['server_id'],
									'rid' => $spam_user[0]['id'],
									'wb' => $type,
									'email' => $email,
									'priority' => $priority,
									'active' => $enabled);

					$uid = $this->soap->client_get_id($session_id, $spam_user[0]['sys_userid']);

					if ($type == "W")
						$update = $this->soap->mail_spamfilter_whitelist_update($session_id, $id, $uid, $params);
					else
						$update = $this->soap->mail_spamfilter_blacklist_update($session_id, $id, $uid, $params);
						
					$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
				}
				else
					$this->rcmail_inst->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');
				
				$this->soap->logout($session_id);
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
		$id = get_input_value('_id', RCUBE_INPUT_GET);

		$this->rcmail_inst->output->add_label('ispconfig3_wblist.wblistdelconfirm',
												'ispconfig3_wblist.textempty'); 
									
		if ($id != '' || $id != 0)
		{
			try
			{
				$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
				$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
				
				if (get_input_value('_type', RCUBE_INPUT_GET) == "W")
				{
					$wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, array('wblist_id' => $id));
					$type = "W";
				}
				else
				{
					$wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, array('wblist_id' => $id)); 
					$type = "B";
				}

				$this->soap->logout($session_id);
			}
			catch (SoapFault $e)
			{
				$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}

			$enabled = $wblist[0]['active'];

			if ($wblist[0]['rid'] != $spam_user[0]['id'])
			{
				$this->rcmail_inst->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');	

				$enabled = 'n';
				$wblist[0]['email'] = '';
				$wblist[0]['priority'] = '';
			}
		}
		else
		{
			$wblist[0]['priority'] = '5';
		}

		if ($enabled == 'y')
			$enabled = 1;
		else
			$enabled = 0;

		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$out .= '<fieldset><legend>' . $this->gettext('acc_wblist') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		$hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $wblist[0]['wblist_id']));
		$out .= $hidden_id->show();

		$field_id = 'wblistaddress';
		$input_wblistemail = new html_inputfield(array('name' => '_wblistemail', 'id' => $field_id, 'size' => 70));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('email')),
						$input_wblistemail->show($wblist[0]['email']));

		$field_id = 'wblistwb';
		$input_wblistwb = new html_select(array('name' => '_wblistwb', 'id' => $field_id));
		$input_wblistwb->add(array($this->gettext('wblistwhitelist'),$this->gettext('wblistblacklist')), array('W','B'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('wblisttype')),
						$input_wblistwb->show($type));

		$input_wblistpriority = new html_select(array('name' => '_wblistpriority', 'id' => 'wblistpriority'));
		$input_wblistpriority->add(array("1","2","3","4","5","6","7","8","9","10"));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('wblistpriority')),
						$input_wblistpriority->show($wblist[0]['priority']));

		$field_id = 'wblistenabled';
		$input_wblistenabled = new html_checkbox(array('name' => '_wblistenabled', 'id' => $field_id, 'value' => '1'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('wblistenabled')),
						$input_wblistenabled->show($enabled));

		$out .= "\n</table>";
		$out .= '<br />' . "\n";
		$out .= "</fieldset>\n";  

		$this->rcmail_inst->output->add_gui_object('wblistform', 'wblist-form');

		return $out;
	}

	function gen_table($attrib)
	{
		$this->rcmail_inst->output->set_env('framed', true);

		$out = '<fieldset><legend>'.$this->gettext('wblistentries').' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";

		$rule_table = new html_table(array('id' => 'rule-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 4));
		$rule_table->add_header(array('width' => '370px'), $this->gettext('wblistentries'));
		$rule_table->add_header(array('width' => '16px'), '');
		$rule_table->add_header(array('width' => '16px'), '');
		$rule_table->add_header(array('width' => '16px'), '');

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
			$wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, array('rid' => $spam_user[0]['id']));
			//$blist = $this->soap->mail_spamfilter_blacklist_get($session_id, array('rid' => $spam_user[0]['id']));
			//$wblist = array_merge($wlist, $blist);
			$this->soap->logout($session_id);

			for ( $i = 0; $i < count($wblist); $i++ )
			{
				$class = ( $class == 'odd' ? 'even' : 'odd' );

				if($wblist[$i]['wblist_id'] == get_input_value('_id', RCUBE_INPUT_GET))
					$class = 'selected';

				$rule_table->set_row_attribs(array('class' => $class,'id' => 'rule_'.$wblist[$i]['wblist_id']));
				$this->_rule_row($rule_table,$wblist[$i]['email'],$wblist[$i]['wb'],$wblist[$i]['active'],$wblist[$i]['wblist_id'],$attrib);
			}
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
		
		
		if(count($wblist) == 0)
		{
			$rule_table->add(array('colspan' => '4'), rep_specialchars_output($this->gettext('wblistnorules')));
			$rule_table->set_row_attribs(array('class' => 'odd'));
			$rule_table->add_row();
		}


		$out .= "<div id=\"rule-cont\">".$rule_table->show()."</div>\n";
		$out .= '<br />' . "\n";       
		$out .= "</fieldset>\n";
		
		return $out;
	}

	private function _rule_row($rule_table,$name,$wb,$active,$id,$attrib)
	{
		$rule_table->add(array('class' => 'rule','onclick' => 'wb_edit('.$id.',"'.$wb.'");'), $name);

		$white_button = html::img(array('src' => $attrib['whiteicon'], 'alt' => "W", 'border' => 0));
		$black_button = html::img(array('src' => $attrib['blackicon'], 'alt' => "B", 'border' => 0));

		if ($wb == "W")
			$rule_table->add(array('class' => 'control'), $white_button);
		else
			$rule_table->add(array('class' => 'control'), $black_button);


		$enable_button = html::img(array('src' => $attrib['enableicon'], 'alt' => $this->gettext('enabled'), 'border' => 0));
		$disable_button = html::img(array('src' => $attrib['disableicon'], 'alt' => $this->gettext('disabled'), 'border' => 0));

		if($active == 'y')
			$status_button = $enable_button;
		else
			$status_button = $disable_button;

		$rule_table->add(array('class' => 'control'), '&nbsp;'.$status_button);

		$del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_wblist.del', 'prop' => $id.'\',\''.$wb, 'type' => 'image',
														'image' => $attrib['deleteicon'], 'alt' => $this->gettext('delete'),
														'title' => $this->gettext('delete')));
														
		$rule_table->add(array('class' => 'control'), $del_button);

		return $rule_table;
	}
}
?>