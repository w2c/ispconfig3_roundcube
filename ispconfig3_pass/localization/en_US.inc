<?php
$labels = array();
$labels['password'] = 'Password';
$labels['changepasswd'] = 'Change Password';
$labels['curpasswd'] = 'Current Password:';
$labels['newpasswd'] = 'New Password:';
$labels['confpasswd'] = 'Confirm New Password:';

$messages = array();
$messages['nopassword'] = 'Please input new password.';
$messages['nocurpassword'] = 'Please input current password.';
$messages['passwordincorrect'] = 'Current password is incorrect.';
$messages['passwordinconsistency'] = 'Inconsistency of password, please try again.';
$messages['passwordminlength'] = 'Password is too short: %d digits required.';
$messages['passwordchecknumber'] = 'Password must include at least one number.';
$messages['passwordchecklower'] = 'Password must include at least one lower case letter.';
$messages['passwordcheckupper'] = 'Password must include at least one upper case letter.';
$messages['passwordchecksymbol'] = 'Password must include at least one symbol.';
?>