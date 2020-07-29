<?php
class ispconfig3_autoselect extends rcube_plugin
{
    public $task = 'login|logout';
    private $rcmail;
    private $soap;

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->require_plugin('ispconfig3_account');

        $this->load_config('config/config.inc.php.dist');
        if (file_exists($this->home . '/config/config.inc.php')) {
            $this->load_config('config/config.inc.php');
        }

        $this->load_con_config();

        $this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('template_object_loginform', array($this, 'template_object_loginform'));

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

    function load_con_config()
    {
        $config = $this->api->dir . 'ispconfig3_account/config/config.inc.php';
        if (file_exists($config)) {
            if (!$this->rcmail->config->load_from_file($config))
                rcube::raise_error(array('code' => 527, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Failed to load config from $config"), true, false);
        }
        else if (file_exists($config . ".dist")) {
            if (!$this->rcmail->config->load_from_file($config . '.dist'))
                rcube::raise_error(array('code' => 527, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Failed to load config from $config"), true, false);
        }
    }

    function template_object_loginform($args)
    {
        $args['content'] = preg_replace("/<tr><td\ class\=\"[A-z0-9]{1,}\"><label\ for\=\"rcmloginhost\">.*?rcmloginhost.*?td>\s+<\/tr>/s", "", $args['content']);

        return $args;
    }

    function authenticate($args)
    {
        if (isset($_POST['_user'], $_POST['_pass']))
            $args['host'] = $this->getHost(rcube_utils::get_input_value('_user', rcube_utils::INPUT_POST));

        return $args;
    }

    private function getHost($user)
    {
        $host = '';

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $user));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            if (count($mail_user) == 1) {
                $mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'server');
                $host = $this->rcmail->config->get('autoselect_con_type') . $mail_server['hostname'];
            }

            $this->soap->logout($session_id);
        }
        catch (SoapFault $e) {
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
        }

        return $host;
    }
}
