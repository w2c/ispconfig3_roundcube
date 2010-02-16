<?php

class ispconfig3_filter extends rcube_plugin
{
  public $task = 'settings';

  function init()
  {
		$this->_load_config();
	  $this->add_texts('localization/', true);
    $rcmail = rcmail::get_instance();

    $this->register_action('plugin.ispconfig3_filter', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_filter.save', array($this, 'save'));
		$this->register_action('plugin.ispconfig3_filter.del', array($this, 'del'));
    $this->api->output->add_handler('filter_form', array($this, 'gen_form'));
		$this->api->output->add_handler('filter_table', array($this, 'gen_table'));
	  $this->api->output->add_handler('sectionname_filter', array($this, 'prefs_section_name'));
    $this->include_script('filter.js');
  }
	
	function _load_config()
  {
    $rcmail = rcmail::get_instance();
    $config = "plugins/ispconfig3_filter/config/config.inc.php";
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
    $rcmail->output->set_pagetitle($this->gettext('isp_filter')); 
    $rcmail->output->send('ispconfig3_filter.filter');
    
  }
  
  function prefs_section_name()
  {
	  return $this->gettext('isp_filter');
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
				$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
				$filter = $client->mail_user_filter_get($session_id, $id);
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
			if ($filter['mailuser_id'] == $mail_user[0]['mailuser_id']) {
				try {
					
					$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
					$delete = $client->mail_user_filter_delete($session_id, $id);
				
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
		
    $name        = get_input_value('_filtername', RCUBE_INPUT_POST);
    $source     = get_input_value('_filtersource', RCUBE_INPUT_POST);
		$op     = get_input_value('_filterop', RCUBE_INPUT_POST);
		$searchterm     = get_input_value('_filtersearchterm', RCUBE_INPUT_POST);
		$action     = get_input_value('_filteraction', RCUBE_INPUT_POST);
		$target     = get_input_value('_filtertarget', RCUBE_INPUT_POST);
		$enabled     = get_input_value('_filterenabled', RCUBE_INPUT_POST);
		
    if(!$enabled) {
      $enabled = 'n';
		} else {
			$enabled = 'y';
		}
		
		if($id == 0 || $id == '') {		
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																				 'uri'      => $rcmail->config->get('soap_url')));
			
			$limit = $rcmail->config->get('filter_limit');
			
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
				$filter = $client->mail_user_filter_get($session_id, array('mailuser_id' => $mail_user[0]['mailuser_id']));
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
			if(count($filter) < $limit) {
				try {
					
					$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
					
					$params = array('mailuser_id' => $mail_user[0]['mailuser_id'],
						  'rulename' => $name,
						  'source' => $source,
						  'searchterm' => $searchterm,
						  'op' => $op,
						  'action' => $action,
						  'target' => $target,
						  'active' => $enabled);
					
					$add = $client->mail_user_filter_add($session_id, 0, $params);
				
					$client->logout($session_id);
					$rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
					
				} catch (SoapFault $e) {
					$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
				}
			} else {
				$rcmail->output->command('display_message', 'Error: '.$this->gettext('filterlimitreached'), 'error');
			}
		} else {
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																	 'uri'      => $rcmail->config->get('soap_url')));
						
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
				$filter = $client->mail_user_filter_get($session_id, $id);
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}

