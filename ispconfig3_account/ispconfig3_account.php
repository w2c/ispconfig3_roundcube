<?php

class ispconfig3_account extends rcube_plugin
{
    public $task = 'settings';
    private $rcmail;
    private $rc;

    private $soap;
    private $mail_user;
    private $aliases;

    const ISPCONFIG_PLUGINS = [ 'account', 'pass', 'fetchmail', 'forward', 'autoreply', 'filter', 'wblist', 'spam' ];

    function init()
    {
        $this->rcmail = rcmail::get_instance();
        $this->rc = rcube::get_instance();
        $this->add_texts('localization/');

        $this->load_config('config/config.inc.php.dist');
        if (file_exists($this->home . '/config/config.inc.php')) {
            $this->load_config('config/config.inc.php');
        }

        $this->register_action('plugin.ispconfig3_account', [$this, 'init_html']);
        $this->register_action('plugin.ispconfig3_account.show', [$this, 'init_html']);

        $this->add_hook('settings_actions', [$this, 'settings_actions']);
        if ($this->rcmail->config->get('identity_limit') === true) {
            $this->add_hook('template_object_identityform', [$this, 'template_object_identityform']);
        }

        $this->include_script('account.js');
        $this->include_stylesheet($this->local_skin_path() . '/account.css');

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_account') === 0 ||
            ($this->rcmail->config->get('identity_limit') === true &&
                (strpos($this->rcmail->action, 'edit-identity') === 0 ||
                 strpos($this->rcmail->action, 'add-identity') === 0 ||
                 strpos($this->rcmail->action, 'save-identity') === 0))) {

            $this->soap = new SoapClient(null, [
                'location' => $this->rcmail->config->get('soap_url') . 'index.php',
                'uri' => $this->rcmail->config->get('soap_url'),
                $this->rcmail->config->get('soap_validate_cert') ?:
                    'stream_context' => stream_context_create(['ssl' => [
                        'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true
                    ]])
            ]);
        }
    }

     /**
      * Handler for settings_actions hook.
      *
      * Adds ispconfig3_account settings section into preferences.
      */
    function settings_actions($args)
    {
        $args['actions'][] = [
            'action' => 'plugin.ispconfig3_account',
            'class'  => 'account',
            'label'  => 'acc_acc',
            'title'  => 'acc_acc',
            'domain' => 'ispconfig3_account',
        ];

        return $args;
    }

    function init_html()
    {
        $this->api->output->set_pagetitle($this->gettext('acc_acc'));
        if (rcmail::get_instance()->action == 'plugin.ispconfig3_account.show') {
            // General account info
            $this->api->output->add_handler('info', [$this, 'gen_form']);
            $this->api->output->add_handler('sectionname_acc', [$this, 'prefs_section_name']);
            $this->api->output->send('ispconfig3_account.general');
        }
        else {
            // Plugin overview
            $this->api->output->add_handler('accountlist', [$this, 'accountlist']);
            $this->api->output->add_handler('accountframe', [$this, 'accountframe']);
            $this->api->output->send('ispconfig3_account.account');
        }
    }

    /**
     * Handler for identity_form hook.
     *
     * Replaces the text input field for an email address with a select dropdown (Identities -> Edit).
     */
    function template_object_identityform($args)
    {
        $emails = new html_select(['name' => '_email', 'id' => 'rcmfd_email', 'class' => 'ff_email']);

        $this->remoteGetUserAndAliases();
        if ($this->mail_user) {
            $emails->add($this->mail_user[0]['email'], $this->mail_user[0]['email']);
        }
        foreach ((array) $this->aliases as $alias) {
            $emails->add($alias['source'], $alias['source']);
        }

        $email_pattern = '/<input type=\"text\" size=\"40\" id=\"rcmfd_email\" name=\"_email\" class=\"ff_email\"(?: value=\"(.*)\")?>/U';
        preg_match($email_pattern, $args['content'], $test);
        $email = isset($test[1]) ? $test[1] : '';
        $args['content'] = preg_replace($email_pattern, $emails->show($email), $args['content']);

        return $args;
    }

    function accountframe($attrib)
    {
        if (empty($attrib['id'])) {
            $attrib['id'] = 'rcmaccountframe';
        }

        $attrib['name'] = $attrib['id'];

        $this->api->output->set_env('contentframe', $attrib['name']);
        $this->api->output->set_env('blankpage', $attrib['src'] ? $this->api->output->abs_url($attrib['src']) : 'about:blank');

        return html::iframe($attrib);
    }

    function accountlist($attrib)
    {
        if (empty($attrib['id'])) {
            $attrib['id'] = 'rcmaccountlist';
        }

        $rc_plugins = $this->rcmail->config->get('plugins');
        $rc_plugins = array_flip($rc_plugins);

        $sections = [];
        foreach ($this::ISPCONFIG_PLUGINS as $plugin) {
            if (isset($rc_plugins['ispconfig3_' . $plugin])) {
                if ($plugin == 'account') {
                    $plugin = 'general';
                }

                $sections[$plugin] = ['id' => $plugin, 'section' => $this->gettext('acc_' . $plugin)];
            }
        }

        $out = $this->rc->table_output($attrib, $sections, ['section'], 'id');
        $this->rcmail->output->add_gui_object('accountlist', $attrib['id']);
        $this->rcmail->output->include_script('list.js');

        return $out;
    }

    function prefs_section_name()
    {
        return $this->gettext('acc_general');
    }

    function gen_form()
    {
        // General
        $out = '<fieldset><legend>' . $this->gettext('acc_general') . '</legend>' . "\n";
        $table = new html_table(['cols' => 2, 'cellpadding' => 3, 'class' => 'propform']);

        $table->add('title', rcube::Q($this->gettext('username')));
        $table->add('', rcube::Q($this->rcmail->user->data['username']));

        $table->add('title', rcube::Q($this->gettext('server')));
        $table->add('', rcube::Q($this->rcmail->user->data['mail_host']));

        $table->add('title', rcube::Q($this->gettext('acc_lastlogin')));
        $table->add('', rcube::Q($this->rcmail->format_date($this->rcmail->user->data['last_login'])));

        $identity = $this->rcmail->user->get_identity();
        $table->add('title', rcube::Q($this->gettext('acc_defaultidentity')));
        $table->add('', rcube::Q($identity['name'] . ' <' . $identity['email'] . '>'));

        $out .= $table->show();
        $out .= "</fieldset>\n";

        // Linked email address(es)
        $out .= '<fieldset><legend>' . $this->gettext('acc_alias') . '</legend>' . "\n";
        $alias_table = new html_table(['id' => 'alias-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 1]);
        $alias_table->add_header(['width' => '100%'], $this->gettext('mail'));

        $this->remoteGetUserAndAliases();
        if ($this->mail_user) {
            $alias_table->add('', $this->mail_user[0]['email']);
        }
        foreach ((array) $this->aliases as $alias) {
            $alias_table->add('', $alias['source']);
        }

        $out .= "<div id=\"alias-cont\">" . $alias_table->show() . "</div>\n";
        $out .= "</fieldset>\n";

        return $out;
    }

    private function remoteGetUserAndAliases()
    {
        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));

            $this->mail_user = $this->soap->mail_user_get($session_id, ['login' => $this->rcmail->user->data['username']]);
            // Alternatively also search the email field, this can differ from the login field for legacy reasons
            if (empty($this->mail_user)) {
                $this->mail_user = $this->soap->mail_user_get($session_id, ['email' => $this->rcmail->user->data['username']]);
            }

            $this->aliases = $this->soap->mail_alias_get($session_id,
                ['destination' => $this->mail_user[0]['email'], 'type' => 'alias', 'active' => 'y']);

            $this->soap->logout($session_id);
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }
    }
}
