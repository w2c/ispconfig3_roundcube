<?php

class ispconfig3_wblist extends rcube_plugin
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

        $this->register_action('plugin.ispconfig3_wblist', [$this, 'init_html']);
        $this->register_action('plugin.ispconfig3_wblist.save', [$this, 'save']);
        $this->register_action('plugin.ispconfig3_wblist.del', [$this, 'del']);

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_wblist') === 0) {
            $this->load_config('config/config.inc.php.dist');
            if (file_exists($this->home . '/config/config.inc.php')) {
                $this->load_config('config/config.inc.php');
            }

            $this->api->output->add_handler('wblist_form', [$this, 'gen_form']);
            $this->api->output->add_handler('wblist_table', [$this, 'gen_table']);
            $this->api->output->add_handler('sectionname_wblist', [$this, 'prefs_section_name']);

            $this->include_script('wblist.js');
            $this->include_stylesheet($this->local_skin_path() . '/wblist.css');

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
        $this->rcmail->output->set_pagetitle($this->gettext('acc_wblist'));
        $this->rcmail->output->send('ispconfig3_wblist.wblist');
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_wblist');
    }

    function del()
    {
        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);

        if (!empty($id)) {
            try {
                $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
                $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
                // Alternatively also search the email field, this can differ from the login field for legacy reasons.
                if (empty($mail_user)) {
                    $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
                }

                $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);

                if (rcube_utils::get_input_value('_type', rcube_utils::INPUT_GET) == "W") {
                    $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, $id);
                }
                else {
                    $wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, $id);
                }

                if ($wblist['rid'] == $spam_user[0]['id']) {
                    if (rcube_utils::get_input_value('_type', rcube_utils::INPUT_GET) == "W") {
                        $this->soap->mail_spamfilter_whitelist_delete($session_id, $id);
                    }
                    else {
                        $this->soap->mail_spamfilter_blacklist_delete($session_id, $id);
                    }

                    $this->rcmail->output->command('display_message', $this->gettext('deletedsuccessfully'), 'confirmation');
                }

                $this->soap->logout($session_id);
            }
            catch (SoapFault $e) {
                $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
                $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
            }
        }
    }

    function save()
    {
        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_POST);
        $type = rcube_utils::get_input_value('_wblistwb', rcube_utils::INPUT_POST);
        $email = rcube_utils::get_input_value('_wblistemail', rcube_utils::INPUT_POST);
        $priority = rcube_utils::get_input_value('_wblistpriority', rcube_utils::INPUT_POST);
        $enabled = rcube_utils::get_input_value('_wblistenabled', rcube_utils::INPUT_POST);
        $enabled = (!$enabled) ? 'n' : 'y';

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);
            $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

            if (empty($id)) {
                $limit = $this->rcmail->config->get('wblist_limit');

                if ($spam_user[0]['id'] == '') {
                    $params = [
                        'server_id' => $mail_user[0]['server_id'],
                        'priority'  => '5',
                        'policy_id' => $this->rcmail->config->get('wblist_default_policy'),
                        'email'     => $mail_user[0]['email'],
                        'fullname'  => $mail_user[0]['email'],
                        'local'     => 'Y'
                    ];

                    $this->soap->mail_spamfilter_user_add($session_id, $uid, $params);
                    $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);
                }

                $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, ['rid' => $spam_user[0]['id']]);
                if (count($wblist) < $limit) {
                    $params = [
                        'sys_userid'  => $spam_user[0]['sys_userid'],
                        'sys_groupid' => $spam_user[0]['sys_groupid'],
                        'server_id'   => $spam_user[0]['server_id'],
                        'rid'         => $spam_user[0]['id'],
                        'wb'          => $type,
                        'email'       => $email,
                        'priority'    => $priority,
                        'active'      => $enabled
                    ];

                    if ($type == "W") {
                        $this->soap->mail_spamfilter_whitelist_add($session_id, $uid, $params);
                    }
                    else {
                        $this->soap->mail_spamfilter_blacklist_add($session_id, $uid, $params);
                    }

                    $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
                }
                else {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('wblimitreached'), 'error');
                }
            }
            else {
                $wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, $id);
                if ($wblist['rid'] == $spam_user[0]['id']) {
                    $params = [
                        'server_id' => $spam_user[0]['server_id'],
                        'rid'       => $spam_user[0]['id'],
                        'wb'        => $type,
                        'email'     => $email,
                        'priority'  => $priority,
                        'active'    => $enabled
                    ];

                    if ($type == "W") {
                        $this->soap->mail_spamfilter_whitelist_update($session_id, $uid, $id, $params);
                    }
                    else {
                        $this->soap->mail_spamfilter_blacklist_update($session_id, $uid, $id, $params);
                    }

                    $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
                }
                else {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');
                }
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
        $this->rcmail->output->add_label('ispconfig3_wblist.wblistdelconfirm', 'ispconfig3_wblist.textempty',
            'ispconfig3_wblist.invalidaddress');

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form([
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.ispconfig3_wblist.save',
                'noclose' => true
            ] + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('acc_wblist') . '</legend>' . "\n";

        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
        $enabled = 0;
        $type = 'W';
        if (!empty($id)) {
            try {
                $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
                $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
                $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);

                if (rcube_utils::get_input_value('_type', rcube_utils::INPUT_GET) == "W") {
                    $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, ['wblist_id' => $id]);
                }
                else {
                    $wblist = $this->soap->mail_spamfilter_blacklist_get($session_id, ['wblist_id' => $id]);
                    $type = 'B';
                }

                $this->soap->logout($session_id);

                $enabled = $wblist[0]['active'];

                if ($wblist[0]['rid'] != $spam_user[0]['id']) {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');

                    $enabled = 'n';
                    $wblist[0]['email'] = '';
                    $wblist[0]['priority'] = '';
                }
            }
            catch (SoapFault $e) {
                $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
                $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
            }
        }
        else {
            $wblist[0]['priority'] = '5';
        }

        $enabled = ($enabled == 'y') ? 1 : 0;

        $hidden_id = new html_hiddenfield(['name' => '_id', 'value' => $wblist[0]['wblist_id'] ?? '']);
        $out .= $hidden_id->show();

        $table = new html_table(['cols' => 2, 'class' => 'propform']);

        $field_id = 'wblistemail';
        $input_wblistemail = new html_inputfield(['name' => '_' . $field_id, 'id' => $field_id, 'size' => 70]);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('email'))));
        $table->add('', $input_wblistemail->show($wblist[0]['email'] ?? ''));

        $field_id = 'wblistwb';
        $input_wblistwb = new html_select(['name' => '_' . $field_id, 'id' => $field_id]);
        $input_wblistwb->add([$this->gettext('wblistwhitelist'), $this->gettext('wblistblacklist')], ['W', 'B']);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('wblisttype'))));
        $table->add('', $input_wblistwb->show($type));

        $field_id = 'wblistpriority';
        $input_wblistpriority = new html_select(['name' => '_' . $field_id, 'id' => $field_id]);
        $input_wblistpriority->add(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('wblistpriority'))));
        $table->add('', $input_wblistpriority->show($wblist[0]['priority'] ?? ''));

        $field_id = 'wblistenabled';
        $input_wblistenabled = new html_checkbox(['name' => '_' . $field_id, 'id' => $field_id, 'value' => '1']);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('wblistenabled'))));
        $table->add('', $input_wblistenabled->show($enabled));

        $out .= $table->show();
        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $out = '<fieldset><legend>' . $this->gettext('wblistentries') . '</legend>' . "\n";

        $rule_table = new html_table([
            'id' => 'rule-table',
            'class' => 'records-table', 'cellspacing' => '0', 'cols' => 4
        ]);
        $rule_table->add_header(null, $this->gettext('email'));
        $rule_table->add_header(['width' => '16px'], '');
        $rule_table->add_header(['width' => '16px'], '');
        $rule_table->add_header(['width' => '16px'], '');

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            $spam_user = $this->soap->mail_spamfilter_user_get($session_id, ['email' => $mail_user[0]['email']]);
            $wblist = $this->soap->mail_spamfilter_whitelist_get($session_id, ['rid' => $spam_user[0]['id']]);
            $this->soap->logout($session_id);

            foreach ((array) $wblist as $value) {
                $row_attribs = ['id' => 'rule_' . $value['wblist_id']];
                if ($value['wblist_id'] == rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET)) {
                    $row_attribs['class'] = 'selected';
                }

                $rule_table->set_row_attribs($row_attribs);
                $this->_rule_row($rule_table, $value['email'], $value['wb'], $value['active'],
                    $value['wblist_id'], $attrib);
            }

            if (empty($wblist)) {
                $rule_table->add(['colspan' => '4'], rcube::Q($this->gettext('wblistnorules')));
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

    private function _rule_row($rule_table, $name, $wb, $active, $id, $attrib)
    {
        $rule_table->add(['class' => 'rule', 'onclick' => 'wb_edit(' . $id . ',"' . $wb . '");'], $name);

        $list = ($wb == 'W') ? 'whitelist' : 'blacklist';
        $list_button = $this->api->output->button([
            'name' => 'list_button',
            'type' => 'link',
            'class' => 'button icon ' . $list,
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'ispconfig3_wblist.wblist' . $list
        ]);
        $rule_table->add(['class' => 'control'], $list_button);

        $status = ($active == 'y') ? 'enabled' : 'disabled';
        $status_button = $this->api->output->button([
            'name' => 'status_button',
            'type' => 'link',
            'class' => 'button icon status-' . $status,
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'ispconfig3_wblist.wblist' . $status
        ]);
        $rule_table->add(['class' => 'control'], $status_button);

        $del_button = $this->api->output->button([
            'command' => 'plugin.ispconfig3_wblist.del',
            'prop' => $id . '\',\'' . $wb,
            'type' => 'link',
            'class' => 'button icon delete',
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'delete'
        ]);
        $rule_table->add(['class' => 'control'], $del_button);

        return $rule_table;
    }
}
