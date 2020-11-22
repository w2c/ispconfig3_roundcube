<?php
class ispconfig3_fetchmail extends rcube_plugin
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

        $this->register_action('plugin.ispconfig3_fetchmail', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_fetchmail.save', array($this, 'save'));
        $this->register_action('plugin.ispconfig3_fetchmail.del', array($this, 'del'));

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_fetchmail') === 0) {
            $this->load_config('config/config.inc.php.dist');
            if (file_exists($this->home . '/config/config.inc.php')) {
                $this->load_config('config/config.inc.php');
            }

            $this->api->output->add_handler('fetchmail_form', array($this, 'gen_form'));
            $this->api->output->add_handler('fetchmail_table', array($this, 'gen_table'));
            $this->api->output->add_handler('sectionname_fetchmail', array($this, 'prefs_section_name'));

            $this->include_script('fetchmail.js');
            $this->include_stylesheet($this->local_skin_path() . '/fetchmail.css');

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
        $this->rcmail->output->set_pagetitle($this->gettext('acc_fetchmail'));
        $this->rcmail->output->send('ispconfig3_fetchmail.fetchmail');
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_fetchmail');
    }

    function del()
    {
        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);

        if (!empty($id)) {
            try {
                $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
                $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
                // Alternatively also search the email field, this can differ from the login field for legacy reasons.
                if (empty($mail_user)) {
                    $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
                }

                $mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, $id);

                if ($mail_fetchmail['destination'] == $mail_user[0]['email']) {
                    $delete = $this->soap->mail_fetchmail_delete($session_id, $id);

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
        $typ = rcube_utils::get_input_value('_fetchmailtyp', rcube_utils::INPUT_POST);
        $server = rcube_utils::get_input_value('_fetchmailserver', rcube_utils::INPUT_POST);
        $user = rcube_utils::get_input_value('_fetchmailuser', rcube_utils::INPUT_POST);
        $pass = rcube_utils::get_input_value('_fetchmailpass', rcube_utils::INPUT_POST);
        $delete = rcube_utils::get_input_value('_fetchmaildelete', rcube_utils::INPUT_POST);
        $enabled = rcube_utils::get_input_value('_fetchmailenabled', rcube_utils::INPUT_POST);

        if (!$delete)
            $delete = 'n';
        else
            $delete = 'y';

        if (!$enabled)
            $enabled = 'n';
        else
            $enabled = 'y';

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $uid = $this->soap->client_get_id($session_id, $mail_user[0]['sys_userid']);

            if (empty($id)) {
                $mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, array('destination' => $mail_user[0]['email']));
                $limit = $this->rcmail->config->get('fetchmail_limit');

                if (count($mail_fetchmail) < $limit) {
                    $params = array('server_id'       => $mail_user[0]['server_id'],
                                    'type'            => $typ,
                                    'source_server'   => $server,
                                    'source_username' => $user,
                                    'source_password' => $pass,
                                    'source_delete'   => $delete,
                                    'destination'     => $mail_user[0]['email'],
                                    'active'          => $enabled);

                    $add = $this->soap->mail_fetchmail_add($session_id, $uid, $params);

                    $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');
                }
                else {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('fetchmaillimitreached'), 'error');
                }
            }
            else {
                $mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, $id);

                if ($mail_fetchmail['destination'] == $mail_user[0]['email']) {
                    $params = array('server_id'       => $mail_fetchmail['server_id'],
                                    'type'            => $typ,
                                    'source_server'   => $server,
                                    'source_username' => $user,
                                    'source_password' => $pass,
                                    'source_delete'   => $delete,
                                    'destination'     => $mail_user[0]['email'],
                                    'active'          => $enabled);

                    $update = $this->soap->mail_fetchmail_update($session_id, $uid, $id, $params);

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
        $this->rcmail->output->add_label('ispconfig3_fetchmail.fetchmaildelconfirm', 'ispconfig3_fetchmail.textempty');

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form(array(
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.ispconfig3_fetchmail.save',
                'noclose' => true
            ) + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('acc_fetchmail') . '</legend>' . "\n";

        $id = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET);
        $enabled = 0;
        $delete = 0;
        if (!empty($id)) {
            try {
                $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
                $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
                // Alternatively also search the email field, this can differ from the login field for legacy reasons.
                if (empty($mail_user)) {
                    $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
                }

                $mail_fetchmail = $this->soap->mail_fetchmail_get($session_id, $id);
                $this->soap->logout($session_id);

                $enabled = $mail_fetchmail['active'];
                $delete = $mail_fetchmail['source_delete'];

                if ($mail_fetchmail['destination'] != $mail_user[0]['email']) {
                    $this->rcmail->output->command('display_message', 'Error: ' . $this->gettext('opnotpermitted'), 'error');

                    $enabled = 'n';
                    $delete = 'n';
                    $mail_fetchmail['mailget_id'] = '';
                    $mail_fetchmail['server_id'] = '';
                    $mail_fetchmail['type'] = '';
                    $mail_fetchmail['source_server'] = '';
                    $mail_fetchmail['source_username'] = '';
                    $mail_fetchmail['source_delete'] = '';
                }
            }
            catch (SoapFault $e) {
                $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
                $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
            }
        }

        $delete = ($delete == 'y') ? 1 : 0;
        $enabled = ($enabled == 'y') ? 1 : 0;

        $hidden_id = new html_hiddenfield(array('name' => '_id', 'value' => $mail_fetchmail['mailget_id']));
        $out .= $hidden_id->show();

        $table = new html_table(array('cols' => 2, 'class' => 'propform'));

        $field_id = 'fetchmailtyp';
        $input_fetchmailtyp = new html_select(array('name' => '_' . $field_id, 'id' => $field_id));
        $input_fetchmailtyp->add(array('POP3', 'IMAP', 'POP3 SSL', 'IMAP SSL'), array('pop3', 'imap', 'pop3ssl', 'imapssl'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('fetchmailtyp'))));
        $table->add('', $input_fetchmailtyp->show($mail_fetchmail['type']));

        $field_id = 'fetchmailserver';
        $input_fetchmailserver = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'maxlength' => 320, 'size' => 40));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('fetchmailserver'))));
        $table->add('', $input_fetchmailserver->show($mail_fetchmail['source_server']));

        $field_id = 'fetchmailuser';
        $input_fetchmailuser = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'maxlength' => 320, 'size' => 40));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('username'))));
        $table->add('', $input_fetchmailuser->show($mail_fetchmail['source_username']));

        $field_id = 'fetchmailpass';
        $input_fetchmailpass = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id, 'maxlength' => 320, 'size' => 40, 'autocomplete' => 'off'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('password'))));
        $table->add('', $input_fetchmailpass->show($mail_fetchmail['source_password']));

        $field_id = 'fetchmaildelete';
        $input_fetchmaildelete = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => '1'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('fetchmaildelete'))));
        $table->add('', $input_fetchmaildelete->show($delete));

        $field_id = 'fetchmailenabled';
        $input_fetchmailenabled = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => '1'));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('fetchmailenabled'))));
        $table->add('', $input_fetchmailenabled->show($enabled));

        $out .= $table->show();
        $out .= "</fieldset>\n";

        return $out;
    }

    function gen_table($attrib)
    {
        $out = '<fieldset><legend>' . $this->gettext('fetchmail_entries') . '</legend>' . "\n";

        $fetch_table = new html_table(array('id' => 'fetch-table',
                                            'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
        $fetch_table->add_header(null, $this->gettext('fetchmailserver'));
        $fetch_table->add_header(array('width' => '16px'), '');
        $fetch_table->add_header(array('width' => '16px'), '');

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            $fetchmail = $this->soap->mail_fetchmail_get($session_id, array('destination' => $mail_user[0]['email']));
            $this->soap->logout($session_id);

            for ($i = 0; $i < count($fetchmail); $i++) {
                $row_attribs = array('id' => 'fetch_' . $fetchmail[$i]['mailget_id']);
                if ($fetchmail[$i]['mailget_id'] == rcube_utils::get_input_value('_id', rcube_utils::INPUT_GET)) {
                    $row_attribs['class'] = 'selected';
                }

                $fetch_table->set_row_attribs($row_attribs);
                $this->_fetch_row($fetch_table,
                    $fetchmail[$i]['source_username'] . '@' . $fetchmail[$i]['source_server'],
                    $fetchmail[$i]['active'], $fetchmail[$i]['mailget_id'], $attrib);
            }

            if (count($fetchmail) == 0) {
                $fetch_table->add(array('colspan' => '3'), rcube::Q($this->gettext('nofetch')));
                $fetch_table->add_row();
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $out .= "<div id=\"fetch-cont\">" . $fetch_table->show() . "</div>\n";
        $out .= "</fieldset>\n";
        $out .= '</form>';

        return $out;
    }

    private function _fetch_row($fetch_table, $name, $active, $id, $attrib)
    {
        $fetch_table->add(array('class' => 'fetch', 'onclick' => 'fetchmail_edit(' . $id . ');'), $name);

        $status = ($active == 'y') ? 'enabled' : 'disabled';
        $status_button = $this->api->output->button(array(
            'name' => 'status_button',
            'type' => 'link',
            'class' => 'button icon status-' . $status,
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'ispconfig3_fetchmail.fetchmail' . $status));
        $fetch_table->add(array('class' => 'control'), $status_button);

        $del_button = $this->api->output->button(array(
            'command' => 'plugin.ispconfig3_fetchmail.del',
            'prop' => $id,
            'type' => 'link',
            'class' => 'button icon delete',
            'innerclass' => 'inner',
            'content' => '',
            'title' => 'delete'));
        $fetch_table->add(array('class' => 'control'), $del_button);

        return $fetch_table;
    }
}
