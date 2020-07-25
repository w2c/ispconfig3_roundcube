<?php
class ispconfig3_account extends rcube_plugin
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

        $this->load_config('config/config.inc.php.dist');
        if (file_exists($this->home . '/config/config.inc.php')) {
            $this->load_config('config/config.inc.php');
        }

        $this->register_action('plugin.ispconfig3_account', array($this, 'init_html'));
        $this->register_action('plugin.ispconfig3_account.show', array($this, 'init_html'));

        $this->add_hook('settings_actions', array($this, 'settings_actions'));
        $this->add_hook('template_object_identityform', array($this, 'template_object_identityform'));

        $this->include_script('account.js');
        $this->include_stylesheet($this->local_skin_path() . '/account.css');

        if (strpos($this->rcmail->action, 'plugin.ispconfig3_account') === 0 ||
            ($this->rcmail->config->get('identity_limit') === true &&
                (strpos($this->rcmail->action, 'edit-identity') === 0 ||
                 strpos($this->rcmail->action, 'add-identity') === 0))) {

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

     /**
     * Handler for settings_actions hook.
     * Adds ispconfig3_account settings section into preferences.
     */
    function settings_actions($args)
    {
        $args['actions'][] = array(
            'action' => 'plugin.ispconfig3_account',
            'class'  => 'account',
            'label'  => 'acc_acc',
            'title'  => 'acc_acc',
            'domain' => 'ispconfig3_account',
        );

        return $args;
    }

    function init_html()
    {
        $this->api->output->set_pagetitle($this->gettext('acc_acc'));
        if (rcmail::get_instance()->action == 'plugin.ispconfig3_account.show') {
            // General account info
            $this->api->output->add_handler('info', array($this, 'gen_form'));
            $this->api->output->add_handler('sectionname_acc', array($this, 'prefs_section_name'));
            $this->api->output->send('ispconfig3_account.general');
        }
        else {
            // Plugin overview
            $this->api->output->add_handler('accountlist', array($this, 'accountlist'));
            $this->api->output->add_handler('accountframe', array($this, 'accountframe'));
            $this->api->output->send('ispconfig3_account.account');
        }
    }

    /**
     * Handler for identity_form hook.
     * Replaces the text input field for an email address with a select dropdown (Identities -> Edit).
     */
    function template_object_identityform($args)
    {
        if ($this->rcmail->config->get('identity_limit') === true) {
            $emails = new html_select(array('name' => '_email', 'id' => 'rcmfd_email', 'class' => 'ff_email'));
            try {
                $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
                $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
                // Alternatively also search the email field, this can differ from the login field for legacy reasons.
                if (empty($mail_user)) {
                    $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
                }

                $alias = $this->soap->mail_alias_get($session_id, array('destination' => $mail_user[0]['email'], 'type' => 'alias', 'active' => 'y'));
                $this->soap->logout($session_id);

                $emails->add($mail_user[0]['email'], $mail_user[0]['email']);
                for ($i = 0; $i < count($alias); $i++) {
                    $emails->add($alias[$i]['source'], $alias[$i]['source']);
                }
            }
            catch (SoapFault $e) {
                $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
                $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
            }

            $email_pattern = '/<input type=\"text\" size=\"40\" id=\"rcmfd_email\" name=\"_email\" class=\"ff_email\"(?: value=\"(.*)\")?>/';
            preg_match($email_pattern, $args['content'], $test);
            $email = isset($test[1]) ? $test[1] : '';
            $args['content'] = preg_replace($email_pattern, $emails->show($email), $args['content']);
        }

        return $args;
    }

    function accountframe($attrib)
    {
        if (!$attrib['id'])
            $attrib['id'] = 'rcmaccountframe';

        $attrib['name'] = $attrib['id'];

        $this->api->output->set_env('contentframe', $attrib['name']);
        $this->api->output->set_env('blankpage', $attrib['src'] ? $this->api->output->abs_url($attrib['src']) : 'about:blank');

        return html::iframe($attrib);
    }

    function accountlist($attrib)
    {
        if (!$attrib['id'])
            $attrib['id'] = 'rcmaccountlist';

        $sectionavail = array('general'   => array('id' => 'general', 'section' => $this->gettext('acc_general')),
                              'pass'      => array('id' => 'pass', 'section' => $this->gettext('acc_pass')),
                              'fetchmail' => array('id' => 'fetchmail', 'section' => $this->gettext('acc_fetchmail')),
                              'forward'   => array('id' => 'forward', 'section' => $this->gettext('acc_forward')),
                              'autoreply' => array('id' => 'autoreply', 'section' => $this->gettext('acc_autoreply')),
                              'filter'    => array('id' => 'filter', 'section' => $this->gettext('acc_filter')),
                              'wblist'    => array('id' => 'wblist', 'section' => $this->gettext('acc_wblist')),
                              'spam'      => array('id' => 'spam', 'section' => $this->gettext('acc_spam')));

        $array = array('general');
        $plugins = $this->rcmail->config->get('plugins');
        $plugins = array_flip($plugins);
        if (isset($plugins['ispconfig3_pass']))
            $array[] = 'pass';
        if (isset($plugins['ispconfig3_fetchmail']))
            $array[] = 'fetchmail';
        if (isset($plugins['ispconfig3_forward']))
            $array[] = 'forward';
        if (isset($plugins['ispconfig3_autoreply']))
            $array[] = 'autoreply';
        if (isset($plugins['ispconfig3_filter']))
            $array[] = 'filter';
        if (isset($plugins['ispconfig3_wblist']))
            $array[] = 'wblist';
        if (isset($plugins['ispconfig3_spam']))
            $array[] = 'spam';

        $blocks = $array;
        if (isset($attrib['sections'])) {
            $quotes_stripped = str_replace(array("'", '"'), '', $attrib['sections']);
            $blocks = preg_split('/[\s,;]+/', $quotes_stripped);
        }
        $sections = array();
        foreach ($blocks as $block) {
            $sections[$block] = $sectionavail[$block];
        }

        $out = $this->rc->table_output($attrib, $sections, array('section'), 'id');
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
        $out = '<form class="propform"><fieldset><legend>' . $this->gettext('acc_general') . '</legend>' . "\n";

        $table = new html_table(array('cols' => 2, 'cellpadding' => 3, 'class' => 'propform'));
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

        $out .= '<fieldset><legend>' . $this->gettext('acc_alias') . '</legend>' . "\n";

        $alias_table = new html_table(array('id' => 'alias-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 1));
        $alias_table->add_header(array('width' => '100%'), $this->gettext('mail'));

        try {
            $session_id = $this->soap->login($this->rcmail->config->get('remote_soap_user'), $this->rcmail->config->get('remote_soap_pass'));
            $mail_user = $this->soap->mail_user_get($session_id, array('login' => $this->rcmail->user->data['username']));
            // Alternatively also search the email field, this can differ from the login field for legacy reasons.
            if (empty($mail_user)) {
                $mail_user = $this->soap->mail_user_get($session_id, array('email' => $this->rcmail->user->data['username']));
            }

            $alias = $this->soap->mail_alias_get($session_id, array('destination' => $mail_user[0]['email'], 'type' => 'alias', 'active' => 'y'));
            $this->soap->logout($session_id);

            $alias_table->add('', $mail_user[0]['email']);
            for ($i = 0; $i < count($alias); $i++) {
                $alias_table->add('', $alias[$i]['source']);
            }
        }
        catch (SoapFault $e) {
            $error = $this->rc->text_exists($e->getMessage(), $this->ID) ? $this->gettext($e->getMessage()) : $e->getMessage();
            $this->rcmail->output->command('display_message', 'Soap Error: ' . $error, 'error');
        }

        $out .= "<div id=\"alias-cont\">" . $alias_table->show() . "</div>\n";
        $out .= "</fieldset></form>\n";

        return $out;
    }
}
