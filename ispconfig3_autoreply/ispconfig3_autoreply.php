<?php
class ispconfig3_autoreply extends rcube_plugin
{
  public $task = 'settings';
  private $soap = NULL;
  private $rcmail_inst = NULL;
  private $required_plugins = array('jqueryui', 'ispconfig3_account');

  function init()
  {
    $this->rcmail_inst = rcmail::get_instance();
    $this->add_texts('localization/', TRUE);
    $this->soap = new SoapClient(NULL, array('location' => $this->rcmail_inst->config->get('soap_url') . 'index.php',
      'uri' => $this->rcmail_inst->config->get('soap_url')));

    $this->register_action('plugin.ispconfig3_autoreply', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_autoreply.save', array($this, 'save'));

    $this->api->output->add_handler('autoreply_form', array($this, 'gen_form'));
    $this->api->output->add_handler('sectionname_autoreply', array($this, 'prefs_section_name'));

    $skin = $this->rcmail_inst->config->get('skin');

    if (file_exists('skins/' . $skin . '/css/jquery/jquery.ui.datetime.css'))
      $this->include_stylesheet('skins/' . $skin . '/css/jquery/jquery.ui.datetime.css');
    else
      $this->include_stylesheet('skins/classic/css/jquery/jquery.ui.datetime.css');

    if (file_exists('skins/' . $skin . '/js/jquery.ui.datetime.min.js'))
      $this->include_script('skins/' . $skin . '/js/jquery.ui.datetime.min.js');
    else
      $this->include_script('skins/classic/js/jquery.ui.datetime.min.js');

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
    $enabled = get_input_value('_autoreplyenabled', RCUBE_INPUT_POST);
    $body = get_input_value('_autoreplybody', RCUBE_INPUT_POST);
    $subject = get_input_value('_autoreplysubject', RCUBE_INPUT_POST);
    $startdate = get_input_value('_autoreplystarton', RCUBE_INPUT_POST);
    $enddate = get_input_value('_autoreplyendby', RCUBE_INPUT_POST);

    if (strtotime($enddate) < strtotime($startdate))
      $enddate = $startdate;
      
    $startdate = gmdate("Y-m-d H:i", strtotime($startdate));
    $enddate = gmdate("Y-m-d H:i", strtotime($enddate));

    $startdate = array('year' => substr($startdate, 0, 4),
      'month' => substr($startdate, 5, 2),
      'day' => substr($startdate, 8, 2),
      'hour' => substr($startdate, 11, 2),
      'minute' => substr($startdate, 14, 2));

    $enddate = array('year' => substr($enddate, 0, 4),
      'month' => substr($enddate, 5, 2),
      'day' => substr($enddate, 8, 2),
      'hour' => substr($enddate, 11, 2),
      'minute' => substr($enddate, 14, 2));

    if (!$enabled)
      $enabled = 'n';
    else
      $enabled = 'y';

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

      $params = $mail_user[0];
      unset($params['password']);
      $params['autoresponder'] = $enabled;
      $params['autoresponder_text'] = $body;
      $params['autoresponder_subject'] = $subject;
      $params['autoresponder_start_date'] = $startdate;
      $params['autoresponder_end_date'] = $enddate;

      $update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
      $this->soap->logout($session_id);

      $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }

    $this->init_html();
  }

  function gen_form()
  {
    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $this->soap->logout($session_id);
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }

    $enabled = $mail_user[0]['autoresponder'];

    if ($enabled == 'y')
      $enabled = 1;
    else
      $enabled = 0;

    if ($mail_user[0]['autoresponder_start_date'] == '0000-00-00 00:00:00')
      $mail_user[0]['autoresponder_start_date'] = date('Y') . '-' . date('m') . '-' . date('d') . ' ' . date('H') . ':' . date('i');

    if ($mail_user[0]['autoresponder_end_date'] == '0000-00-00 00:00:00')
      $mail_user[0]['autoresponder_end_date'] = date('Y') . '-' . date('m') . '-' . date('d') . ' ' . date('H') . ':' . date('i');

    $this->rcmail_inst->output->set_env('framed', TRUE);

    $hidden_priority = new html_hiddenfield(array('name' => '_priority', 'value' => $priority, 'id' => 'priority'));
    $out .= $hidden_priority->show();

    $out .= '<fieldset><legend>' . $this->gettext('acc_autoreply') . '</legend>' . "\n";

    $table = new html_table(array('cols' => 2, 'class' => 'propform'));

    $input_autoreplysubject = new html_inputfield(array('name' => '_autoreplysubject', 'id' => 'autoreplysubject', 'size' => 40));
    $table->add('title', rep_specialchars_output($this->gettext('subject')));
    $table->add('', $input_autoreplysubject->show($mail_user[0]['autoresponder_subject']));

    $input_autoreplybody = new html_textarea(array('name' => '_autoreplybody', 'id' => 'autoreplybody', 'cols' => 48, 'rows' => 15));
    $table->add('title', rep_specialchars_output($this->gettext('autoreplymessage')));
    $table->add('', $input_autoreplybody->show($mail_user[0]['autoresponder_text']));

    $input_autoreplystarton = new html_inputfield(array('name' => '_autoreplystarton', 'id' => 'autoreplystarton', 'size' => 20));
    $table->add('title', rep_specialchars_output($this->gettext('autoreplystarton')));
    $table->add('', $input_autoreplystarton->show($mail_user[0]['autoresponder_start_date']));

    $input_autoreplyendby = new html_inputfield(array('name' => '_autoreplyendby', 'id' => 'autoreplyendby', 'size' => 20));
    $table->add('title', rep_specialchars_output($this->gettext('autoreplyendby')));
    $table->add('', $input_autoreplyendby->show($mail_user[0]['autoresponder_end_date']));

    $input_autoreplyenabled = new html_checkbox(array('name' => '_autoreplyenabled', 'id' => 'autoreplyenabled', 'value' => 1));
    $table->add('title', rep_specialchars_output($this->gettext('autoreplyenabled')));
    $table->add('', $input_autoreplyenabled->show($enabled));

    $out .= $table->show();
    $out .= "</fieldset>\n";

    return $out;
  }
}

?>
