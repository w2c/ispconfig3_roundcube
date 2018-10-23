window.rcmail && rcmail.addEventListener('init', function (evt) {
    rcmail.register_command('plugin.ispconfig3_account', function () {
        rcmail.goto_url('plugin.ispconfig3_account')
    }, true);

    if (rcmail.env.action.startsWith('plugin.ispconfig3_account')) {
        if (rcmail.gui_objects.accountlist) {
            rcmail.account_list = new rcube_list_widget(rcmail.gui_objects.accountlist,
                {multiselect: false, draggable: false, keyboard: true});

            rcmail.account_list
                .addEventListener('select', function (o) { rcmail.plugin_select(o); })
                .init()
                .focus();

            //rcmail.account_list.select_row('general');
        }
    }

    // Compat shim for rcmail.confirm_dialog - undefined in Roundcube <= 1.4
    if (!rcmail.confirm_dialog) {
        rcmail.confirm_dialog = function(content, button_label, action) {
            if (confirm(content)) { action(this, rcmail); }
        }
    }
});

rcube_webmail.prototype.plugin_select = function (list) {
    var id = list.get_single_selection(), add_url = '', target = window;

    if (id) {
        if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
            add_url = '&_framed=1';
            target = window.frames[this.env.contentframe];
        }

        if (id === 'general') {
            id = 'account.show';
        }

        rcmail.location_href(this.env.comm_path + '&_action=plugin.ispconfig3_' + id + add_url, target, true);
    }

    return true;
};