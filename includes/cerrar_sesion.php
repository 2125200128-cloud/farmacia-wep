<?php


session_start();
require_once 'config.php';


if (isset($_SESSION['usuario_id'])) {
    logEvent('usuarios', 'LOGOUT', null, null, $_SESSION['usuario_id']);
}


session_destroy();


header('Location: login/login.html');
exit();

?>
