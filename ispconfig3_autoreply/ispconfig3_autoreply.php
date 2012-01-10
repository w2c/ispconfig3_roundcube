<?php

class ispconfig3_autoreply extends rcube_plugin
{
	public $task = 'settings';
	private $soap = NULL;
	private $rcmail_inst = NULL;
  private $required_plugins = array('jqueryui');

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
		$this->add_texts('localization/', true);
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));

		$this->register_action('plugin.ispconfig3_autoreply', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_autoreply.save', array($this, 'save'));
		
		$this->api->output->add_handler('autoreply_form', array($this, 'gen_form'));
		$this->api->output->add_handler('sectionname_autoreply', array($this, 'prefs_section_name'));
		
		$skin = $this->rcmail_inst->config->get('skin');
		$this->include_stylesheet('skins/'.$skin.'/css/jquery/jquery.ui.datetime.css');
		
		$this->include_script('skins/default/js/jquery.ui.datetime.min.js');
		$this->include_script('autoreply.js');
	}

	function init_html()
	{
		$this->rcmail_inst->output->set_pagetitle($this->gettext('acc_autoreply')); 
		$this->rcmail_inst->output->send('ispconfig3_autoreply.autoreply');
	}

	function prefs_section_name()
	{
		return $this->gettext('acc_autoreply');
	}

	function save()
	{ 
		$user = $this->rcmail_inst->user->data['username'];
		$enabled = get_input_value('_autoreplyenabled', RCUBE_INPUT_POST);
		$body = get_input_value('_autoreplybody', RCUBE_INPUT_POST);
		$startdate = get_input_value('_autoreplystarton', RCUBE_INPUT_POST);
		$enddate = get_input_value('_autoreplyendby', RCUBE_INPUT_POST);
		
		$startdate = array('year' => substr($startdate,0,4),
							'month' => substr($startdate,5,2),
							'day' => substr($startdate,8,2),
							'hour' => substr($startdate,11,2),
							'minute' => substr($startdate,14,2));
							
		$enddate = array('year' => substr($enddate,0,4),
						'month' => substr($enddate,5,2),
						'day' => substr($enddate,8,2),
						'hour' => substr($enddate,11,2),
						'minute' => substr($enddate,14,2));
		
		if(!$enabled)
			$enabled = 'n';
		else
			$enabled = 'y';

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));
      $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);
      
      $params = $mail_user[0];
      unset($params['password']);
      $params['autoresponder'] = $enabled;
      $params['autoresponder_text'] = $body;
      $params['autoresponder_start_date'] = $startdate;
      $params['autoresponder_end_date'] = $enddate;
      
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
		$this->rcmail_inst->output->add_label('ispconfig3_autoreply.textempty'); 

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail_inst->user->data['username']));  
			$this->soap->logout($session_id);
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		$enabled     = $mail_user[0]['autoresponder'];
		
		if ($enabled == 'y')
			$enabled = 1;
		else
			$enabled = 0;
			
		if ($mail_user[0]['autoresponder_start_date'] == '0000-00-00 00:00:00' || strtotime($mail_user[0]['autoresponder_start_date']) <= time())
			$mail_user[0]['autoresponder_start_date'] = date('Y').'-'.date('m').'-'.date('d').' '.date('H').':'.date('i');
			
		if ($mail_user[0]['autoresponder_end_date'] == '0000-00-00 00:00:00')
			$mail_user[0]['autoresponder_end_date'] = date('Y').'-'.date('m').'-'.date('d').' '.date('H').':'.date('i');

		$this->rcmail_inst->output->set_env('framed', true);

		$attrib_str = create_attrib_string($attrib, array('style', 'class', 'id', 'cellpadding', 'cellspacing', 'border', 'summary'));

		$hidden_priority = new html_hiddenfield(array('name' => '_priority', 'value' => $priority, 'id' => 'priority'));
		$out .= $hidden_priority->show();

		$out .= '<fieldset><legend>' . $this->gettext('acc_autoreply') . ' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";
		$out .= '<table' . $attrib_str . ">\n\n";

		$field_id = 'autoreplybody';
		$input_autoreplybody = new html_textarea(array('name' => '_autoreplybody', 'id' => $field_id, 'cols' => 48, 'rows' => 15));
		$out .= sprintf("<tr><td valign=\"top\" class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplymessage')),
						$input_autoreplybody->show($mail_user[0]['autoresponder_text']));

		$field_id = 'autoreplystarton';
		$input_autoreplystarton = new html_inputfield(array('name' => '_autoreplystarton', 'id' => $field_id, 'size' => 20));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplystarton')),
						$input_autoreplystarton->show($mail_user[0]['autoresponder_start_date']));

		$field_id = 'autoreplyendby';
		$input_autoreplyendby = new html_inputfield(array('name' => '_autoreplyendby', 'id' => $field_id, 'size' => 20));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplyendby')),
						$input_autoreplyendby->show($mail_user[0]['autoresponder_end_date']));
		
		$field_id = 'autoreplyenabled';
		$input_autoreplyenabled = new html_checkbox(array('name' => '_autoreplyenabled', 'id' => $field_id, 'value' => 1));
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
						$field_id,
						rep_specialchars_output($this->gettext('autoreplyenabled')),
						$input_autoreplyenabled->show($enabled?1:0));

		$out .= "\n</table>";
		$out .= '<br />' . "\n";
		$out .= "</fieldset>\n";    

		$this->rcmail_inst->output->add_gui_object('autoreplyform', 'autoreply-form');

		return $out;
	}
}
?>