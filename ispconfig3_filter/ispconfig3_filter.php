<?php
class ispconfig3_filter extends rcube_plugin
{
  public $task = 'settings';
  private $soap = NULL;
  private $rcmail_inst = NULL;
  private $required_plugins = array('ispconfig3_account');

  function init()
  {
    $this->rcmail_inst = rcmail::get_instance();
    $this->load_config();
    $this->add_texts('localization/', TRUE);
    $this->soap = new SoapClient(NULL, array('location' => $this->rcmail_inst->config->get('soap_url') . 'index.php',
      'uri' => $this->rcmail_inst->config->get('soap_url')));

    $this->register_action('plugin.ispconfig3_filter', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_filter.show', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_filter.save', array($this, 'save'));
    $this->register_action('plugin.ispconfig3_filter.del', array($this, 'del'));

    $this->api->output->add_handler('filter_form', array($this, 'gen_form'));
    $this->api->output->add_handler('filter_table', array($this, 'gen_table'));
    $this->api->output->add_handler('sectionname_filter', array($this, 'prefs_section_name'));

    $this->include_script('filter.js');
  }

  function init_html()
  {
    $this->rcmail_inst->output->set_pagetitle($this->gettext('acc_filter'));
    $this->rcmail_inst->output->send('ispconfig3_filter.filter');
  }