	    if ($filter['mailuser_id'] == $mail_user[0]['mailuser_id']) {		
				try {
					
					$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
					
					$params = array('mailuser_id' => $mail_user[0]['mailuser_id'],
						  'rulename' => $name,
						  'source' => $source,
						  'searchterm' => $searchterm,
						  'op' => $op,
						  'action' => $action,
						  'target' => $target,
						  'active' => $enabled);
					
					$update = $client->mail_user_filter_update($session_id, $mail_user[0]['mailuser_id'], $id, $params);
				
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
		$rcmail->imap_init(TRUE);
		$id = get_input_value('_id', RCUBE_INPUT_GET);

    // add some labels to client
    $rcmail->output->add_label(
			'ispconfig3_filter.filterdelconfirm',
			'ispconfig3_filter.textempty'
    ); 
		if ($id != '' || $id != 0) {
			/*auslesen start*/
			$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																			 'uri'      => $rcmail->config->get('soap_url')));
	
			try {
				
				$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
				$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
				$filter = $client->mail_user_filter_get($session_id, array('filter_id' => $id)); 
				 
				$client->logout($session_id);
				
			} catch (SoapFault $e) {
				$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
			}
	    
			$enabled     = $filter[0]['active'];
			
	    if ($filter[0]['mailuser_id'] != $mail_user[0]['mailuser_id']) {
				$rcmail->output->command('display_message', 'Error: '.$this->gettext('opnotpermitted'), 'error');	
				
				$enabled = 'n';
				$mail_fetchmail['rulename'] = '';
				$mail_fetchmail['source'] = '';
				$mail_fetchmail['searchterm'] = '';
				$mail_fetchmail['op'] = '';
				$mail_fetchmail['action'] = '';
				$mail_fetchmail['target'] = '';
			}
	
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
    $out .= '<fieldset><legend>' . $this->gettext('isp_filter') . ' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
    $out .= '<table' . $attrib_str . ">\n\n";

    // show fetchmail properties
		$hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $filter[0]['filter_id']));
		$out .= $hidden_id->show();
		
		$field_id = 'filtername';
		$input_filtername = new html_inputfield(array('name' => '_filtername', 'id' => $field_id, 'size' => 70));

		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
								$field_id,
								rep_specialchars_output($this->gettext('filtername')),
								$input_filtername->show($filter[0]['rulename']));
		
		$field_id = 'filtersource';
		$input_filtersource = new html_select(array('name' => '_filtersource', 'id' => $field_id));
		$input_filtersource->add(array($this->gettext('filtersubject'),$this->gettext('filterfrom'),$this->gettext('filterto')), array('Subject','From','To'));
		
		$input_filterop = new html_select(array('name' => '_filterop', 'id' => 'filterop'));
		$input_filterop->add(array($this->gettext('filtercontains'),$this->gettext('filteris'),$this->gettext('filterbegins'),$this->gettext('filterends')),array('contains','is','begins','ends'));
		
		$input_filtersearchterm = new html_inputfield(array('name' => '_filtersearchterm', 'id' => 'filtersearchterm', 'size' => 43));
		
		$string = $input_filtersource->show($filter[0]['source']).$input_filterop->show($filter[0]['op']).$input_filtersearchterm->show($filter[0]['searchterm']);
		
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
									$field_id,
									rep_specialchars_output($this->gettext('filtersource')),
									$string);
		
		$field_id = 'filteraction';
		$input_filteraction = new html_select(array('name' => '_filteraction', 'id' => $field_id));
		$input_filteraction->add(array($this->gettext('filtermove'), $this->gettext('filterdelete')), array('move','delete'));
		
		$input_filtertarget = rcmail_mailbox_select(array('name' => '_filtertarget', 'id' => 'filtertarget'));
		
		$string = $input_filteraction->show($filter[0]['action']).$input_filtertarget->show($filter[0]['target']);
		
		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
									$field_id,
									rep_specialchars_output($this->gettext('filteraction')),
									$string);
		
		$field_id = 'filterenabled';
		$input_filterenabled = new html_checkbox(array('name' => '_filterenabled', 'id' => $field_id, 'value' => '1'));

		$out .= sprintf("<tr><td class=\"title\"><label for=\"%s\">%s</label>:</td><td>%s</td></tr>\n",
								$field_id,
								rep_specialchars_output($this->gettext('filterenabled')),
								$input_filterenabled->show($enabled));
		
    $out .= "\n</table>";
    $out .= '<br />' . "\n";
    $out .= "</fieldset>\n";  

    $rcmail->output->add_gui_object('filterform', 'filter-form');
    
    return $out;
  }
	
	function gen_table($attrib)
	{
		$rcmail = rcmail::get_instance();
    $rcmail->output->set_env('framed', true);
		
		$out = '<fieldset><legend>'.$this->gettext('filter_entries').' ::: ' . $rcmail->user->data['username'] . '</legend>' . "\n";
    $out .= '<br />' . "\n";
		
		$rule_table = new html_table(array('id' => 'rule-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
		$rule_table->add_header(array('width' => '388px'), $this->gettext('filter_entries'));
		$rule_table->add_header(array('width' => '16px'), '');
		$rule_table->add_header(array('width' => '16px'), '');
		
		$client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
																			 'uri'      => $rcmail->config->get('soap_url')));
	
		try {
			
			$session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
			$mail_user = $client->mail_user_get($session_id, array('email' => $rcmail->user->data['username']));
			$filter = $client->mail_user_filter_get($session_id, array('mailuser_id' => $mail_user[0]['mailuser_id']));
			
			for ( $i = 0; $i < count($filter); $i++ )
			{
				$class = ( $class == 'odd' ? 'even' : 'odd' );
				
				if($filter[$i]['filter_id'] == get_input_value('_id', RCUBE_INPUT_GET)) {
					$class = 'selected';
				}
				
				$rule_table->set_row_attribs(array('class' => $class,'id' => 'rule_'.$filter[$i]['filter_id']));
				$this->_rule_row($rule_table,$filter[$i]['rulename'],$filter[$i]['active'],$filter[$i]['filter_id'],$attrib);
			}
			
			if(count($filter) == 0)
			{
				$rule_table->add(array('colspan' => '3'), rep_specialchars_output($this->gettext('filternorules')));
				$rule_table->set_row_attribs(array('class' => 'odd'));
				$rule_table->add_row();
			}
			
			$client->logout($session_id);
			
		} catch (SoapFault $e) {
			$rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}
		
		$out .= "<div id=\"rule-cont\">".$rule_table->show()."</div>\n";
		$out .= '<br />' . "\n";       
    $out .= "</fieldset>\n";
		return $out;
	}
	
	private function _rule_row($rule_table,$name,$active,$id,$attrib)
  {
	  $rule_table->add(array('class' => 'rule','onclick' => 'edit('.$id.');'), $name);
	  
	  $enable_button = html::img(array('src' => $attrib['enableicon'], 'alt' => $this->gettext('enabled'), 'border' => 0));
	  $disable_button = html::img(array('src' => $attrib['disableicon'], 'alt' => $this->gettext('disabled'), 'border' => 0));
	  
	  if($active == 'y') {
		  $status_button = $enable_button;
	  } else {
		  $status_button = $disable_button;
	  }
	  
	  $rule_table->add(array('class' => 'control'), '&nbsp;'.$status_button);
	  
	  $del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_filter.del', 'prop' => $id, 'type' => 'image', 'image' => $attrib['deleteicon'], 'alt' => $this->gettext('delete'), 'title' => $this->gettext('delete')));
	  $rule_table->add(array('class' => 'control'), $del_button);

	  return $rule_table;
  }
}

?>