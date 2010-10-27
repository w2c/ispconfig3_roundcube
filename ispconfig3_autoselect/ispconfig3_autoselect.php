<?php
/**
* ISPConfig 3 Autoselect Host
*
* Make use of the ISPConfig 3 remote library to select the corresponding Host
*
* @author Horst Fickel ( web-wack.at )
*/

class ispconfig3_autoselect extends rcube_plugin
{
	public $task = 'login|mail|logout';
	private $soap = NULL;
	private $rcmail_inst = NULL;

	function init()
	{
		$this->rcmail_inst = rcmail::get_instance();
		$this->load_config();
		$this->load_con_config();
		$this->soap = new SoapClient(null, array('location' => $this->rcmail_inst->config->get('soap_url').'index.php',
									'uri'      => $this->rcmail_inst->config->get('soap_url')));
									
		$this->add_hook('startup', array($this, 'startup'));
		$this->add_hook('authenticate', array($this, 'authenticate'));
		$this->add_hook('template_object_loginform', array($this, 'template_object_loginform'));
	}
	
	function load_config()
	{
		$config = $this->home.'/config/config.inc.php';
		if(file_exists($config))
		{
			if(!$this->rcmail_inst->config->load_from_file($config))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config . ".dist"))
		{
			if(!$this->rcmail_inst->config->load_from_file($config . '.dist'))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
	}
	
	function load_con_config()
	{
		$config = $this->api->dir.'ispconfig3_account/config/config.inc.php';
		if(file_exists($config))
		{
			if(!$this->rcmail_inst->config->load_from_file($config))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
		else if(file_exists($config . ".dist"))
		{
			if(!$this->rcmail_inst->config->load_from_file($config . '.dist'))
     			raise_error(array('code' => 527, 'type' => 'php', 'message' => "Failed to load config from $config"), true, false);		
		}
	}

	function startup($args)
	{
		if (empty($args['action']) && empty($_SESSION['user_id']) && !empty($_POST['_user']) && !empty($_POST['_pass']))
			$args['action'] = 'login';
			
		return $args;
	}

	function template_object_loginform($args)
	{
		$args['content'] = str_replace("<tr><td class=\"title\"><label for=\"rcmloginhost\">Server</label>\n</td>\n<td><input name=\"_host\" id=\"rcmloginhost\" autocomplete=\"off\" type=\"text\" /></td>\n</tr>","",$args['content']);

		return $args;
	}

	function authenticate($args)
	{
		if(isset($_POST['_user']) && isset($_POST['_pass']))  
			$args['host'] = $this->getHost(get_input_value('_user', RCUBE_INPUT_POST));

		return $args;
	}

	private function getHost($user)
	{
		$host = '';

		try
		{
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'),$this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('email' => $user));  

			if(count($mail_user) == 1)
			{
				$mail_server = $this->soap->server_get($session_id, $mail_user[0]['server_id'], 'server');
				$host = $this->rcmail_inst->config->get('autoselect_con_type').$mail_server['hostname'];
			}

			$this->soap->logout($session_id);
		}
		catch (SoapFault $e)
		{
			$this->rcmail_inst->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
		}

		return $host;
	}
}
?>