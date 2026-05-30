<?php
class ispconfig3_user_identity extends rcube_plugin {

	public $task = 'login';

	private $rcmail_inst;
	private $rcube_inst;
	private $soap;

	function init() {
		$this->rcmail_inst = rcmail::get_instance();
		$this->rcube_inst = rcube::get_instance();
		$this->load_config();

		$this->require_plugin('ispconfig3_account');

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

		$this->add_hook('login_after', array($this, 'ispconfig_update_user'));
	}


	function load_config($fname = 'config.inc.php') {
		$config = $this->home . '/config/' . $fname;
		if (file_exists($config)) {
			if (!$this->rcmail_inst->config->load_from_file($config))
				rcube::raise_error(array('code' => 527, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Failed to load config from $config"), true, false);
		} else if (file_exists($config . ".dist")) {
			if (!$this->rcmail_inst->config->load_from_file($config . '.dist'))
				rcube::raise_error(array('code' => 527, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__, 'message' => "Failed to load config from $config"), true, false);
		}
	}


	function ispconfig_update_user($args) {
		$user = rcmail::get_instance()->user;

		$identity = $user->get_identity();
		$username = $user->get_username();

		try {
			$session_id = $this->soap->login($this->rcmail_inst->config->get('remote_soap_user'), $this->rcmail_inst->config->get('remote_soap_pass'));
			$mail_user = $this->soap->mail_user_get($session_id, array('login' => $username));
			$this->soap->logout($session_id);

			$identity['name'] = $mail_user[0]['name'];
			$identity['email'] = $mail_user[0]['email'];

		} catch (SoapFault $e) {
			$this->rcmail_inst->output->command('display_message', 'Soap Error: ' . $e->getMessage(), 'error');
		}

		$user->update_identity($identity['identity_id'],$identity);
		return $args;
	}
}
