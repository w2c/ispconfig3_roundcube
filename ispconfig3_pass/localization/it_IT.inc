<?php

$labels = array();
$labels['password'] = 'Password';
$labels['changepasswd']  = 'Modifica la Password';
$labels['curpasswd']  = 'Password attuale:';
$labels['newpasswd']  = 'Nuova Password:';
$labels['confpasswd']  = 'Nuova Password (conferma):';

$messages = array();
$messages['nopassword'] = 'Prego, inserire una nuova password.';
$messages['nocurpassword'] = 'Prego, inserire la password attuale.';
$messages['passwordincorrect'] = 'La password attuale è errata.';
$messages['passwordinconsistency'] = 'Le nuova password non è stata inserita correttamente.<br>Prego, riprova.';
$messages['internalerror'] = 'L\'aggiornamento della password non è univoco.<br>Questa è una situazione anomala.<br>Contatta il responsabile del servizo di posta.';
$messages['errorsaving'] = 'L\'aggiornamento della password è fallito.<br>Contatta il responsabile del servizo di posta.';
$messages['passwordminlength'] = 'La password inserita è troppo corta: sono richiesti almeno %d caratteri.';
?>