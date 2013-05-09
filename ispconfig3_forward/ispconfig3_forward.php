<?php
class ispconfig3_forward extends rcube_plugin
{
  public $task = 'settings';
  public $EMAIL_ADDRESS_PATTERN = '([a-z0-9][a-z0-9\-\.\+\_]*@[a-z0-9]([a-z0-9\-][.]?)*[a-z0-9]\\.[a-z]{2,5})';
  private $soap = NULL;
  private $rcmail_inst = NULL;
  private $required_plugins = array('ispconfig3_account');

  function init()
  {
    $this->rcmail_inst = rcmail::get_instance();
    $this->add_texts('localization/', TRUE);
    $this->soap = new SoapClient(NULL, array('location' => $this->rcmail_inst->config->get('soap_url') . 'index.php',
      'uri' => $this->rcmail_inst->config->get('soap_url')));

    $this->register_action('plugin.ispconfig3_forward', array($this, 'init_html'));
    $this->register_action('plugin.ispconfig3_forward.save', array($this, 'save'));

    $this->api->output->add_handler('forward_form', array($this, 'gen_form'));
    $this->api->output->add_handler('sectionname_forward', array($this, 'prefs_section_name'));

    $this->include_script('forward.js');
  }

  function init_html()
  {
    $this->rcmail_inst->output->set_pagetitle($this->gettext('acc_forward'));
    $this->rcmail_inst->output->send('ispconfig3_forward.forward');
  }

  function prefs_section_name()
  {
    return $this->gettext('acc_forward');
  }

  function save()
  {
    $address = strtolower(get_input_value('_forwardingaddress', RCUBE_INPUT_POST));

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));

      if ($address == $mail_user[0]['email'])
        $this->rcmail_inst->output->command('display_message', $this->gettext('forwardingloop'), 'error');
      else {
        $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

        $params = $mail_user[0];
        unset($params['password']);
        unset($params['autoresponder_start_date']);
        unset($params['autoresponder_end_date']);
        $params['cc'] = $address;

        $update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
        $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
      }

      $this->soap->logout($session_id);
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }

    $this->init_html();
  }

  function gen_form()
  {
    $user = $this->rcmail_inst->user->get_prefs();

    $this->rcmail_inst->output->add_label('ispconfig3_forward.invalidaddress',
      'ispconfig3_forward.forwardingempty');

    try {
      $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
      $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
      $this->soap->logout($session_id);
    } catch (SoapFault $e) {
      $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
    }

    $this->rcmail_inst->output->set_env('framed', TRUE);

    $out .= '<fieldset><legend>' . $this->gettext('acc_forward') . '</legend>' . "\n";

    $table = new html_table(array('cols' => 2, 'class' => 'propform'));

    $input_forwardingaddress = new html_inputfield(array('name' => '_forwardingaddress', 'id' => 'forwardingaddress', 'value' => $forward[1], 'maxlength' => 320, 'size' => 40));
    $table->add('title', rep_specialchars_output($this->gettext('forwardingaddress')));
    $table->add('', $input_forwardingaddress->show($mail_user[0]['cc']));

    $out .= $table->show();
    $out .= "</fieldset>\n";

    return $out;
  }
}

?>