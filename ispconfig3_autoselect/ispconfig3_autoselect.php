<?php
class ispconfig3_autoselect extends rcube_plugin
{
    public $task = 'login|logout';
    private $soap;
    private $rcmail_inst;

    function init()
    {
        $this->rcmail_inst = rcmail::get_instance();
        $this->load_config();
        $this->load_con_config();

        $this->soap = new SoapClient(null, array(
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

        $this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('template_object_loginform', array($this, 'template_object_loginform'));
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

    function load_con_config()
    {
        $config = $this->api->dir . 'ispconfig3_account/config/config.inc.php';
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

        try
        {
            $session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $user));

            if (count($mail_user) == 1)
            {
                $mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'server');
                $host = $this->rcmail_inst->config->get('autoselect_con_type') . $mail_server['hostname'];
            }

            $this->soap->logout($session_id);
        } catch (SoapFault $e)
        {
            $this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
        }

        return $host;
    }
}
