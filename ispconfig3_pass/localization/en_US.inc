<?php

$labels = array();
$labels['password'] = 'Password';
$labels['changepasswd']  = 'Change Password';
$labels['curpasswd']  = 'Current Password:';
$labels['newpasswd']  = 'New Password:';
$labels['confpasswd']  = 'Confirm New Password:';

$messages = array();
$messages['nopassword'] = 'Please input new password.';
$messages['nocurpassword'] = 'Please input current password.';
$messages['passwordincorrect'] = 'Current password is incorrect.';
$messages['passwordinconsistency'] = 'Inconsistency of password, please try again.';
$messages['internalerror'] = 'The server is updated more than one row in the database. This could be bad for all users. Contact your system adminstrator.';
$messages['errorsaving'] = 'Could not save your new password to the database. Contact your system adminstrator.';
$messages['passwordminlength'] = 'Password is too short: %d digits required.';
?>