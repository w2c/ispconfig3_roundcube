<?php

$labels = array();
$labels['password'] = 'Contrase&ntilde;a';
$labels['changepasswd']  = 'Cambio Contrase&ntilde;a';
$labels['curpasswd']  = 'Contrase&ntilde;a Actual';
$labels['newpasswd']  = 'Nueva Contrase&ntilde;a:';
$labels['confpasswd']  = 'Confirmaci&oacute;n de Contrase&ntilde;a:';

$messages = array();
$messages['nopassword'] = 'Favor escriba la nueva contrase&ntilde;a.';
$messages['nocurpassword'] = 'Favor escriba la contrase&ntilde;a actual.';
$messages['passwordincorrect'] = 'Contrase&ntilde;a actual esta incorrecta.';
$messages['passwordinconsistency'] = 'Inconsistencia de las contrase&ntilde;a, intente nuevamente.';
$messages['internalerror'] = 'El servidor actualizo m&aacute;s de una fila en la base de datos. Esto puede ser un problema para todos los usuarios. Favor contactar al administrador del sistema.';
$messages['errorsaving'] = 'No se pudo guardar la nueva contrase&ntilde;a en la base de datos. Favor contactar al administrador del sistema.';
$messages['passwordminlength'] = 'La contrase&ntilde;a es muy corta: %d digitos requeridos.';
?>