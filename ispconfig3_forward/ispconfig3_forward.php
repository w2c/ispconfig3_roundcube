<?php
class ispconfig3_forward extends rcube_plugin
{
    public $task = 'settings';
    public $EMAIL_ADDRESS_PATTERN = '([a-z0-9][a-z0-9\-\.\+\_]*@[a-z0-9]([a-z0-9\-][.]?)*[a-z0-9]\\.[a-z]{2,5})';
    private $soap = null;
    private $rcmail_inst = null;
    private $required_plugins = array('ispconfig3_account');

    function init()
    {
        $this->rcmail_inst = rcmail::get_instance();
        $this->add_texts('localization/', true);
        $this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url') . 'index.php',
                                                 'uri'      => $this->rcmail_inst->config->get('soap_url')));

        $this->register_action('plugin.ispconfig3_forward', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_forward.save', array($this, 'save'));

        $this->api->output->add_handler('forward_form', array($this, 'gen_form'));
        $this->api->output->add_handler('forward_table', array($this, 'gen_table'));
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
        $type = rcube_utils::get_input_value('_type', RCUBE_INPUT_GET);

        if ($type != 'del')
            $address = strtolower(rcube_utils::get_input_value('_forwardingaddress', RCUBE_INPUT_POST));
        else
            $address = strtolower(rcube_utils::get_input_value('_forwardingaddress', RCUBE_INPUT_GET));

        try
        {
            $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));

            if ($address == $mail_user[0]['email'])
                $this->rcmail_inst->output->command('display_message', $this->gettext('forwardingloop'), 'error');
            else
            {
                $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

                $params = $mail_user[0];
                unset($params['password']);

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

                if (empty($params['cc']))
                    $forward = $address;
                else
                {
                    $forward = explode(",", $params['cc']);
                    while (($i = array_search($address, $forward)) !== false)
                        unset($forward[$i]);

                    if ($type != 'del')
                        $forward[] = $address;

                    $forward = implode(',', $forward);
                }

                $params['cc'] = $forward;
                $params['autoresponder_end_date'] = $enddate;
                $params['autoresponder_start_date'] = $startdate;

                $update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);

                if ($type != 'del')
                    $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
                else
                    $this->rcmail_inst->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
            }

            $this->soap->logout($session_id);
        } catch (SoapFault $e)
        {
            $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
        }

        $this->init_html();
    }

    function gen_form()
    {
        $user = $this->rcmail_inst->user->get_prefs();

        $this->rcmail_inst->output->add_label('ispconfig3_forward.invalidaddress',
            'ispconfig3_forward.forwardingempty');

        $this->rcmail_inst->output->set_env('framed', true);

        $out .= '<fieldset><legend>' . $this->gettext('acc_forward') . '</legend>' . "\n";

        $table = new html_table(array('cols' => 2, 'class' => 'propform'));

        $input_forwardingaddress = new html_inputfield(array('name' => '_forwardingaddress', 'id' => 'forwardingaddress', 'value' => '', 'maxlength' => 320, 'size' => 40));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('forwardingaddress')));
        $table->add('', $input_forwardingaddress->show());

        $out .= $table->show();
        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $this->rcmail_inst->output->set_env('framed', true);

        $out = '<fieldset><legend>' . $this->gettext('forward_entries') . '</legend>' . "\n";

        $rule_table = new html_table(array('id'    => 'rule-table',
                                           'class' => 'records-table', 'cellspacing' => '0', 'cols' => 2));
        $rule_table->add_header("", $this->gettext('forward_entries'));
        $rule_table->add_header(array('width' => '16px'), '');

        try
        {
            $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
            $this->soap->logout($session_id);

            $forward = explode(",", $mail_user[0]['cc']);

            if (!empty($forward[0]))
            {
                for ($i = 0; $i < count($forward); $i++)
                {
                    $class = ($class == 'odd' ? 'even' : 'odd');

                    if ($forward[$i] == rcube_utils::get_input_value('_forwardingaddress', RCUBE_INPUT_GET))
                        $class = 'selected';

                    $rule_table->set_row_attribs(array('class' => $class, 'id' => 'rule_' . $forward[$i]));
                    $this->_rule_row($rule_table, $forward[$i], $attrib);
                }
            }
            else
            {
                $rule_table->add(array('colspan' => '2'), rcube_utils::rep_specialchars_output($this->gettext('forwardnomails')));
                $rule_table->set_row_attribs(array('class' => 'odd'));
                $rule_table->add_row();
            }
        } catch (SoapFault $e)
        {
            $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
        }

        $out .= "<div id=\"rule-cont\">" . $rule_table->show() . "</div>\n";
        $out .= "</fieldset>\n";

        return $out;
    }

    private function _rule_row($rule_table, $mail, $attrib)
    {
        $rule_table->add(array('class' => 'rule'), $mail);

        $del_button = $this->api->output->button(array('command' => 'plugin.ispconfig3_forward.del', 'prop' => $mail, 'type' => 'image',
                                                       'image'   => $attrib['deleteicon'], 'alt' => $this->gettext('delete'),
                                                       'title'   => $this->gettext('delete')));

        $rule_table->add(array('class' => 'control'), $del_button);

        return $rule_table;
    }
}
