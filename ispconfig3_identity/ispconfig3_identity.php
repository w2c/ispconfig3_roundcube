<?php

class ispconfig3_identity extends rcube_plugin
{
    public $task = 'login';
    private $rcmail;
    private $rc;
    private $mail_user;

    private $soap;


    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rc = rcube::get_instance();

        $this->require_plugin('ispconfig3_account');

        $this->load_config('config/config.inc.php.dist');
        if (file_exists($this->home . '/config/config.inc.php')) {
            $this->load_config('config/config.inc.php');
        }

        $this->soap = new SoapClient(null, [
            'location' => $this->rcmail->config->get('soap_url') . 'index.php',
            'uri' => $this->rcmail->config->get('soap_url'),
            $this->rcmail->config->get('soap_validate_cert') ?:
                'stream_context' => stream_context_create(['ssl' => [
                    'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true
                ]])
        ]);

        $this->add_hook('login_after', [$this, 'set_identity']);
    }

    private function remoteGetUser()
    {
        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            // Search by the login
            $this->mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons
            if (empty($this->mail_user)) {
                $this->mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }
            
            $this->soap->logout($session_id);
            // Still not set a user, return false
            return (empty($this->mail_user)) ? false : true;
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }
    }

    /* 
    *  Funciton to set the identitiy that matches the e-mail address
    */
    function set_identity()
    {
        if ($this->remoteGetUser()) {
            $identities = $this->rc->user->list_identities();
            // Loop through identities to find the one corrisponding to the mailbox email
            foreach ($identities as $identity) {
                // Identitiy found
                if ($identity['email'] == $this->rcmail->user->data['username'] && $this->mail_user[0]['email'] === $this->rcmail->user->data['username']) {
                    // The below by default will set once at initial login, this allows users to then change later
                    // If setting force_name_update to true will update to match ISPConfig every login
                    if ($identity['name'] == "" || ($this->rcmail->config->get('force_name_update') && $identity['name'] != $this->mail_user[0]['name'])) {
                        $update = ["name" => $this->mail_user[0]['name']];
                        $this->rc->user->update_identity($identity['identity_id'], $update);
                    }
                }
            }
        }
    }
}
