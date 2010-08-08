<?php

class ispconfig3_spam extends rcube_plugin
{
	public $task = 'settings';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->add_texts('localization/', true);
		$this->rcmail_inst = rcmail::get_instance();
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));

		$this->register_action('plugin.ispconfig3_spam', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_spam.save', array($this, 'save'));
		
		$this->api->output->add_handler('spam_form', array($this, 'gen_form'));
		$this->api->output->add_handler('sectionname_spam', array($this, 'prefs_section_name'));
		$this->api->output->add_handler('spam_table', array($this, 'gen_table'));
		
		$this->include_script('spam.js');
	}

	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('junk')); 
		$this->rcmail_inst->output->send('ispconfig3_spam.spam');
	}

	function prefs_section_name()
	{
		return $this->gettext('junk');
	}  

	function save()
	{
		$policy_id = get_input_value('_spampolicy_name', RCUBE_INPUT_POST);
		$move_junk = get_input_value('_spammove', RCUBE_INPUT_POST);

		if(!$move_junk)
			$move_junk = 'n';
		else
			$move_junk = 'y';

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

			if ($spam_user[0]['id'] == '')
			{
				$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));

				$params = array('server_id' => $mail_user[0]['server_id'],
								'priority' => '5',
								'policy_id' => $policy_id,
								'email' => $this->rcmail_inst->user->data['username'],
								'fullname' => $this->rcmail_inst->user->data['username'],
								'local' => 'Y');

				$uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);
				$add = $this->soap->mail_spamfilter_user_add($session_id, $uid, $params);
				$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
			}
			else
			{
				$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
				$uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);
			}

			$params = array('server_id' => $spam_user[0]['server_id'],
							'priority' => $spam_user[0]['priority'],
							'policy_id' => $policy_id,
							'email' => $this->rcmail_inst->user->data['username'],
							'fullname' => $this->rcmail_inst->user->data['username'],
							'local' => $spam_user[0]['local']);

			$update = $this->soap->mail_spamfilter_user_update($session_id, $spam_user[0]['id'], $uid, $params);

			$params = array('server_id' => $mail_user[0]['server_id'],
							'email' => $this->rcmail_inst->user->data['username'],
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
							'move_junk' => $move_junk,
							'custom_mailfilter' => $mail_user[0]['custom_mailfilter'],
							'postfix' => $mail_user[0]['postfix'],
							'access' => $mail_user[0]['access'],
							'disableimap' => $mail_user[0]['disableimap'],
							'disablepop3' => $mail_user[0]['disablepop3'],
							'disabledeliver' => $mail_user[0]['disabledeliver'],
							'disablesmtp' => $mail_user[0]['disablesmtp']);

			$update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
			$this->soap->logout($session_id);
			
			$this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		$this->init_html();
	}

	function gen_form()
	{
		$policy_name = array();
		$policy_id = array();

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
			$policy = $this->soap->mail_policy_get($session_id, array(1 => 1));
			$policy_sel = $this->soap->mail_policy_get($session_id, array("id" => $spam_user[0]['policy_id']));
			$this->soap->logout($session_id);
			
			for ( $i = 0; $i < count($policy); $i++ )
			{
				$policy_name[] = $policy[$i]['policy_name'];
				$policy_id[] = $policy[$i]['id'];
			}
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		$enabled = $mail_user[0]['move_junk'];
			
		if ($enabled == 'y')
			$enabled = 1;
		else
			$enabled = 0;

		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$out .= '<fieldset><legend>' . $this->gettext('junk') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		$field_id = 'policy_name';
		$input_spampolicy_name = new html_select(array('name' => '_spampolicy_name', 'id' => $field_id));
		$input_spampolicy_name->add($policy_name,$policy_id);
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('policy_name')),
						$input_spampolicy_name->show($policy_sel[0]['policy_name']));

		$field_id = 'spammove';
		$input_spammove = new html_checkbox(array('name' => '_spammove', 'value' => '1'));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('spammove')),
						$input_spammove->show($enabled));

		$out .= "\n</table>";
		$out .= '<br />' . "\n";
		$out .= "</fieldset>\n"; 

		$this->rcmail_inst->output->add_gui_object('spamform', 'spam-form');

		return $out;
	}

	function gen_table($attrib)
	{
		$this->rcmail_inst->output->set_env('framed', true);

		$out = '<fieldset><legend>'.$this->gettext('policy_entries').' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";

		$spam_table = new html_table(array('id' => 'spam-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$spam_table->add_header(array('width' => '220px'), $this->gettext('policy_entries'));
		$spam_table->add_header(array('width' => '150px'), $this->gettext('policy_tag'));
		$spam_table->add_header(array('width' => '100px'), $this->gettext('policy_kill'));

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
			$policies = $this->soap->mail_policy_get($session_id, array(1 => 1));

			for ( $i = 0; $i < count($policies); $i++ )
			{
				$class = ( $class == 'odd' ? 'even' : 'odd' );

				if($policies[$i]['id'] == $spam_user[0]['policy_id'])
					$class = 'selected';

				$spam_table->set_row_attribs(array('class' => $class));
				
				$this->_spam_row($spam_table,$policies[$i]['policy_name'],$policies[$i]['spam_tag_level'],$policies[$i]['spam_kill_level'],$attrib);
			}

			$this->soap->logout($session_id);
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		if(count($policies) == 0)
		{
			$spam_table->add(array('colspan' => '3'), rep_specialchars_output($this->gettext('spamnopolicies')));
			$spam_table->set_row_attribs(array('class' => 'odd'));
			$spam_table->add_row();
		}

		$out .= "<div id=\"spam-cont\">".$spam_table->show()."</div>\n";
		$out .= '<br />' . "\n";       
		$out .= "</fieldset>\n";
		
		return $out;
	}

	private function _spam_row($spam_table,$name,$tag,$kill,$attrib)
	{
		$spam_table->add(array('class' => 'policy'), $name);
		$spam_table->add(array('class' => 'value'), '&nbsp;'.$tag);
		$spam_table->add(array('class' => 'value'), $kill);

		return $spam_table;
	}
}
?>