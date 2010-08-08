<?php

class ispconfig3_account extends rcube_plugin
{
	public $task = 'settings';
	private $sections = array();
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{  
		$this->rcmail_inst = rcmail::get_instance();
		$this->load_config();
		$this->add_texts('localization/', true);
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));

		$this->sections = array('general' => array('id' => 'general', 'section' => $this->gettext('acc_general')),
								'pass' => array('id' => 'pass', 'section' => $this->gettext('acc_pass')),
								'fetchmail' => array('id' => 'fetchmail','section' => $this->gettext('acc_fetchmail')),
								'forward' => array('id' => 'forward', 'section' => $this->gettext('acc_forward')),
								'autoreply' => array('id' => 'autoreply', 'section' => $this->gettext('acc_autoreply')),
								'filter' => array('id' => 'filter','section' => $this->gettext('acc_filter')),
								'wblist' => array('id' => 'wblist','section' => $this->gettext('acc_wblist')),
								'spam' => array('id' => 'spam','section' => $this->gettext('junk')));

		$this->register_action('plugin.ispconfig3_account', array($this, 'init_html'));
		$this->register_action('plugin.ispconfig3_account.show', array($this, 'init_html'));

		$this->include_script('account.js');
	}

	function init_html()
	{
		$this->api->output->set_pagetitle($this->gettext('acc_acc'));

		if (rcmail::get_instance()->action == 'plugin.ispconfig3_account.show')
		{
			$this->api->output->add_handler('info', array($this, 'gen_form'));
			$this->api->output->add_handler('sectionname_acc', array($this, 'prefs_section_name'));
			$this->api->output->send('ispconfig3_account.general');
		}
		else
		{
			$this->api->output->add_handler('accsectionslist', array($this, 'section_list'));
			$this->api->output->add_handler('accprefsframe', array($this, 'preference_frame'));
			$this->api->output->send('ispconfig3_account.account');
		}
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

	function preference_frame($attrib)
	{
		if (!$attrib['id'])
			$attrib['id'] = 'rcmprefsframe';

		$attrib['name'] = $attrib['id'];	  

		$this->api->output->set_env('contentframe', $attrib['name']);
		$this->api->output->set_env('blankpage', $attrib['src'] ? $this->api->output->abs_url($attrib['src']) : 'program/blank.gif');

		return html::iframe($attrib);
	}

	function section_list($attrib)
	{
		if (!strlen($attrib['id']))
		$attrib['id'] = 'rcmsectionslist';

		$sections = array();
		$array = array('general');
		$plugins = $this->rcmail_inst->config->get('plugins');
		$plugins = array_flip($plugins);

		if (isset($plugins['ispconfig3_pass']))
			array_push($array,'pass'); 
			
		if (isset($plugins['ispconfig3_fetchmail']))
			array_push($array,'fetchmail');
			
		if (isset($plugins['ispconfig3_forward']))
			array_push($array,'forward');

		if (isset($plugins['ispconfig3_autoreply']))
			array_push($array,'autoreply');

		if (isset($plugins['ispconfig3_filter']))
			array_push($array,'filter');
			
		if (isset($plugins['ispconfig3_wblist']))
			array_push($array,'wblist');
			
		if (isset($plugins['ispconfig3_spam']))
			array_push($array,'spam');
			
		$blocks = $attrib['sections'] ? preg_split('/[\s,;]+/', strip_quotes($attrib['sections'])) : $array;
		
		foreach ($blocks as $block)
			$sections[$block] = $this->sections[$block];

		$out = rcube_table_output($attrib, $sections, array('section'), 'id');

		$this->rcmail_inst->output->add_gui_object('sectionslist', $attrib['id']);
		$this->rcmail_inst->output->include_script('list.js');

		return $out;
	}

	function prefs_section_name()
	{
		return $this->gettext('acc_general');
	}

	function gen_form()
	{
		$this->rcmail_inst->output->set_env('framed', true);

		$out = '<fieldset><legend>'.$this->gettext('acc_general').' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";

		$table = new html_table(array('cols' => 2, 'cellpadding' => 3));

		$table->add('title', Q($this->gettext('username')));
		$table->add('', Q($this->rcmail_inst->user->data['username']));

		$table->add('title', Q($this->gettext('server')));
		$table->add('', Q($this->rcmail_inst->user->data['mail_host']));

		$table->add('title', Q($this->gettext('acc_lastlogin')));
		$table->add('', Q($this->rcmail_inst->user->data['last_login']));

		$identity = $this->rcmail_inst->user->get_identity();
		$table->add('title', Q($this->gettext('acc_defaultidentity')));
		$table->add('', Q($identity['name'] . ' <' . $identity['email'] . '>'));
		$out .= $table->show();
		$out .= '<br />' . "\n";       
		$out .= "</fieldset>\n";

		$out .= '<fieldset><legend>'.$this->gettext('acc_alias').' ::: ' . $this->rcmail_inst->user->data['username'] . '</legend>' . "\n";
		$out .= '<br />' . "\n";

		$alias_table = new html_table(array('id' => 'alias-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 1));
		$alias_table->add_header(array('width' => '100%'), $this->gettext('mail'));

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$alias = $this->soap->mail_alias_get($session_id, array('destination' => $this->rcmail_inst->user->data['username'], 'type' => 'alias', 'active' => 'y'));
			$this->soap->logout($session_id);
			
			for ( $i = 0; $i < count($alias); $i++ )
			{
				$class = ( $class == 'odd' ? 'even' : 'odd' );
				$alias_table->set_row_attribs(array('class' => $class));
				$alias_table->add('', $alias[$i]['source']);
			}
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
		
		if(count($alias) == 0)
		{
			$alias_table->add('', rep_specialchars_output($this->gettext('acc_noalias')));
			$alias_table->set_row_attribs(array('class' => 'odd'));
			$alias_table->add_row();
		}

		$out .= "<div id=\"alias-cont\">".$alias_table->show()."</div>\n";
		$out .= '<br />' . "\n";       
		$out .= "</fieldset>\n";

		return $out;
	} 
}
?>