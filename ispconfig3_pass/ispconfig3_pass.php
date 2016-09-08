<?php
class ispconfig3_pass extends rcube_plugin
{
    public $task = 'settings';
    private $rcmail_inst;

    function init()
    {
        $this->rcmail_inst = rcmail::get_instance();
        $this->load_config();
        $this->add_texts('localization/', true);
        $this->require_plugin('ispconfig3_account');

        $this->register_action('plugin.ispconfig3_pass', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_pass.save', array($this, 'save'));

        $this->api->output->add_handler('pass_form', array($this, 'gen_form'));
        $this->api->output->add_handler('sectionname_pass', array($this, 'prefs_section_name'));

        $this->include_script('pwdmeter.js');
        $this->include_script('pass.js');
    }

    function init_html()
    {
        $this->rcmail_inst->output->set_pagetitle($this->gettext('password'));
        $this->rcmail_inst->output->send('ispconfig3_pass.pass');
    }

    function load_config($fname = 'config.inc.php')
    {
        $config = $this->home . '/config/' . $fname;
        if (file_exists($config))
        {
            if (!$this->rcmail_inst->config->load_from_file($config))
                rcube::raise_error(array('code' => 527, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Failed to load config from $config"), true, false);
        }
        else if (file_exists($config . ".dist"))
        {
            if (!$this->rcmail_inst->config->load_from_file($config . '.dist'))
                rcube::raise_error(array('code' => 527, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Failed to load config from $config"), true, false);
        }
    }

    function prefs_section_name()
    {
        return $this->gettext('password');
    }

    function save()
    {
        $confirm = $this->rcmail_inst->config->get('password_confirm_current');

        if (($confirm && !isset($_POST['_curpasswd'])) || !isset($_POST['_newpasswd']))
            $this->rcmail_inst->output->command('display_message', $this->gettext('nopassword'), 'error');
        else
        {
            $curpwd = rcube_utils::get_input_value('_curpasswd', rcube_utils::INPUT_POST);
            $newpwd = rcube_utils::get_input_value('_newpasswd', rcube_utils::INPUT_POST);
            $pwl = $this->rcmail_inst->config->get('password_min_length');
            $checkUpper = $this->rcmail_inst->config->get('password_check_upper');
            $checkLower = $this->rcmail_inst->config->get('password_check_lower');
            $checkSymbol = $this->rcmail_inst->config->get('password_check_symbol');
            $checkNumber = $this->rcmail_inst->config->get('password_check_number');
            $error = false;

            if (!empty($pwl))
                $pwl = max(6, $pwl);
            else
                $pwl = 6;

            if ($confirm && $this->rcmail_inst->decrypt($_SESSION['password']) != $curpwd)
                $this->rcmail_inst->output->command('display_message', $this->gettext('passwordincorrect'), 'error');
            else
            {
                if (strlen($newpwd) < $pwl)
                {
                    $error = true;
                    $this->rcmail_inst->output->command('display_message', str_replace("%d", $pwl, $this->gettext('passwordminlength')), 'error');
                }

                if (!$error && $checkNumber && !preg_match("#[0-9]+#", $newpwd))
                {
                    $error = true;
                    $this->rcmail_inst->output->command('display_message', $this->gettext('passwordchecknumber'), 'error');
                }

                if (!$error && $checkLower && !preg_match("#[a-z]+#", $newpwd))
                {
                    $error = true;
                    $this->rcmail_inst->output->command('display_message', $this->gettext('passwordchecklower'), 'error');
                }

                if (!$error && $checkUpper && !preg_match("#[A-Z]+#", $newpwd))
                {
                    $error = true;
                    $this->rcmail_inst->output->command('display_message', $this->gettext('passwordcheckupper'), 'error');
                }

                if (!$error && $checkSymbol && !preg_match("#\W+#", $newpwd))
                {
                    $error = true;
                    $this->rcmail_inst->output->command('display_message', $this->gettext('passwordchecksymbol'), 'error');
                }

                if (!$error)
                {
                    try
                    {
                        $soap = new SoapClient(null, array(
                            'location' => $this->rcmail_inst->config->get('soap_url') . 'index.php',
                            'uri' => $this->rcmail_inst->config->get('soap_url'),
                            'stream_context' => stream_context_create(array(
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                )
                            ))
                        ));
                        $session_id = $soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
                        $mail_user = $soap->mail_user_get($session_id, array('login' => $this->rcmail_inst->user->data['username']));
                        $params = $mail_user[0];

                        $ispconfig_version = $this->soap->server_get_app_version($session_id);
                        if (version_compare($ispconfig_version['ispc_app_version'], '3.1rc1', '<')) {
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

                        $this->rcmail_inst->output->command('display_message', $this->gettext('successfullysaved'), 'confirmation');

                        $_SESSION['password'] = $this->rcmail_inst->encrypt($newpwd);

                        $this->rcmail_inst->user->data['password'] = $_SESSION['password'];
                    } catch (SoapFault $e)
                    {
                        $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
                    }
                }
            }
        }
        $this->init_html();
    }

    function gen_form()
    {
        $confirm = $this->rcmail_inst->config->get('password_confirm_current');
        $pwl = $this->rcmail_inst->config->get('password_min_length');

        if (!empty($pwl))
            $pwl = max(6, $pwl);
        else
            $pwl = 6;

        $this->rcmail_inst->output->add_label('ispconfig3_pass.nopassword',
            'ispconfig3_pass.nocurpassword',
            'ispconfig3_pass.passwordinconsistency',
            'ispconfig3_pass.changepasswd',
            'ispconfig3_pass.passwordminlength');

        $this->rcmail_inst->output->add_script('var pw_min_length =' . $pwl . ';');
        $this->rcmail_inst->output->set_env('framed', true);

        $out = '<fieldset><legend>' . $this->gettext('password') . '</legend>' . "\n";

        $table = new html_table(array('cols' => 2, 'class' => 'propform'));

        if ($confirm)
        {
            $input_newpasswd = new html_passwordfield(array('name' => '_curpasswd', 'id' => 'curpasswd', 'size' => 20));
            $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('curpasswd')));
            $table->add('', $input_newpasswd->show());
        }

        $input_newpasswd = new html_passwordfield(array('name' => '_newpasswd', 'id' => 'newpasswd', 'size' => 20));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('newpasswd')));
        $table->add('', $input_newpasswd->show() . '<div id="pass-check">');

        $input_confpasswd = new html_passwordfield(array('name' => '_confpasswd', 'id' => 'confpasswd', 'size' => 20));
        $table->add('title', rcube_utils::rep_specialchars_output($this->gettext('confpasswd')));
        $table->add('', $input_confpasswd->show());

        $out .= $table->show();
        $out .= "</fieldset>\n";

        return $out;
    }
}
