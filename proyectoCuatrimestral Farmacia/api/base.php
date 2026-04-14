<?php


ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/validar_sesion.php';


$metodo = $_SERVER['REQUEST_METHOD'];


$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);


if (empty($input)) {
    $input = $_POST ?? [];
}


function enviarRespuesta($exito, $mensaje, $datos = null, $codigoHTTP = 200) {
    http_response_code($codigoHTTP);
    echo json_encode([
        'exito' => $exito,
        'mensaje' => $mensaje,
        'datos' => $datos
    ]);
    exit();
}


function enviarError($mensaje, $codigoHTTP = 400) {
    enviarRespuesta(false, $mensaje, null, $codigoHTTP);
}


function enviarExito($mensaje, $datos = null) {
    enviarRespuesta(true, $mensaje, $datos, 200);
}

?>
