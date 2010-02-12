<?php

$labels = array();
$labels['password'] = 'Sprememba gesla';
$labels['changepasswd']  = 'Spremeni geslo';
$labels['curpasswd']  = 'Trenutno geslo:';
$labels['newpasswd']  = 'Novo geslo:';
$labels['confpasswd']  = 'Potrditev novega gesla:';

$messages = array();
$messages['nopassword'] = 'Prosimo, vnesite novo geslo.';
$messages['nocurpassword'] = 'Prosimo, vnesite trenutno geslo.';
$messages['passwordincorrect'] = 'Trenutno geslo ni pravilno.';
$messages['passwordinconsistency'] = 'Gesla se ne ujemata, poizkusite znova.';
$messages['internalerror'] = 'The server is updated more than one row in the database. This could be bad for all users. Contact your system adminstrator.';
$messages['errorsaving'] = 'Could not save your new password to the database. Contact your system adminstrator.';
$messages['passwordminlength'] = 'Geslo je prekratko: vsaj %d znakov.';
?>