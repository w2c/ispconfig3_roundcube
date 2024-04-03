<?php

class ispconfig3_filter extends rcube_plugin
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

        $this->register_action('plugin.ispconfig3_filter', [$this, 'init_html']);
        $this->register_action('plugin.ispconfig3_filter.show', [$this, 'init_html']);
        $this->register_action('plugin.ispconfig3_filter.save', [$this, 'save']);
        $this->register_action('plugin.ispconfig3_filter.del', [$this, 'del']);

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_filter') === 0) {
            $this->load_config('config/config.inc.php.dist');
            if (file_exists($this->home . '/config/config.inc.php')) {
                $this->load_config('config/config.inc.php');
            }

            $this->api->output->add_handler('filter_form', [$this, 'gen_form']);
            $this->api->output->add_handler('filter_table', [$this, 'gen_table']);
            $this->api->output->add_handler('sectionname_filter', [$this, 'prefs_section_name']);

            $this->include_script('filter.js');
            $this->include_stylesheet($this->local_skin_path() . '/filter.css');

            $this->soap = new SoapClient(null, [
                'location' => $this->rcmail->config->get('soap_url') . 'index.php',
                'uri' => $this->rcmail->config->get('soap_url'),
                $this->rcmail->config->get('soap_validate_cert') ?:
                    'stream_context' => stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ]
                )
            ]);
        }
    }

    function init_html()
    {
        $this->rcmail->output->set_pagetitle($this->gettext('acc_filter'));
        $this->rcmail->output->send('ispconfig3_filter.filter');
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_filter');
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

                $filter = $this->soap->mail_user_filter_get($session_id, $id);

                if ($filter['mailuser_id'] == $mail_user[0]['mailuser_id']) {
                    $this->soap->mail_user_filter_delete($session_id, $id);
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
        $name = rcube_utils::get_input_value('_filtername', rcube_utils::INPUT_POST);
        $source = rcube_utils::get_input_value('_filtersource', rcube_utils::INPUT_POST);
        $op = rcube_utils::get_input_value('_filterop', rcube_utils::INPUT_POST);
        $searchterm = rcube_utils::get_input_value('_filtersearchterm', rcube_utils::INPUT_POST);
        $action = rcube_utils::get_input_value('_filteraction', rcube_utils::INPUT_POST);
        $target = mb_convert_encoding(rcube_utils::get_input_value('_filtertarget', rcube_utils::INPUT_POST), 'UTF-8', 'UTF7-IMAP');
        $enabled = rcube_utils::get_input_value('_filterenabled', rcube_utils::INPUT_POST);
        $enabled = (!$enabled) ? 'n' : 'y';

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }

            $mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'mail');
            $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

            if ($mail_server['mail_filter_syntax'] == 'maildrop') {
                $target = str_replace("INBOX.", "", $target);
            }

            if (empty($id)) {
                $filter = $this->soap->mail_user_filter_get($session_id, ['mailuser_id' => $mail_user[0]['mailuser_id']]);
                $limit = $this->rcmail->config->get('filter_limit');

                if (count($filter) < $limit) {
                    $params = [
                        'mailuser_id' => $mail_user[0]['mailuser_id'],
                        'rulename'    => $name,
                        'source'      => $source,
                        'searchterm'  => $searchterm,
                        'op'          => $op,
                        'action'      => $action,
                        'target'      => $target,
                        'active'      => $enabled
                    ];

                    $this->soap->mail_user_filter_add($session_id, $uid, $params);
                    $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
                }
                else {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('filterlimitreached'), 'error');
                }
            }
            else {
                $filter = $this->soap->mail_user_filter_get($session_id, $id);
                if ($filter['mailuser_id'] == $mail_user[0]['mailuser_id']) {
                    $params = [
                        'mailuser_id' => $mail_user[0]['mailuser_id'],
                        'rulename'    => $name,
                        'source'      => $source,
                        'searchterm'  => $searchterm,
                        'op'          => $op,
                        'action'      => $action,
                        'target'      => $target,
                        'active'      => $enabled
                    ];

                    $this->soap->mail_user_filter_update($session_id, $uid, $id, $params);
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
        $this->rcmail->output->add_label('ispconfig3_filter.filterdelconfirm', 'ispconfig3_filter.textempty');
        $this->rcmail->storage_connect();

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form([
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.ispconfig3_filter.save',
                'noclose' => true
            ] + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('acc_filter') . '</legend>' . "\n";

        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
        $enabled = 0;

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $ispconfig_version = $this->soap->server_get_app_version($session_id);

            if (!empty($id)) {
                $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
                // Alternatively also search the email field, this can differ from the login field for legacy reasons.
                if (empty($mail_user)) {
                    $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
                }

                $filter = $this->soap->mail_user_filter_get($session_id, ['filter_id' => $id]);
                $mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'mail');
                $this->soap->logout($session_id);

                $enabled = $filter[0]['active'];
                if ($filter[0]['mailuser_id'] != $mail_user[0]['mailuser_id']) {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');
                    $enabled = 'n';
                }

                $enabled = ($enabled == 'y') ? 1 : 0;

                if ($mail_server['mail_filter_syntax'] == 'maildrop') {
                    $filter[0]['target'] = "INBOX." . $filter[0]['target'];
                }

                $filter[0]['target'] = mb_convert_encoding($filter[0]['target'], 'UTF7-IMAP', 'UTF-8');
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $hidden_id = new html_hiddenfield(['name' => '_id', 'value' => $filter[0]['filter_id'] ?? '']);
        $out .= $hidden_id->show();

        $table = new html_table(['cols' => 2, 'class' => 'compact-table']);

        $field_id = 'filtername';
        $input_filtername = new html_inputfield(['name' => '_' . $field_id, 'id' => $field_id, 'size' => 70]);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('filtername'))));
        $table->add('', $input_filtername->show($filter[0]['rulename'] ?? ''));

        $field_id = 'filtersource';
        $input_filtersource = new html_select(['name' => '_' . $field_id, 'id' => $field_id]);
        $input_filtersource->add([$this->gettext('filtersubject'), $this->gettext('filterfrom'),
            $this->gettext('filterto')], ['Subject', 'From', 'To']);

        $field_id = 'filterop';
        $input_filterop = new html_select(['name' => '_' . $field_id, 'id' => $field_id]);

        $filter_op = [
            'filtercontains' => 'contains',
            'filteris' => 'is',
            'filterbegins' => 'begins',
            'filterends' => 'ends',
        ];

        if (version_compare($ispconfig_version['ispc_app_version'] ?? '', '3.2', '>=')) {
            $filter_op_extended = [
                'filterregex' => 'regex',
                'filterlocalpart' => 'localpart',
                'filterdomain' => 'domain',
            ];
            $filter_op = array_merge($filter_op, $filter_op_extended);
        }

        $input_filterop->add(array_map([$this, 'gettext'], array_keys($filter_op)), array_values($filter_op));

        $field_id = 'filtersearchterm';
        $input_filtersearchterm = new html_inputfield(['name' => '_' . $field_id, 'id' => $field_id, 'size' => 43]);
        $table->add('title', html::label('filtersource', rcube::Q($this->gettext('filtersource'))));
        $table->add('', $input_filtersource->show($filter[0]['source'] ?? '') . $input_filterop->show($filter[0]['op'] ?? '') .
            $input_filtersearchterm->show($filter[0]['searchterm'] ?? ''));

        $field_id = 'filteraction';
        $input_filteraction = new html_select(['name' => '_' . $field_id, 'id' => $field_id]);
        $input_filteraction->add([$this->gettext('filtermove'), $this->gettext('filterdelete')], ['move', 'delete']);

        $field_id = 'filtertarget';
        $input_filtertarget = $this->rcmail->folder_selector(['name' => '_' . $field_id, 'id' => $field_id]);
        $table->add('title', html::label('filteraction', rcube::Q($this->gettext('filteraction'))));
        $table->add('', $input_filteraction->show($filter[0]['action'] ?? '') . $input_filtertarget->show($filter[0]['target'] ?? ''));

        $field_id = 'filterenabled';
        $input_filterenabled = new html_checkbox(['name' => '_' . $field_id, 'id' => $field_id, 'value' => '1']);
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('filterenabled'))));
        $table->add('', $input_filterenabled->show($enabled));

        $out .= $table->show();
        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $out = '<fieldset><legend>' . $this->gettext('filter_entries') . '</legend>' . "\n";

        $rule_table = new html_table(['id' => 'rule-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3]);
        $rule_table->add_header(null, $this->gettext('filtername'));
        $rule_table->add_header(['width' => '16px'], '');
        $rule_table->add_header(['width' => '16px'], '');

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }

            $filter = $this->soap->mail_user_filter_get($session_id, ['mailuser_id' => $mail_user[0]['mailuser_id']]);
            $this->soap->logout($session_id);

            foreach ((array) $filter as $filter_value) {
                $row_attribs = ['id' => 'rule_' . $filter_value['filter_id']];
                if ($filter_value['filter_id'] == rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET)) {
                    $row_attribs['class'] = 'selected';
                }

                $rule_table->set_row_attribs($row_attribs);
                $this->_rule_row($rule_table, $filter_value['rulename'], $filter_value['active'], $filter_value['filter_id']);
            }

            if (empty($filter)) {
                $rule_table->add(['colspan' => '3'], rcube::Q($this->gettext('filternorules')));
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

    private function _rule_row($rule_table, $name, $active, $id)
    {
        $rule_table->add(['class' => 'rule', 'onclick' => 'filter_edit(' . $id . ');'], $name);

        $status = ($active == 'y') ? 'enabled' : 'disabled';
        $status_button = $this->api->output->button([
            'name' => 'status_button',
            'type' => 'link',
            'class' => 'button icon status-' . $status,
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'ispconfig3_filter.filter' . $status
        ]);
        $rule_table->add(['class' => 'control'], $status_button);

        $del_button = $this->api->output->button([
            'command' => 'plugin.ispconfig3_filter.del',
            'prop' => $id,
            'type' => 'link',
            'class' => 'button icon delete',
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'delete'
        ]);
        $rule_table->add(['class' => 'control'], $del_button);
    }
}
