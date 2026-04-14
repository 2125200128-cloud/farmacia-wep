<?php





define('DB_HOST', getenv('MYSQLHOST') ?: 'viaduct.proxy.rlwy.net');      
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');           
define('DB_USER', getenv('MYSQLUSER') ?: 'root');           
define('DB_PASS', getenv('MYSQLPASSWORD') ?: 'rclNByooYtLqkTnHFgfGwITfXXvsLovN');               
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');  




// Definir la URL base del sitio de forma dinámica (solo en contexto web)
if (isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = $_SERVER['SCRIPT_NAME'];
    $dir = str_replace('\\', '/', dirname(dirname($script_name)));
    if ($dir !== '/') $dir .= '/';
    define('SITE_URL', $protocol . "://" . $host . $dir);
} else {
    define('SITE_URL', 'http://localhost/proyectoCuatrimestral Farmacia/');
}
define('SITE_NAME', 'MediClick');
define('APP_PATH', dirname(__FILE__) . '/');




define('SESSION_TIMEOUT', 3600);  
define('REMEMBER_ME_DAYS', 7);     




define('HASH_ALGORITHM', 'bcrypt');
define('BCRYPT_COST', 10);
define('ENCRYPTION_KEY', 'tu_clave_secreta_aqui_cambiar_en_produccion');




define('TASA_IVA', 0.16);  




date_default_timezone_set('America/Mexico_City');




try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        )
    );
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}






function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}


function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}


function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}


function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}


function respondJSON($success, $message, $data = null, $code = 200) {
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}


function logEvent($table, $operation, $previousValues = null, $newValues = null, $userId = null) {
    global $pdo;
    
    if ($userId === null && isset($_SESSION['usuario_id'])) {
        $userId = $_SESSION['usuario_id'];
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria (usuario_id, tabla_afectada, tipo_operacion, valores_anteriores, valores_nuevos, direccion_ip)
            VALUES (:usuario_id, :tabla, :operacion, :anteriores, :nuevos, :ip)
        ");
        
        $stmt->execute([
            ':usuario_id' => $userId,
            ':tabla' => $table,
            ':operacion' => $operation,
            ':anteriores' => $previousValues ? json_encode($previousValues) : null,
            ':nuevos' => $newValues ? json_encode($newValues) : null,
            ':ip' => getClientIP()
        ]);
    } catch (PDOException $e) {
        error_log('Error en logEvent: ' . $e->getMessage());
    }
}



function registrarMovimientoInventario($productoId, $tipo, $cantidad, $stockAnterior, $stockNuevo) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO movimiento_inventario (
                producto_id, tipo_movimiento, cantidad,
                stock_anterior, stock_nuevo, usuario_id
            ) VALUES (
                :producto_id, :tipo, :cantidad,
                :stock_anterior, :stock_nuevo, :usuario_id
            )
        ");
        $stmt->execute([
            ':producto_id'   => $productoId,
            ':tipo'          => $tipo,
            ':cantidad'      => $cantidad,
            ':stock_anterior'=> $stockAnterior,
            ':stock_nuevo'   => $stockNuevo,
            ':usuario_id'    => $_SESSION['usuario_id'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log('Error registrando movimiento: ' . $e->getMessage());
    }
}

?>
