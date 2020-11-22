<?php
class ispconfig3_autoreply extends rcube_plugin
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
        $this->require_plugin('jqueryui');

        $this->register_action('plugin.ispconfig3_autoreply', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_autoreply.save', array($this, 'save'));

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_autoreply') === 0) {
            $this->api->output->add_handler('autoreply_form', array($this, 'gen_form'));
            $this->api->output->add_handler('sectionname_autoreply', array($this, 'prefs_section_name'));

            $this->include_stylesheet('skins/classic/jquery.ui.datetime.css');
            $this->include_script('skins/classic/jquery.ui.datetime.min.js');

            $skin = $this->rcmail->config->get('skin');
            if (file_exists($this->home . '/skins/' . $skin . '/jquery.ui.datetime.css'))
                $this->include_stylesheet('skins/' . $skin . '/jquery.ui.datetime.css');

            $this->include_script('autoreply.js');

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
        $this->rcmail->output->set_pagetitle($this->gettext('acc_autoreply'));
        $this->rcmail->output->send('ispconfig3_autoreply.autoreply');
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_autoreply');
    }

    function save()
    {
        $enabled = rcube_utils::get_input_value('_autoreplyenabled', rcube_utils::INPUT_POST);
        $body = rcube_utils::get_input_value('_autoreplybody', rcube_utils::INPUT_POST);
        $subject = rcube_utils::get_input_value('_autoreplysubject', rcube_utils::INPUT_POST);
        $startdate = rcube_utils::get_input_value('_autoreplystarton', rcube_utils::INPUT_POST);
        $enddate = rcube_utils::get_input_value('_autoreplyendby', rcube_utils::INPUT_POST);

        $server_tz = new DateTimeZone(date_default_timezone_get());
        $server_offset = $server_tz->getOffset(new DateTime);
        $user_tz = new DateTimeZone($this->rcmail->config->get('timezone'));
        $user_offset = $user_tz->getOffset(new DateTime);

        $startdate = strtotime($startdate) - ($user_offset - $server_offset);
        $enddate = strtotime($enddate) - ($user_offset - $server_offset);

        if ($enddate < $startdate)
            $enddate = $startdate + 86400;

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

            $ispconfig_version = $this->soap->server_get_app_version($session_id);
            if (version_compare($ispconfig_version['ispc_app_version'], '3.1dev', '<')) {
                $startdate = array('year'   => date('Y', $startdate),
                    'month'  => date('m', $startdate),
                    'day'    => date('d', $startdate),
                    'hour'   => date('H', $startdate),
                    'minute' => date('i', $startdate));

                $enddate = array('year'   => date('Y', $enddate),
                    'month'  => date('m', $enddate),
                    'day'    => date('d', $enddate),
                    'hour'   => date('H', $enddate),
                    'minute' => date('i', $enddate));
            }
            else {
                $datetimeformat = 'Y-m-d H:i:s';
                $startdate = date($datetimeformat, $startdate);
                $enddate = date($datetimeformat, $enddate);
            }

            $params = $mail_user[0];
            unset($params['password']);
            $params['autoresponder'] = $enabled;
            $params['autoresponder_text'] = $body;
            $params['autoresponder_subject'] = $subject;
            $params['autoresponder_start_date'] = $startdate;
            $params['autoresponder_end_date'] = $enddate;

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
        $this->rcmail->output->add_label('ispconfig3_autoreply.textempty');

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form(array(
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.ispconfig3_autoreply.save',
                'noclose' => true
            ) + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('acc_autoreply') . '</legend>' . "\n";

        $enabled = 0;
        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $this->soap->logout($session_id);

            $enabled = $mail_user[0]['autoresponder'];
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $enabled = ($enabled == 'y') ? 1 : 0;

        if (empty($mail_user[0]['autoresponder_start_date']) ||
            $mail_user[0]['autoresponder_start_date'] == '0000-00-00 00:00:00') {
            $dt = new DateTime('@' . time());
            $dt->setTimezone(new DateTimeZone($this->rcmail->config->get('timezone')));
            $mail_user[0]['autoresponder_start_date'] = $dt->format('Y-m-d H:i');
        }
        else {
            $mail_user[0]['autoresponder_start_date'] = strtotime($mail_user[0]['autoresponder_start_date']);
            $dt = new DateTime('@' . $mail_user[0]['autoresponder_start_date']);
            $dt->setTimezone(new DateTimeZone($this->rcmail->config->get('timezone')));
            $mail_user[0]['autoresponder_start_date'] = $dt->format('Y-m-d H:i');
        }

        if (empty($mail_user[0]['autoresponder_end_date']) ||
            $mail_user[0]['autoresponder_end_date'] == '0000-00-00 00:00:00') {
            $dt = new DateTime('@' . (time() + 86400));
            $dt->setTimezone(new DateTimeZone($this->rcmail->config->get('timezone')));
            $mail_user[0]['autoresponder_end_date'] = $dt->format('Y-m-d H:i');
        }
        else {
            $mail_user[0]['autoresponder_end_date'] = strtotime($mail_user[0]['autoresponder_end_date']);
            $dt = new DateTime('@' . $mail_user[0]['autoresponder_end_date']);
            $dt->setTimezone(new DateTimeZone($this->rcmail->config->get('timezone')));
            $mail_user[0]['autoresponder_end_date'] = $dt->format('Y-m-d H:i');
        }

        $table = new html_table(array('cols' => 2, 'class' => 'propform'));

        $field_id = 'autoreplysubject';
        $input_autoreplysubject = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => 40));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('subject'))));
        $table->add('', $input_autoreplysubject->show($mail_user[0]['autoresponder_subject']));

        $field_id = 'autoreplybody';
        $input_autoreplybody = new html_textarea(array('name' => '_' . $field_id, 'id' => $field_id, 'cols' => 48, 'rows' => 15));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('autoreplymessage'))));
        $table->add('', $input_autoreplybody->show($mail_user[0]['autoresponder_text']));

        $field_id = 'autoreplystarton';
        $input_autoreplystarton = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => 20));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('autoreplystarton'))));
        $table->add('', $input_autoreplystarton->show($mail_user[0]['autoresponder_start_date']));

        $field_id = 'autoreplyendby';
        $input_autoreplyendby = new html_inputfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => 20));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('autoreplyendby'))));
        $table->add('', $input_autoreplyendby->show($mail_user[0]['autoresponder_end_date']));

        $field_id = 'autoreplyenabled';
        $input_autoreplyenabled = new html_checkbox(array('name' => '_' . $field_id, 'id' => $field_id, 'value' => 1));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('autoreplyenabled'))));
        $table->add('', $input_autoreplyenabled->show($enabled));

        $out .= $table->show();
        $out .= "</fieldset>\n";
        $out .= '</form>';

        return $out;
    }
}
