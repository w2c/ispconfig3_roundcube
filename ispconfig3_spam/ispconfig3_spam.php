<?php

class ispconfig3_spam extends rcube_plugin
{
    public $task = 'settings';
    private $rcmail;
    private $rc;
    private $soap;

    const CONTENT_FILTERS = [
        'amavisd' => [
            'policy_tag' => 'spam_tag_level',
            'policy_tag2' => 'spam_tag2_level',
            'policy_kill' => 'spam_kill_level'
        ],
        'rspamd' => [
            'policy_greylisting' => 'rspamd_spam_greylisting_level',
            'policy_tag' => 'rspamd_spam_tag_level',
            'policy_kill' => 'rspamd_spam_kill_level'
        ]
    ];

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rc = rcube::get_instance();
        $this->add_texts('localization/');
        $this->require_plugin('ispconfig3_account');

        $this->register_action('plugin.ispconfig3_spam', [$this, 'init_html']);
        $this->register_action('plugin.ispconfig3_spam.save', [$this, 'save']);

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_spam') === 0) {
            $this->api->output->add_handler('spam_form', [$this, 'gen_form']);
            $this->api->output->add_handler('sectionname_spam', [$this, 'prefs_section_name']);
            $this->api->output->add_handler('spam_table', [$this, 'gen_table']);

            $this->include_script('spam.js');
            $this->include_stylesheet($this->local_skin_path() . '/spam.css');

