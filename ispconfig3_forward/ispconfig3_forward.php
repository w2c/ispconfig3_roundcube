<?php
class ispconfig3_forward extends rcube_plugin
{
    public $task = 'settings';
    private $rcmail;
    private $rc;
    private $soap;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rc = rcube::get_instance();
        $this->add_texts('localization/');
        $this->require_plugin('ispconfig3_account');

        $this->register_action('plugin.ispconfig3_forward', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_forward.save', array($this, 'save'));

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_forward') === 0) {
            $this->api->output->add_handler('forward_form', array($this, 'gen_form'));
            $this->api->output->add_handler('forward_table', array($this, 'gen_table'));
            $this->api->output->add_handler('sectionname_forward', array($this, 'prefs_section_name'));

            $this->include_script('forward.js');

            $this->soap = new SoapClient(null, array(
                'location' => $this->rcmail->config->get('soap_url') . 'index.php',
                'uri' => $this->rcmail->config->get('soap_url'),
                $this->rcmail->config->get('soap_validate_cert') ?:
                    'stream_context' => stream_context_create(
                        array('ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    ))
            ));
        }
    }

    function init_html()
    {
        $this->rcmail->output->set_pagetitle($this->gettext('acc_forward'));
        $this->rcmail->output->send('ispconfig3_forward.forward');
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_forward');
    }

    function save()
    {
        $type = rcube_utils::get_input_value('_type', rcube_utils::INPUT_GET);

        if ($type != 'del')
            $address = strtolower(rcube_utils::get_input_value('_forwardingaddress', rcube_utils::INPUT_POST));
        else
            $address = strtolower(rcube_utils::get_input_value('_forwardingaddress', rcube_utils::INPUT_GET));

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }


            if ($address == $mail_user[0]['email']) {
                $this->rcmail->output->command('display_message', $this->gettext('forwardingloop'), 'error');
            }
            else {
                $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

                $params = $mail_user[0];
                unset($params['password']);

                $ispconfig_version = $this->soap->server_get_app_version($session_id);
                if (version_compare($ispconfig_version['ispc_app_version'], '3.1dev', '<')) {
                    $startdate = array('year'   => substr($params['autoresponder_start_date'], 0, 4),
                        'month'  => substr($params['autoresponder_start_date'], 5, 2),
                        'day'    => substr($params['autoresponder_start_date'], 8, 2),
                        'hour'   => substr($params['autoresponder_start_date'], 11, 2),
                        'minute' => substr($params['autoresponder_start_date'], 14, 2));

                    $enddate = array('year'   => substr($params['autoresponder_end_date'], 0, 4),
                        'month'  => substr($params['autoresponder_end_date'], 5, 2),
                        'day'    => substr($params['autoresponder_end_date'], 8, 2),
                        'hour'   => substr($params['autoresponder_end_date'], 11, 2),
                        'minute' => substr($params['autoresponder_end_date'], 14, 2));

                    $params['autoresponder_end_date'] = $enddate;
                    $params['autoresponder_start_date'] = $startdate;
                }

                if (empty($params['cc'])) {
                    $forward = $address;
                }
                else {
                    $forward = explode(",", $params['cc']);
                    while (($i = array_search($address, $forward)) !== false)
                        unset($forward[$i]);

                    if ($type != 'del')
                        $forward[] = $address;

                    $forward = implode(',', $forward);
                }

                $params['cc'] = $forward;

                $update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);

                if ($type != 'del')
                    $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
                else
                    $this->rcmail->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
            }

            $this->soap->logout($session_id);
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $this->init_html();
    }

    function gen_form($attrib)
    {
        $this->rcmail->output->add_label('ispconfig3_forward.invalidaddress', 'ispconfig3_forward.forwardingempty');

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form(array(
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.ispconfig3_forward.save',
                'noclose' => true
            ) + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('acc_forward') . '</legend>' . "\n";

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $this->soap->logout($session_id);

            $field_id = 'forwardingaddress';
            $input_forwardingaddress = new html_inputfield(array(
                'name' => '_' . $field_id,
                'id' => $field_id,
                'value' => $mail_user[0]['cc'],
                'maxlength' => 320,
                'size' => 40
            ));

            $table = new html_table(array('cols' => 2, 'class' => 'propform'));
            $table->add('title', html::label($field_id, rcube::Q($this->gettext('forwardingaddress'))));
            $table->add(null, $input_forwardingaddress->show());
            $out .= $table->show();
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $this->rcmail->output->add_label('ispconfig3_forward.forwarddelconfirm');

        $out = '<fieldset><legend>' . $this->gettext('forward_entries') . '</legend>' . "\n";

        $rule_table = new html_table(array('id'    => 'rule-table',
                                           'class' => 'records-table', 'cellspacing' => '0', 'cols' => 2));
        $rule_table->add_header(null, $this->gettext('forward_entries'));
        $rule_table->add_header(array('width' => '16px'), '');

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $this->soap->logout($session_id);

            $forward = explode(',', $mail_user[0]['cc']);
            if (!empty($forward[0])) {
                for ($i = 0; $i < count($forward); $i++) {
                    $row_attribs = array('id' => 'rule_' . $forward[$i]);
                    if ($forward[$i] == rcube_utils::get_input_value('_forwardingaddress', rcube_utils::INPUT_GET)) {
                        $row_attribs['class'] = 'selected';
                    }

                    $rule_table->set_row_attribs($row_attribs);
                    $this->_rule_row($rule_table, $forward[$i], $attrib);
                }
            }
            else {
                $rule_table->add(array('colspan' => '2'), rcube::Q($this->gettext('forwardnomails')));
                $rule_table->add_row();
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $out .= "<div id=\"rule-cont\">" . $rule_table->show() . "</div>\n";
        $out .= "</fieldset>\n";
        $out .= '</form>';
        
        return $out;
    }

    private function _rule_row($rule_table, $mail, $attrib)
    {
        $rule_table->add(array('class' => 'rule'), $mail);

        $del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_forward.del', 'prop' => $mail,
                                                       'type' => 'link', 'class' => 'button icon delete',
                                                       'innerclass' => 'inner', 'content' => '', 'title' => 'delete'));
        $rule_table->add(null, $del_button);

        return $rule_table;
    }
}
