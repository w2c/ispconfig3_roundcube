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

  function init()
  {
    $this->_load_config();
    $this->add_hook('startup', array($this, 'startup'));
    $this->add_hook('authenticate', array($this, 'authenticate'));
    $this->add_hook('template_object_loginform', array($this, 'template_object_loginform'));
  }

  function startup($args)
  {
    if (empty($args['action']) && empty($_SESSION['user_id']) && !empty($_POST['_user']) && !empty($_POST['_pass']))
    {
      $args['action'] = 'login';
    }
    return $args;
  }
  
  function template_object_loginform($args)
  {
    $args['content'] = substr($args['content'], 0, 545).substr($args['content'], 701);

    return $args;
  }

  function authenticate($args)
  {
    if(isset($_POST['_user']) && isset($_POST['_pass'])){	  
	    $args['host'] = $this->getHost(get_input_value('_user', RCUBE_INPUT_POST));
    }
  
    return $args;
  }

  function getHost($user)
  {
    $rcmail = rcmail::get_instance();
    $host = '';
    $client = new SoapClient(null, array('location' => $rcmail->config->get('soap_url').'index.php',
                             'uri'      => $rcmail->config->get('soap_url')));
    try {
      $session_id = $client->login($rcmail->config->get('remote_soap_user'),$rcmail->config->get('remote_soap_pass'));
      $mail_user = $client->mail_user_get($session_id, array('email' => $user));  
      
      if(count($mail_user) == 1)
      {
        $mail_server = $client->server_get_name($session_id, $mail_user[0]['server_id']);
        $host = $mail_server;
      }
      
      $client->logout($session_id);
    } catch (SoapFault $e) {
      $rcmail->output->command('display_message', 'Soap Error: '.$e->getMessage(), 'error');
    }
    
    return $host;
  }
  
  function _load_config()
  {
    $rcmail = rcmail::get_instance();
    $config_1 = "plugins/ispconfig3_autoselect/config/config.inc.php";
    $config_2 = "plugins/ispconfig3_account/config/config.inc.php";
    if(file_exists($config_1))
      include $config_1;
    else if(file_exists($config_2))
      include $config_2;
    else if(file_exists($config_1 . ".dist"))
      include $config_1 . ".dist";
    else if(file_exists($config_2 . ".dist"))
      include $config_2 . ".dist";
    if(is_array($rcmail_config)){
      $arr = array_merge($rcmail->config->all(),$rcmail_config);
      $rcmail->config->merge($arr);
    }
  } 
}
?>