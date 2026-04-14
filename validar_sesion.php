<?php
/**
 * Validar Sesión - MediClick

 */

session_start();
require_once __DIR__ . '/config.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario'])) {
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        http_response_code(401);
        header('Content-Type: application/json');
        exit(json_encode(['exito' => false, 'mensaje' => 'Sesión expirada o no autenticado']));
    }
    header('Location: ' . SITE_URL . 'login/login.html');
    exit('No autenticado');
}

// Verificar timeout de sesión
$timeout = SESSION_TIMEOUT;
if (isset($_SESSION['fecha_inicio'])) {
    if (time() - $_SESSION['fecha_inicio'] > $timeout) {
        session_destroy();
        http_response_code(401);
        header('Location: ' . SITE_URL . 'login/login.html?error=sesion');
        exit('Sesión expirada');
    }
}

// Renovar tiempo de sesión
$_SESSION['fecha_inicio'] = time();

// Verificar que la IP no haya cambiado (seguridad adicional)
$currentIP = getClientIP();
$storedIP = $_SESSION['ip'] ?? '';

// Normalizar localhost para evitar problemas entre IPv4 e IPv6
const LOCAL_IPS = ['::1', '127.0.0.1', 'localhost'];
$isCurrentLocal = in_array($currentIP, LOCAL_IPS);
$isStoredLocal = in_array($storedIP, LOCAL_IPS);

if ($storedIP && $storedIP !== $currentIP && !($isCurrentLocal && $isStoredLocal)) {
    session_destroy();
    if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        http_response_code(401);
        exit(json_encode(['exito' => false, 'mensaje' => 'IP de sesión cambiada']));
    }
    header('Location: ' . SITE_URL . 'login/login.html?error=sesion');
    exit('Sesión inválida');
}

// Función para verificar rol
function requiereRol($rolesPermitidos) {
    if (!isset($_SESSION['rol'])) {
        http_response_code(403);
        die('Acceso denegado: Sin rol asignado');
    }
    
    if (!is_array($rolesPermitidos)) {
        $rolesPermitidos = array($rolesPermitidos);
    }
    
    if (!in_array($_SESSION['rol'], $rolesPermitidos)) {
        http_response_code(403);
        die('Acceso denegado: Rol insuficiente');
    }
}

// Función para obtener datos del usuario actual
function getUsuarioActual() {
    global $pdo;
    
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    
    $stmt = $pdo->prepare("
        SELECT u.*, e.sucursal 
        FROM usuarios u 
        LEFT JOIN empleados e ON u.id = e.usuario_id 
        WHERE u.id = :id
    ");
    
    $stmt->execute([':id' => $_SESSION['usuario_id']]);
    return $stmt->fetch();
}

?>
