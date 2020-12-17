<?php
class ispconfig3_spam extends rcube_plugin
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

        $this->register_action('plugin.ispconfig3_spam', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_spam.save', array($this, 'save'));

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_spam') === 0) {
            $this->api->output->add_handler('spam_form', array($this, 'gen_form'));
            $this->api->output->add_handler('sectionname_spam', array($this, 'prefs_section_name'));
            $this->api->output->add_handler('spam_table', array($this, 'gen_table'));

            $this->include_script('spam.js');
            $this->include_stylesheet($this->local_skin_path() . '/spam.css');

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
        $this->rcmail->output->set_pagetitle($this->gettext('acc_spam'));
        $this->rcmail->output->send('ispconfig3_spam.spam');
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_spam');
    }

    function save()
    {
        $policy_id = rcube_utils::get_input_value('_spampolicy_name', rcube_utils::INPUT_POST);
        $move_junk = rcube_utils::get_input_value('_spammove', rcube_utils::INPUT_POST);

        if (!$move_junk)
            $move_junk = 'n';
        else
            $move_junk = 'y';

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));
            $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

            if ($spam_user[0]['id'] == '') {
                $params = array('server_id' => $mail_user[0]['server_id'],
                                'priority'  => '5',
                                'policy_id' => $policy_id,
                                'email'     => $mail_user[0]['email'],
                                'fullname'  => $mail_user[0]['email'],
                                'local'     => 'Y');

                $add = $this->soap->mail_spamfilter_user_add($session_id, $uid, $params);
            }
            else {
                $params = $spam_user[0];
                $params['policy_id'] = $policy_id;

                $update = $this->soap->mail_spamfilter_user_update($session_id, $uid, $spam_user[0]['id'], $params);
            }

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

            $params['move_junk'] = $move_junk;

            $update = $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
            $this->soap->logout($session_id);

            $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $this->init_html();
    }

    function gen_form($attrib)
    {
        $policy_name = array();
        $policy_id = array();
        $enabled = 0;

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form(array(
            'id'      => $form_id,
            'name'    => $form_id,
            'method'  => 'post',
            'task'    => 'settings',
            'action'  => 'plugin.ispconfig3_spam.save',
            'noclose' => true
            ) + $attrib);
            
        $out .= '<fieldset><legend>' . $this->gettext('acc_spam') . '</legend>' . "\n";        

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));
            $policy = $this->soap->mail_policy_get($session_id, array());
            $policy_sel = $this->soap->mail_policy_get($session_id, array('id' => $spam_user[0]['policy_id']));
            $this->soap->logout($session_id);

            for ($i = 0; $i < count($policy); $i++) {
                $policy_name[] = $policy[$i]['policy_name'];
                $policy_id[] = $policy[$i]['id'];
            }

            $enabled = $mail_user[0]['move_junk'];
            if ($enabled == 'y')
                $enabled = 1;
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $table = new html_table(array('cols' => 2, 'class' => 'propform'));

        $field_id = 'spampolicy_name';
        $input_spampolicy_name = new html_select(array('name' => '_' . $field_id, 'id' => $field_id));
        $input_spampolicy_name->add($policy_name, $policy_id);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('policy_name'))));
        $table->add('', $input_spampolicy_name->show($policy_sel[0]['policy_name']));

        $field_id = 'spammove';
        $input_spammove = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => '1'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('spammove'))));
        $table->add('', $input_spammove->show($enabled));
        $out .= $table->show();

        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $out = '<fieldset><legend>' . $this->gettext('policy_entries') . '</legend>' . "\n";

        $spam_table = new html_table(array('id' => 'spam-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 4));
        $spam_table->add_header(array('width' => '220px'), $this->gettext('policy_name'));
        $spam_table->add_header(array('class' => 'value', 'width' => '150px'), $this->gettext('policy_tag'));
        $spam_table->add_header(array('class' => 'value', 'width' => '150px'), $this->gettext('policy_tag2'));
        $spam_table->add_header(array('class' => 'value', 'width' => '130px'), $this->gettext('policy_kill'));

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, array('email' => $mail_user[0]['email']));
            $policies = $this->soap->mail_policy_get($session_id, array());
            $this->soap->logout($session_id);

            for ($i = 0; $i < count($policies); $i++) {
                if ($policies[$i]['id'] == $spam_user[0]['policy_id']) {
                    $spam_table->set_row_attribs(array('class' => 'selected'));
                }

                $this->_spam_row($spam_table, $policies[$i]['policy_name'], $policies[$i]['spam_tag_level'],
                    $policies[$i]['spam_tag2_level'], $policies[$i]['spam_kill_level'], $attrib);
            }

            if (count($policies) == 0) {
                $spam_table->add(array('colspan' => '4'), rcube::Q($this->gettext('spamnopolicies')));
                $spam_table->add_row();
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $out .= "<div id=\"spam-cont\">" . $spam_table->show() . "</div>\n";
        $out .= '<br /><span>' . $this->gettext('policy_note') . "</span>\n";
        $out .= "</fieldset>\n";
        $out .= '</form>';

        return $out;
    }

    private function _spam_row($spam_table, $name, $tag, $tag2, $kill, $attrib)
    {
        $spam_table->add(array('class' => 'policy'), $name);
        $spam_table->add(array('class' => 'value'), '&nbsp;' . $tag);
        $spam_table->add(array('class' => 'value'), '&nbsp;' . $tag2);
        $spam_table->add(array('class' => 'value'), $kill);

        return $spam_table;
    }
}
