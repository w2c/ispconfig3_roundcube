<?php

class ispconfig3_account extends rcube_plugin
{
  public $task = 'settings';
  private $sections = array();

  function init()
  {  
  	$this->_load_config();
	$this->add_texts('localization/', true);
	
	$this->sections = array(
		  'general' => array('id' => 'general', 'section' => $this->gettext('isp_general')),
		  'pass' => array('id' => 'pass', 'section' => $this->gettext('isp_pass')),
			'fetchmail' => array('id' => 'fetchmail','section' => $this->gettext('isp_fetchmail')),
		  'forward' => array('id' => 'forward', 'section' => $this->gettext('isp_forward')),
		  'autoreply' => array('id' => 'autoreply', 'section' => $this->gettext('isp_autoreply')),
		  'filter' => array('id' => 'filter','section' => $this->gettext('isp_filter')),
		  'spam' => array('id' => 'spam','section' => $this->gettext('junk')),
	  );
	
	$this->register_action('plugin.ispconfig3_account', array($this, 'init_html'));
	$this->register_action('plugin.ispconfig3_account.show', array($this, 'init_html'));
	
	$this->include_script('account.js');
  }

  function init_html()
  {
	$this->api->output->set_pagetitle($this->gettext('isp_acc'));
	
	if (rcmail::get_instance()->action == 'plugin.ispconfig3_account.show') {
		$this->api->output->add_handler('info', array($this, 'gen_form'));
		$this->api->output->add_handler('sectionname_acc', array($this, 'prefs_section_name'));
		$this->api->output->send('ispconfig3_account.general');
	} else {
		$this->api->output->add_handler('accsectionslist', array($this, 'section_list'));
		$this->api->output->add_handler('accprefsframe', array($this, 'preference_frame'));
		$this->api->output->send('ispconfig3_account.account');
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
	  $rcmail = rcmail::get_instance();

	  // add id to message list table if not specified
	  if (!strlen($attrib['id']))
		  $attrib['id'] = 'rcmsectionslist';

	  $sections = array();
	  $array = array('general');
	  $plugins = $rcmail->config->get('plugins');
	  $plugins = array_flip($plugins);
	  
	  
	  if (isset($plugins['ispconfig3_pass'])) {
		  array_push($array,'pass');
	  } 
		if (isset($plugins['ispconfig3_fetchmail'])) {
		  array_push($array,'fetchmail');
	  }
	  if (isset($plugins['ispconfig3_forward'])) {
		  array_push($array,'forward');
	  }
	  if (isset($plugins['ispconfig3_autoreply'])) {
		  array_push($array,'autoreply');
	  }
	  if (isset($plugins['ispconfig3_filter'])) {
		  array_push($array,'filter');
	  }
	  if (isset($plugins['ispconfig3_spam'])) {
		  array_push($array,'spam');
	  }
	  $blocks = $attrib['sections'] ? preg_split('/[\s,;]+/', strip_quotes($attrib['sections'])) : $array;
	  foreach ($blocks as $block)
		  $sections[$block] = $this->sections[$block];

	  // create XHTML table
	  $out = rcube_table_output($attrib, $sections, array('section'), 'id');

	  // set client env
	  $rcmail->output->add_gui_object('sectionslist', $attrib['id']);
	  $rcmail->output->include_script('list.js');

	  return $out;
  }
  
  function prefs_section_name()
  {
	  return $this->gettext('isp_general');
  }

  function gen_form()
  {
    $rcmail = rcmail::get_instance();
    $user = $rcmail->user;
    
	$rcmail->output->set_env('framed', true);
	
	$out = '<fieldset><legend>'.$this->gettext('isp_general').' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
	
    $table = new html_table(array('cols' => 2, 'cellpadding' => 3));
    
    $table->add('title', Q($this->gettext('username')));
    $table->add('', Q($user->data['username']));
    
    $table->add('title', Q($this->gettext('server')));
    $table->add('', Q($user->data['mail_host']));

    $table->add('title', Q($this->gettext('lastlogin')));
    $table->add('', Q($user->data['last_login']));
    
    $identity = $user->get_identity();
    $table->add('title', Q($this->gettext('defaultidentity')));
    $table->add('', Q($identity['name'] . ' <' . $identity['email'] . '>'));
	$out .= $table->show();
	$out .= '<br />' . "\n";       
    $out .= "</fieldset>\n";
	
	$out .= '<fieldset><legend>'.$this->gettext('isp_alias').' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
	
	$alias_table = new html_table(array('id' => 'alias-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 1));
	$alias_table->add_header(array('width' => '100%'), $this->gettext('mail'));
	
	$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
                                     'uri'      => $rcmail->config->get('soap_url')));

	try {
		
		$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
		$alias = $client->mail_alias_get($session_id, array('destination' => $rcmail->user->data['username'], 'type' => 'alias', 'active' => 'y'));

		for ( $i = 0; $i < count($alias); $i++ )
		{
			$class = ( $class == 'odd' ? 'even' : 'odd' );
		 	$alias_table->set_row_attribs(array('class' => $class));
			$alias_table->add('', $alias[$i]['source']);
		}
		
		if(count($alias) == 0)
		{
			$alias_table->add('', rep_specialchars_output($this->gettext('noalias')));
			$alias_table->set_row_attribs(array('class' => 'odd'));
			$alias_table->add_row();
		}
		
		$client->logout($session_id);
		
	} catch (SoapFault $e) {
		$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
	}
	
	$out .= "<div id=\"alias-cont\">".$alias_table->show()."</div>\n";
	
	$out .= '<br />' . "\n";       
    $out .= "</fieldset>\n";
    
    return $out;
  }

  function _load_config()
  {
    $rcmail = rcmail::get_instance();
    $config = "plugins/ispconfig3_account/config/config.inc.php";
    if(file_exists($config))
      include $config;
    else if(file_exists($config . ".dist"))
      include $config . ".dist";
    if(is_array($rcmail_config)){
      $arr = array_merge($rcmail->config->all(),$rcmail_config);
      $rcmail->config->merge($arr);
    }
  } 
}
?>