            $this->soap = new SoapClient(null, [
                'location' => $this->rcmail->config->get('soap_url') . 'index.php',
                'uri' => $this->rcmail->config->get('soap_url'),
                $this->rcmail->config->get('soap_validate_cert') ?:
                    'stream_context' => stream_context_create(['ssl' => [
                    'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true
                ]])
            ]);
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
        $move_junk = (!$move_junk) ? 'n': 'y';

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }

            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);
            $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

            if ($spam_user[0]['id'] == '') {
                $params = [
                    'server_id' => $mail_user[0]['server_id'],
                    'priority'  => '5',
                    'policy_id' => $policy_id,
                    'email'     => $mail_user[0]['email'],
                    'fullname'  => $mail_user[0]['email'],
                    'local'     => 'Y'
                ];

                $this->soap->mail_spamfilter_user_add($session_id, $uid, $params);
            }
            else {
                $params = $spam_user[0];
                $params['policy_id'] = $policy_id;

                $this->soap->mail_spamfilter_user_update($session_id, $uid, $spam_user[0]['id'], $params);
            }

            $params = $mail_user[0];
            unset($params['password']);

            $ispconfig_version = $this->soap->server_get_app_version($session_id);
            if (version_compare($ispconfig_version['ispc_app_version'], '3.1dev', '<')) {
                $startdate = [
                    'year'   => substr($params['autoresponder_start_date'], 0, 4),
                    'month'  => substr($params['autoresponder_start_date'], 5, 2),
                    'day'    => substr($params['autoresponder_start_date'], 8, 2),
                    'hour'   => substr($params['autoresponder_start_date'], 11, 2),
                    'minute' => substr($params['autoresponder_start_date'], 14, 2)
                ];

                $enddate = [
                    'year'   => substr($params['autoresponder_end_date'], 0, 4),
                    'month'  => substr($params['autoresponder_end_date'], 5, 2),
                    'day'    => substr($params['autoresponder_end_date'], 8, 2),
                    'hour'   => substr($params['autoresponder_end_date'], 11, 2),
                    'minute' => substr($params['autoresponder_end_date'], 14, 2)
                ];

                $params['autoresponder_end_date'] = $enddate;
                $params['autoresponder_start_date'] = $startdate;
            }

            $params['move_junk'] = $move_junk;
            $params['purge_junk_days'] = rcube_utils::get_input_value('_purge_junk_days', rcube_utils::INPUT_POST);
            $params['purge_trash_days'] = rcube_utils::get_input_value('_purge_trash_days', rcube_utils::INPUT_POST);

            $this->soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
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
        $policy_name = [];
        $policy_id = [];
        $enabled = 0;

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form([
            'id'      => $form_id,
            'name'    => $form_id,
            'method'  => 'post',
            'task'    => 'settings',
            'action'  => 'plugin.ispconfig3_spam.save',
            'noclose' => true
        ] + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('acc_spam') . '</legend>' . "\n";

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }

            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);
            $policies = $this->soap->mail_policy_get($session_id, []);
            $policy_sel = $this->soap->mail_policy_get($session_id, ['id' => $spam_user[0]['policy_id']]);
            $this->soap->logout($session_id);

            foreach ((array) $policies as $policy) {
                $policy_name[] = $policy['policy_name'];
                $policy_id[] = $policy['id'];
            }

            $enabled = $mail_user[0]['move_junk'];
            if ($enabled == 'y') {
                $enabled = 1;

                $purge_junk_days = $mail_user[0]['purge_junk_days'];
                $purge_trash_days = $mail_user[0]['purge_trash_days'];
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $table = new html_table(['cols' => 2, 'class' => 'propform']);

        $field_id = 'spampolicy_name';
        $input_spampolicy_name = new html_select(['name' => '_' . $field_id, 'id' => $field_id]);
        $input_spampolicy_name->add($policy_name, $policy_id);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('policy_name'))));
        $table->add('', $input_spampolicy_name->show($policy_sel[0]['policy_name'] ?? ''));

        $field_id = 'spammove';
        $input_spammove = new html_checkbox(['name' => '_' . $field_id, 'id' => $field_id, 'value' => '1']);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('spammove'))));
        $table->add('', $input_spammove->show($enabled));

        $field_id = 'purge_junk_days';
        $input_purge_junk_days = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => '10'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('purge_junk_days'))));
        $table->add('', $input_purge_junk_days->show($purge_junk_days));

        $field_id = 'purge_trash_days';
        $input_purge_trash_days = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => '10'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('purge_trash_days'))));
        $table->add('', $input_purge_trash_days->show($purge_trash_days));

        $out .= $table->show();
        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $out = '<fieldset><legend>' . $this->gettext('policy_entries') . '</legend>' . "\n";

        $spam_table = new html_table([ 'id' => 'spam-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 4 ]);
        $spam_table->add_header(['width' => '220px'], $this->gettext('policy_name'));

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }

            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);
            $policies = $this->soap->mail_policy_get($session_id, []);
            $mail_server_config = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'mail');
            $this->soap->logout($session_id);

            $filter = 'amavisd';
            if (isset($mail_server_config['content_filter']) && $mail_server_config['content_filter'] == 'rspamd') {
                $filter = 'rspamd';
            }

            $policy_titles = array_keys(self::CONTENT_FILTERS[$filter]);
            $policy_fields = array_values(self::CONTENT_FILTERS[$filter]);

            $spam_table->add_header([ 'class' => 'value', 'width' => '150px' ], $this->gettext($policy_titles[0]));
            $spam_table->add_header([ 'class' => 'value', 'width' => '150px' ], $this->gettext($policy_titles[1]));
            $spam_table->add_header([ 'class' => 'value', 'width' => '130px' ], $this->gettext($policy_titles[2]));

            foreach ((array) $policies as $policy) {
                if ($policy['id'] == $spam_user[0]['policy_id']) {
                    $spam_table->set_row_attribs([ 'class' => 'selected' ]);
                }

                $this->_spam_row($spam_table, $policy['policy_name'], $policy[$policy_fields[0]],
                        $policy[$policy_fields[1]], $policy[$policy_fields[2]]);
            }

            if (empty($policies)) {
                $spam_table->add([ 'colspan' => '4' ], rcube::Q($this->gettext('spamnopolicies')));
                $spam_table->add_row();
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $out .= "<div id=\"spam-cont\">" . $spam_table->show() . "</div>\n";
        $out .= "</fieldset>\n";
        $out .= '</form>';

        return $out;
    }

    private function _spam_row($spam_table, $name, $tag, $tag2, $kill)
    {
        $spam_table->add(['class' => 'policy'], $name);
        $spam_table->add(['class' => 'value'], '&nbsp;' . $tag);
        $spam_table->add(['class' => 'value'], '&nbsp;' . $tag2);
        $spam_table->add(['class' => 'value'], $kill);
    }
}
