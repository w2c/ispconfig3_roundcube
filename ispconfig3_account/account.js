if (window.rcmail) {
  rcmail.addEventListener('init', function (evt) {

    var tab = $('<span>').attr('id', 'settingstabpluginispconfig3_account').addClass('tablink account');
    var button = $('<a>').attr('href', rcmail.env.comm_path + '&_action=plugin.ispconfig3_account')
      .attr('title', rcmail.gettext('acc_acc', 'ispconfig3_account'))
      .html(rcmail.gettext('acc_acc', 'ispconfig3_account'))
      .bind('click', function (e) {
        return rcmail.command('plugin.ispconfig3_account', this)
      })
      .appendTo(tab);

    // add button and register commands
    rcmail.add_element(tab, 'tabs');
    rcmail.register_command('plugin.ispconfig3_account', function () {
      rcmail.goto_url('plugin.ispconfig3_account')
    }, true);

    if (rcmail.env.action == 'plugin.ispconfig3_account') {

      if (rcmail.gui_objects.accountlist) {
        rcmail.account_list = new rcube_list_widget(rcmail.gui_objects.accountlist, {multiselect: false, draggable: false, keyboard: false});
        rcmail.account_list.addEventListener('select', function (o) {
          rcmail.account_select(o);
        });
        rcmail.account_list.init();
        rcmail.account_list.focus();
        rcmail.account_list.select_row('general');
      }
    }
  });
}
;

rcube_webmail.prototype.account_select = function (list) {
  var id = list.get_single_selection(), add_url = '', target = window;

  if (id) {
    if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {

      add_url = '&_framed=1';
      target = window.frames[this.env.contentframe];
    }

    if (id == 'general') {
      id = 'account.show';
    }

    rcmail.location_href(this.env.comm_path + '&_action=plugin.ispconfig3_' + id + add_url, target, true);
  }

  return true;
};