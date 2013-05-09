<?php
class ispconfig3_wblist extends rcube_plugin
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
    return $this->gettext('acc_wblist');
  }

  function del()
  {
    $id = get_input_value('_id', RCUBE_INPUT_GET);

    if ($id != 0 || $id != '') {
      try {
        $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
        $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
        $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));

        if (get_input_value('_type', RCUBE_INPUT_GET) == "W")
          $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, $id);
        else
          $wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, $id);

        if ($wblist['rid'] == $spam_user[0]['id']) {
          if (get_input_value('_type', RCUBE_INPUT_GET) == "W")
            $delete = $this->soap->mail_spamfilter_whitelist_delete($session_id, $id);
          else
            $delete = $this->soap->mail_spamfilter_blacklist_delete($session_id, $id);

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
    $type = get_input_value('_wblistwb', RCUBE_INPUT_POST);
    $email = get_input_value('_wblistemail', RCUBE_INPUT_POST);
    $priority = get_input_value('_wblistpriority', RCUBE_INPUT_POST);
    $enabled = get_input_value('_wblistenabled', RCUBE_INPUT_POST);

    if (!$enabled)
      $enabled = 'n';
    else
      $enabled = 'y';

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));
      $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

      if ($id == 0 || $id == '') {
        $limit = $this->rcmail_inst->config->get('wblist_limit');

        if ($spam_user[0]['id'] == '') {
          $params = array('server_id' => $mail_user[0]['server_id'],
            'priority' => '5',
            'policy_id' => $this->rcmail_inst->config->get('wblist_default_policy'),
            'email' => $mail_user[0]['email'],
            'fullname' => $mail_user[0]['email'],
            'local' => 'Y');

          $add = $this->soap->mail_spamfilter_user_add($session_id, $uid, $params);
          $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));
        }

        $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, array('rid' => $spam_user[0]['id']));
        //$blist = $this->soap->mail_spamfilter_blacklist_get($session_id, array('rid' => $spam_user[0]['id']));
        //$wblist = array_merge($wlist, $blist);

        if (count($wblist) < $limit) {
          $params = array('sys_userid' => $spam_user[0]['sys_userid'],
            'sys_groupid' => $spam_user[0]['sys_groupid'],
            'server_id' => $spam_user[0]['server_id'],
            'rid' => $spam_user[0]['id'],
            'wb' => $type,
            'email' => $email,
            'priority' => $priority,
            'active' => $enabled);

          if ($type == "W")
            $add = $this->soap->mail_spamfilter_whitelist_add($session_id, $uid, $params);
          else
            $add = $this->soap->mail_spamfilter_blacklist_add($session_id, $uid, $params);

          $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
        }
        else
          $this->rcmail_inst->output->command('display_message', 'Error: ' . $this->gettext('wblimitreached'), 'error');
      }
      else {
        $wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, $id);
        if ($wblist['rid'] == $spam_user[0]['id']) {
          $params = array('server_id' => $spam_user[0]['server_id'],
            'rid' => $spam_user[0]['id'],
            'wb' => $type,
            'email' => $email,
            'priority' => $priority,
            'active' => $enabled);

          if ($type == "W")
            $update = $this->soap->mail_spamfilter_whitelist_update($session_id, $uid, $id, $params);
          else
            $update = $this->soap->mail_spamfilter_blacklist_update($session_id, $uid, $id, $params);

          $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
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
    $id = get_input_value('_id', RCUBE_INPUT_GET);

    $this->rcmail_inst->output->add_label('ispconfig3_wblist.wblistdelconfirm');

    if ($id != '' || $id != 0) {
      try {
        $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
        $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
        $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));

        if (get_input_value('_type', RCUBE_INPUT_GET) == "W") {
          $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, array('wblist_id' => $id));
          $type = "W";
        }
        else {
          $wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, array('wblist_id' => $id));
          $type = "B";
        }

        $this->soap->logout($session_id);
      } catch (SoapFault $e) {
        $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
      }

      $enabled = $wblist[0]['active'];

      if ($wblist[0]['rid'] != $spam_user[0]['id']) {
        $this->rcmail_inst->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');

        $enabled = 'n';
        $wblist[0]['email'] = '';
        $wblist[0]['priority'] = '';
      }
    }
    else {
      $wblist[0]['priority'] = '5';
    }

    if ($enabled == 'y')
      $enabled = 1;
    else
      $enabled = 0;

    $this->rcmail_inst->output->set_env('framed', TRUE);

    $out .= '<fieldset><legend>' . $this->gettext('acc_wblist') . '</legend>' . "\n";

    $hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $wblist[0]['wblist_id']));
    $out .= $hidden_id->show();

    $table = new html_table(array('cols' => 2, 'class' => 'propform'));

    $input_wblistemail = new html_inputfield(array('name' => '_wblistemail', 'id' => 'wblistaddress', 'size' => 70));
    $table->add('title', rep_specialchars_output($this->gettext('email')));
    $table->add('', $input_wblistemail->show($wblist[0]['email']));

    $input_wblistwb = new html_select(array('name' => '_wblistwb', 'id' => 'wblistwb'));
    $input_wblistwb->add(array($this->gettext('wblistwhitelist'), $this->gettext('wblistblacklist')), array('W', 'B'));
    $table->add('title', rep_specialchars_output($this->gettext('wblisttype')));
    $table->add('', $input_wblistwb->show($type));

    $input_wblistpriority = new html_select(array('name' => '_wblistpriority', 'id' => 'wblistpriority'));
    $input_wblistpriority->add(array("1", "2", "3", "4", "5", "6", "7", "8", "9", "10"));
    $table->add('title', rep_specialchars_output($this->gettext('wblistpriority')));
    $table->add('', $input_wblistpriority->show($wblist[0]['priority']));

    $input_wblistenabled = new html_checkbox(array('name' => '_wblistenabled', 'id' => 'wblistenabled', 'value' => '1'));
    $table->add('title', rep_specialchars_output($this->gettext('wblistenabled')));
    $table->add('', $input_wblistenabled->show($enabled));

    $out .= $table->show();
    $out .= "</fieldset>\n";

    return $out;
  }

  function gen_table($attrib)
  {
    $this->rcmail_inst->output->set_env('framed', TRUE);

    $out = '<fieldset><legend>' . $this->gettext('wblistentries') . '</legend>' . "\n";

    $rule_table = new html_table(array('id' => 'rule-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 4));
    $rule_table->add_header("", $this->gettext('wblistentries'));
    $rule_table->add_header(array('width' => '16px'), '');
    $rule_table->add_header(array('width' => '20px'), '');
    $rule_table->add_header(array('width' => '16px'), '');

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));
      $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, array('rid' => $spam_user[0]['id']));
      //$blist = $this->soap->mail_spamfilter_blacklist_get($session_id, array('rid' => $spam_user[0]['id']));
      //$wblist = array_merge($wlist, $blist);
      $this->soap->logout($session_id);

      for ($i = 0; $i < count($wblist); $i++) {
        $class = ($class == 'odd' ? 'even' : 'odd');

        if ($wblist[$i]['wblist_id'] == get_input_value('_id', RCUBE_INPUT_GET))
          $class = 'selected';

        $rule_table->set_row_attribs(array('class' => $class, 'id' => 'rule_' . $wblist[$i]['wblist_id']));
        $this->_rule_row($rule_table, $wblist[$i]['email'], $wblist[$i]['wb'], $wblist[$i]['active'], $wblist[$i]['wblist_id'], $attrib);
      }
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }


    if (count($wblist) == 0) {
      $rule_table->add(array('colspan' => '4'), rep_specialchars_output($this->gettext('wblistnorules')));
      $rule_table->set_row_attribs(array('class' => 'odd'));
      $rule_table->add_row();
    }


    $out .= "<div id=\"rule-cont\">" . $rule_table->show() . "</div>\n";
    $out .= "</fieldset>\n";

    return $out;
  }

  private function _rule_row($rule_table, $name, $wb, $active, $id, $attrib)
  {
    $rule_table->add(array('class' => 'rule', 'onclick' => 'wb_edit(' . $id . ',"' . $wb . '");'), $name);

    $white_button = html::img(array('src' => $attrib['whiteicon'], 'alt' => "W", 'border' => 0));
    $black_button = html::img(array('src' => $attrib['blackicon'], 'alt' => "B", 'border' => 0));

    if ($wb == "W")
      $rule_table->add(array('class' => 'control'), $white_button);
    else
      $rule_table->add(array('class' => 'control'), $black_button);


    $enable_button = html::img(array('src' => $attrib['enableicon'], 'alt' => $this->gettext('enabled'), 'border' => 0));
    $disable_button = html::img(array('src' => $attrib['disableicon'], 'alt' => $this->gettext('disabled'), 'border' => 0));

    if ($active == 'y')
      $status_button = $enable_button;
    else
      $status_button = $disable_button;

    $rule_table->add(array('class' => 'control'), '&nbsp;' . $status_button);

    $del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_wblist.del', 'prop' => $id . '\',\'' . $wb, 'type' => 'image',
      'image' => $attrib['deleteicon'], 'alt' => $this->gettext('delete'),
      'title' => $this->gettext('delete')));

    $rule_table->add(array('class' => 'control'), $del_button);

    return $rule_table;
  }
}

?>