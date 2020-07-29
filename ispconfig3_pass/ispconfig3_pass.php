<?php
class ispconfig3_pass extends rcube_plugin
{
    public $task = 'settings';
    private $rcmail;
    private $rc;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rc = rcube::get_instance();
        $this->add_texts('localization/');
        $this->require_plugin('ispconfig3_account');
        $this->require_plugin('jqueryui');

        $this->register_action('plugin.ispconfig3_pass', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_pass.save', array($this, 'save'));

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_pass') === 0) {
            $this->load_config('config/config.inc.php.dist');
            if (file_exists($this->home . '/config/config.inc.php')) {
                $this->load_config('config/config.inc.php');
            }

            $this->api->output->add_handler('pass_form', array($this, 'gen_form'));
            $this->api->output->add_handler('sectionname_pass', array($this, 'prefs_section_name'));

            $this->include_script('pwdmeter.js');
            $this->include_script('pass.js');
            $this->include_stylesheet($this->local_skin_path() . '/pass.css');
        }
    }

    function init_html()
    {
        $this->rcmail->output->set_pagetitle($this->gettext('changepasswd'));
        $this->rcmail->output->send('ispconfig3_pass.pass');
    }

    function prefs_section_name()
    {
        return $this->gettext('changepasswd');
    }

    function save()
    {
        $confirm = $this->rcmail->config->get('password_confirm_current');

        if (($confirm && !isset($_POST['_curpasswd'])) || !isset($_POST['_newpasswd']))
            $this->rcmail->output->command('display_message', $this->gettext('nopassword'), 'error');
        else {
            $curpwd = rcube_utils::get_input_value('_curpasswd', rcube_utils::INPUT_POST);
            $newpwd = rcube_utils::get_input_value('_newpasswd', rcube_utils::INPUT_POST);
            $pwl = $this->rcmail->config->get('password_min_length');
            $checkUpper = $this->rcmail->config->get('password_check_upper');
            $checkLower = $this->rcmail->config->get('password_check_lower');
            $checkSymbol = $this->rcmail->config->get('password_check_symbol');
            $checkNumber = $this->rcmail->config->get('password_check_number');
            $error = false;

            if (!empty($pwl)) {
                $pwl = max(6, $pwl);
            }
            else {
                $pwl = 6;
            }

            if ($confirm && $this->rcmail->decrypt($_SESSION['password']) != $curpwd) {
                $this->rcmail->output->command('display_message', $this->gettext('passwordincorrect'), 'error');
            }
            else {
                if (strlen($newpwd) < $pwl) {
                    $error = true;
                    $this->rcmail->output->command('display_message', str_replace("%d", $pwl, $this->gettext('passwordminlength')), 'error');
                }

                if (!$error && $checkNumber && !preg_match("#[0-9]+#", $newpwd)) {
                    $error = true;
                    $this->rcmail->output->command('display_message', $this->gettext('passwordchecknumber'), 'error');
                }

                if (!$error && $checkLower && !preg_match("#[a-z]+#", $newpwd)) {
                    $error = true;
                    $this->rcmail->output->command('display_message', $this->gettext('passwordchecklower'), 'error');
                }

                if (!$error && $checkUpper && !preg_match("#[A-Z]+#", $newpwd)) {
                    $error = true;
                    $this->rcmail->output->command('display_message', $this->gettext('passwordcheckupper'), 'error');
                }

                if (!$error && $checkSymbol && !preg_match("#\W+#", $newpwd)) {
                    $error = true;
                    $this->rcmail->output->command('display_message', $this->gettext('passwordchecksymbol'), 'error');
                }

                if (!$error) {
                    try {
                        $soap = new SoapClient(null, array(
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

                        $session_id = $soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
                        $mail_user = $soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
                        // Alternatively also search the email field, this can differ from the login field for legacy reasons.
                        if (empty($mail_user)) {
                            $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
                        }

                        $params = $mail_user[0];

                        $ispconfig_version = $soap->server_get_app_version($session_id);
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

                        $params['password'] = $newpwd;

                        $uid = $soap->client_get_id($session_id, $mail_user[0]['sys_userid']);
                        $update = $soap->mail_user_update($session_id, $uid, $mail_user[0]['mailuser_id'], $params);
                        $soap->logout($session_id);

                        $this->rcmail->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');

                        $_SESSION['password'] = $this->rcmail->encrypt($newpwd);

                        $this->rcmail->user->data['password'] = $_SESSION['password'];
                    }
                    catch (SoapFault $e) {
                        $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
                        $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
                    }
                }
            }
        }

        $this->init_html();
    }

    function gen_form($attrib)
    {
        $this->rcmail->output->add_label('ispconfig3_pass.nopassword',
            'ispconfig3_pass.nocurpassword',
            'ispconfig3_pass.passwordinconsistency',
            'ispconfig3_pass.changepasswd',
            'ispconfig3_pass.passwordminlength');

        $confirm = $this->rcmail->config->get('password_confirm_current');
        $pwl = $this->rcmail->config->get('password_min_length');

        if (!empty($pwl)) {
            $pwl = max(6, $pwl);
        }
        else {
            $pwl = 6;
        }

        $this->rcmail->output->add_script('var pw_min_length =' . $pwl . ';');

        $form_id = $attrib['id'] ?: 'form';
        $out = $this->rcmail->output->request_form(array(
                'id'      => $form_id,
                'name'    => $form_id,
                'method'  => 'post',
                'task'    => 'settings',
                'action'  => 'plugin.ispconfig3_pass.save',
                'noclose' => true
            ) + $attrib);

        $out .= '<fieldset><legend>' . $this->gettext('password') . '</legend>' . "\n";

        $table = new html_table(array('cols' => 2, 'class' => 'propform'));

        if ($confirm) {
            $field_id = 'curpasswd';
            $input_newpasswd = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => 20));
            $table->add('title', html::label($field_id, rcube::Q($this->gettext('curpasswd'))));
            $table->add('', $input_newpasswd->show());
        }

        $field_id = 'newpasswd';
        $input_newpasswd2 = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => 20));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('newpasswd'))));
        $table->add('', $input_newpasswd2->show() . '<div id="pass-check">');

        $field_id = 'confpasswd';
        $input_confpasswd = new html_passwordfield(array('name' => '_' . $field_id, 'id' => $field_id, 'size' => 20));
        $table->add('title', html::label($field_id, rcube::Q($this->gettext('confpasswd'))));
        $table->add('', $input_confpasswd->show());

        $out .= $table->show();
        $out .= "</fieldset>\n";
        $out .= '</form>';

        return $out;
    }
}
