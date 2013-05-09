<?php
class ispconfig3_account extends rcube_plugin
{
  public $task = 'settings';
  private $sections = array();
  private $rcmail_inst = NULL;
  private $soap = NULL;

  function init()
  {
    $this->rcmail_inst = rcmail::get_instance();
    $this->load_config();
    $this->add_texts('localization/', TRUE);
    $this->soap = new SoapClient(NULL, array('location' => $this->rcmail_inst->config->get('soap_url') . 'index.php',
      'uri' => $this->rcmail_inst->config->get('soap_url')));
    $this->register_action('plugin.ispconfig3_account', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_account.show', array($this, 'init_html'));
    $this->add_hook('template_object_identityform', array($this, 'template_object_identityform'));
    $this->include_script('account.js');
  }

  function init_html()
  {
    $this->api->output->set_pagetitle($this->gettext('acc_acc'));
    if (rcmail::get_instance()->action == 'plugin.ispconfig3_account.show') {
      $this->api->output->add_handler('info', array($this, 'gen_form'));
      $this->api->output->add_handler('sectionname_acc', array($this, 'prefs_section_name'));
      $this->api->output->send('ispconfig3_account.general');
    }
    else {
      $this->api->output->add_handler('accountlist', array($this, 'section_list'));
      $this->api->output->add_handler('accountframe', array($this, 'preference_frame'));
      $this->api->output->send('ispconfig3_account.account');
    }
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

  function preference_frame($attrib)
  {
    if (!$attrib['id'])
      $attrib['id'] = 'rcmaccountframe';
    $attrib['name'] = $attrib['id'];
    $this->api->output->set_env('contentframe', $attrib['name']);
    $this->api->output->set_env('blankpage', $attrib['src'] ? $this->api->output->abs_url($attrib['src']) : 'program/blank.gif');

    return html::iframe($attrib);
  }

  function template_object_identityform($args)
  {
    if ($this->rcmail_inst->config->get('identity_limit') === TRUE) {
      $emails = new html_select(array('name' => '_email', 'id' => 'rcmfd_email', 'class' => 'ff_email'));
      try {
        $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
        $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
        $alias = $this->soap->mail_alias_get($session_id, array('destination' => $mail_user[0]['email'], 'type' => 'alias', 'active' => 'y'));
        $this->soap->logout($session_id);
        $emails->add($mail_user[0]['email'], $mail_user[0]['email']);
        for ($i = 0; $i < count($alias); $i++)
          $emails->add($alias[$i]['source'], $alias[$i]['source']);
      } catch (SoapFault $e) {
        $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
      }
      if (substr(RCMAIL_VERSION, 2, 1) <= 7) {
        preg_match('/<input type=\"text\" size=\"40\" id=\"rcmfd_email\" name=\"_email\" class=\"ff_email\" value=\"(.*)\" \/>/', $args['content'], $test);
        $args['content'] = preg_replace('/<input type=\"text\" size=\"40\" id=\"rcmfd_email\" name=\"_email\" class=\"ff_email\" value=\"(.*)\" \/>/', $emails->show($test[1]), $args['content']);
      }
      else {
        preg_match('/<input type=\"text\" size=\"40\" id=\"rcmfd_email\" name=\"_email\" class=\"ff_email\" value=\"(.*)\">/', $args['content'], $test);
        $args['content'] = preg_replace('/<input type=\"text\" size=\"40\" id=\"rcmfd_email\" name=\"_email\" class=\"ff_email\" value=\"(.*)\">/', $emails->show($test[1]), $args['content']);
      }
    }

    return $args;
  }

  function section_list($attrib)
  {
    if (!strlen($attrib['id']))
      $attrib['id'] = 'rcmaccountlist';
    $sectionavail = array('general' => array('id' => 'general', 'section' => $this->gettext('acc_general')),
      'pass' => array('id' => 'pass', 'section' => $this->gettext('acc_pass')),
      'fetchmail' => array('id' => 'fetchmail', 'section' => $this->gettext('acc_fetchmail')),
      'forward' => array('id' => 'forward', 'section' => $this->gettext('acc_forward')),
      'autoreply' => array('id' => 'autoreply', 'section' => $this->gettext('acc_autoreply')),
      'filter' => array('id' => 'filter', 'section' => $this->gettext('acc_filter')),
      'wblist' => array('id' => 'wblist', 'section' => $this->gettext('acc_wblist')),
      'spam' => array('id' => 'spam', 'section' => $this->gettext('junk')));
    $sections = array();
    $array = array('general');
    $plugins = $this->rcmail_inst->config->get('plugins');
    $plugins = array_flip($plugins);
    if (isset($plugins['ispconfig3_pass']))
      array_push($array, 'pass');
    if (isset($plugins['ispconfig3_fetchmail']))
      array_push($array, 'fetchmail');
    if (isset($plugins['ispconfig3_forward']))
      array_push($array, 'forward');
    if (isset($plugins['ispconfig3_autoreply']))
      array_push($array, 'autoreply');
    if (isset($plugins['ispconfig3_filter']))
      array_push($array, 'filter');
    if (isset($plugins['ispconfig3_wblist']))
      array_push($array, 'wblist');
    if (isset($plugins['ispconfig3_spam']))
      array_push($array, 'spam');
    $blocks = $attrib['sections'] ? preg_split('/[\s,;]+/', strip_quotes($attrib['sections'])) : $array;
    foreach ($blocks as $block)
      $sections[$block] = $sectionavail[$block];
    $out = rcube_table_output($attrib, $sections, array('section'), 'id');
    $this->rcmail_inst->output->add_gui_object('accountlist', $attrib['id']);
    $this->rcmail_inst->output->include_script('list.js');

    return $out;
  }

  function prefs_section_name()
  {
    return $this->gettext('acc_general');
  }

  function gen_form()
  {
    $this->rcmail_inst->output->set_env('framed', TRUE);
    $out = '<form class="propform"><fieldset><legend>' . $this->gettext('acc_general') . '</legend>' . "\n";
    $table = new html_table(array('cols' => 2, 'cellpadding' => 3, 'class' => 'propform'));
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
    $out .= "</fieldset>\n";
    $out .= '<fieldset><legend>' . $this->gettext('acc_alias') . '</legend>' . "\n";
    $alias_table = new html_table(array('id' => 'alias-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 1));
    $alias_table->add_header(array('width' => '100%'), $this->gettext('mail'));
    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $alias = $this->soap->mail_alias_get($session_id, array('destination' => $mail_user[0]['email'], 'type' => 'alias', 'active' => 'y'));
      $this->soap->logout($session_id);
      $class = ($class == 'odd' ? 'even' : 'odd');
      $alias_table->set_row_attribs(array('class' => $class));
      $alias_table->add('', $mail_user[0]['email']);
      for ($i = 0; $i < count($alias); $i++) {
        $class = ($class == 'odd' ? 'even' : 'odd');
        $alias_table->set_row_attribs(array('class' => $class));
        $alias_table->add('', $alias[$i]['source']);
      }
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }
    $out .= "<div id=\"alias-cont\">" . $alias_table->show() . "</div>\n";
    $out .= "</fieldset></form>\n";

    return $out;
  }
}

?>