  function load_config()
  {
    $config = $this->home . '/config/config.inc.php';
    if (file_exists($config)) {
      if (!$this->rcmail_inst->config->load_from_file($config))
        raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), TRUE, FALSE);
    }
    else if (file_exists($config . ".dist")) {
      if (!$this->rcmail_inst->config->load_from_file($config . '.dist'))
        raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), TRUE, FALSE);
    }
  }

  function prefs_section_name()
  {
    return $this->gettext('acc_filter');
  }

  function del()
  {
    $id = get_input_value('_id', RCUBE_INPUT_GET);

    if ($id != 0 || $id != '') {
      try {
        $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
        $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
        $filter = $this->soap->mail_user_filter_get($session_id, $id);

        if ($filter['mailuser_id'] == $mail_user[0]['mailuser_id']) {
          $delete = $this->soap->mail_user_filter_delete($session_id, $id);

          $this->rcmail_inst->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
        }

        $this->soap->logout($session_id);
      } catch (SoapFault $e) {
        $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
      }
    }
  }

  function save()
  {
    $id = get_input_value('_id', RCUBE_INPUT_POST);
    $name = get_input_value('_filtername', RCUBE_INPUT_POST);
    $source = get_input_value('_filtersource', RCUBE_INPUT_POST);
    $op = get_input_value('_filterop', RCUBE_INPUT_POST);
    $searchterm = get_input_value('_filtersearchterm', RCUBE_INPUT_POST);
    $action = get_input_value('_filteraction', RCUBE_INPUT_POST);
    $target = get_input_value('_filtertarget', RCUBE_INPUT_POST);
    $enabled = get_input_value('_filterenabled', RCUBE_INPUT_POST);

    if (!$enabled)
      $enabled = 'n';
    else
      $enabled = 'y';

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'mail');
      $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

      if ($mail_server['mail_filter_syntax'] == 'maildrop')
        $target = str_replace("INBOX.", "", $target);

      if ($id == 0 || $id == '') {
        $filter = $this->soap->mail_user_filter_get($session_id, array('mailuser_id' => $mail_user[0]['mailuser_id']));
        $limit = $this->rcmail_inst->config->get('filter_limit');

        if (count($filter) < $limit) {
          $params = array('mailuser_id' => $mail_user[0]['mailuser_id'],
            'rulename' => $name,
            'source' => $source,
            'searchterm' => $searchterm,
            'op' => $op,
            'action' => $action,
            'target' => $target,
            'active' => $enabled);

          $add = $this->soap->mail_user_filter_add($session_id, $uid, $params);

          $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
        }
        else
          $this->rcmail_inst->output->command('display_message', 'Error: ' . $this->gettext('filterlimitreached'), 'error');

      }
      else {
        $filter = $this->soap->mail_user_filter_get($session_id, $id);
        if ($filter['mailuser_id'] == $mail_user[0]['mailuser_id']) {
          $params = array('mailuser_id' => $mail_user[0]['mailuser_id'],
            'rulename' => $name,
            'source' => $source,
            'searchterm' => $searchterm,
            'op' => $op,
            'action' => $action,
            'target' => $target,
            'active' => $enabled);

          $update = $this->soap->mail_user_filter_update($session_id, $uid, $id, $params);
        }
        else
          $this->rcmail_inst->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');
      }

      $this->soap->logout($session_id);
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }

    $this->init_html();
  }

  function gen_form()
  {
    $this->rcmail_inst->imap_connect(TRUE);
    $id = get_input_value('_id', RCUBE_INPUT_GET);

    $this->rcmail_inst->output->add_label('ispconfig3_filter.filterdelconfirm');

    if ($id != '' || $id != 0) {
      try {
        $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
        $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
        $filter = $this->soap->mail_user_filter_get($session_id, array('filter_id' => $id));
        $mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'mail');
        $this->soap->logout($session_id);
      } catch (SoapFault $e) {
        $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
      }

      $enabled = $filter[0]['active'];

      if ($filter[0]['mailuser_id'] != $mail_user[0]['mailuser_id']) {
        $this->rcmail_inst->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');

        $enabled = 'n';
        $mail_fetchmail['rulename'] = '';
        $mail_fetchmail['source'] = '';
        $mail_fetchmail['searchterm'] = '';
        $mail_fetchmail['op'] = '';
        $mail_fetchmail['action'] = '';
        $mail_fetchmail['target'] = '';
      }

      if ($mail_server['mail_filter_syntax'] == 'maildrop')
        $filter[0]['target'] = "INBOX." . $filter[0]['target'];
    }

    if ($enabled == 'y')
      $enabled = 1;
    else
      $enabled = 0;

    $this->rcmail_inst->output->set_env('framed', TRUE);

    $out .= '<fieldset><legend>' . $this->gettext('acc_filter') . '</legend>' . "\n";

    $table = new html_table(array('cols' => 2, 'class' => 'propform'));

    $hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $filter[0]['filter_id']));
    $out .= $hidden_id->show();

    $input_filtername = new html_inputfield(array('name' => '_filtername', 'id' => 'filtername', 'size' => 70));
    $table->add('title', rep_specialchars_output($this->gettext('filtername')));
    $table->add('', $input_filtername->show($filter[0]['rulename']));

    $input_filtersource = new html_select(array('name' => '_filtersource', 'id' => 'filtersource'));
    $input_filtersource->add(array($this->gettext('filtersubject'), $this->gettext('filterfrom'), $this->gettext('filterto')), array('Subject', 'From', 'To'));
    $input_filterop = new html_select(array('name' => '_filterop', 'id' => 'filterop'));
    $input_filterop->add(array($this->gettext('filtercontains'), $this->gettext('filteris'), $this->gettext('filterbegins'), $this->gettext('filterends')), array('contains', 'is', 'begins', 'ends'));
    $input_filtersearchterm = new html_inputfield(array('name' => '_filtersearchterm', 'id' => 'filtersearchterm', 'size' => 43));
    $table->add('title', rep_specialchars_output($this->gettext('filtersource')));
    $table->add('', $input_filtersource->show($filter[0]['source']) . $input_filterop->show($filter[0]['op']) . $input_filtersearchterm->show($filter[0]['searchterm']));

    $input_filteraction = new html_select(array('name' => '_filteraction', 'id' => 'filteraction'));
    $input_filteraction->add(array($this->gettext('filtermove'), $this->gettext('filterdelete')), array('move', 'delete'));
    $input_filtertarget = rcmail_mailbox_select(array('name' => '_filtertarget', 'id' => 'filtertarget'));
    $table->add('title', rep_specialchars_output($this->gettext('filteraction')));
    $table->add('', $input_filteraction->show($filter[0]['action']) . $input_filtertarget->show($filter[0]['target']));

    $input_filterenabled = new html_checkbox(array('name' => '_filterenabled', 'id' => 'filterenabled', 'value' => '1'));
    $table->add('title', rep_specialchars_output($this->gettext('filterenabled')));
    $table->add('', $input_filterenabled->show($enabled));

    $out .= $table->show();
    $out .= "</fieldset>\n";

    return $out;
  }

  function gen_table($attrib)
  {
    $this->rcmail_inst->output->set_env('framed', TRUE);

    $out = '<fieldset><legend>' . $this->gettext('filter_entries') . '</legend>' . "\n";

    $rule_table = new html_table(array('id' => 'rule-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
    $rule_table->add_header("", $this->gettext('filter_entries'));
    $rule_table->add_header(array('width' => '20px'), '');
    $rule_table->add_header(array('width' => '16px'), '');

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $filter = $this->soap->mail_user_filter_get($session_id, array('mailuser_id' => $mail_user[0]['mailuser_id']));
      $this->soap->logout($session_id);

      for ($i = 0; $i < count($filter); $i++) {
        $class = ($class == 'odd' ? 'even' : 'odd');

        if ($filter[$i]['filter_id'] == get_input_value('_id', RCUBE_INPUT_GET))
          $class = 'selected';

        $rule_table->set_row_attribs(array('class' => $class, 'id' => 'rule_' . $filter[$i]['filter_id']));
        $this->_rule_row($rule_table, $filter[$i]['rulename'], $filter[$i]['active'], $filter[$i]['filter_id'], $attrib);
      }
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }

    if (count($filter) == 0) {
      $rule_table->add(array('colspan' => '3'), rep_specialchars_output($this->gettext('filternorules')));
      $rule_table->set_row_attribs(array('class' => 'odd'));
      $rule_table->add_row();
    }

    $out .= "<div id=\"rule-cont\">" . $rule_table->show() . "</div>\n";
    $out .= "</fieldset>\n";

    return $out;
  }

  private function _rule_row($rule_table, $name, $active, $id, $attrib)
  {
    $rule_table->add(array('class' => 'rule', 'onclick' => 'filter_edit(' . $id . ');'), $name);

    $enable_button = html::img(array('src' => $attrib['enableicon'], 'alt' => $this->gettext('enabled'), 'border' => 0));
    $disable_button = html::img(array('src' => $attrib['disableicon'], 'alt' => $this->gettext('disabled'), 'border' => 0));

    if ($active == 'y')
      $status_button = $enable_button;
    else
      $status_button = $disable_button;

    $rule_table->add(array('class' => 'control'), '&nbsp;' . $status_button);

    $del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_filter.del', 'prop' => $id, 'type' => 'image',
      'image' => $attrib['deleteicon'], 'alt' => $this->gettext('delete'),
      'title' => $this->gettext('delete')));

    $rule_table->add(array('class' => 'control'), $del_button);

    return $rule_table;
  }
}

?>