<?php

session_start();
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$usuario = isset($_POST['usuario']) ? sanitize($_POST['usuario']) : '';
$contrasena = isset($_POST['contrasena']) ? $_POST['contrasena'] : '';

if (empty($usuario) || empty($contrasena)) {
    header('Location: index.php?error=campos');
    exit();
}

try {
    // Obtener usuario
    $stmt = $pdo->prepare("
        SELECT id, usuario, contrasena, nombre, rol, estado, ultimo_acceso 
        FROM usuarios 
        WHERE usuario = :usuario 
        LIMIT 1
    ");
    
    $stmt->execute([':usuario' => $usuario]);
    $usuarioData = $stmt->fetch();
    
    // Usuario no existe
    if (!$usuarioData) {
        header('Location: index.php?error=credenciales');
        exit();
    }
    
    // Usuario inactivo
    if ($usuarioData['estado'] !== 'activo') {
        header('Location: index.php?error=sesion');
        exit();
    }
    
    // Verificar contraseña (texto plano o hasheada)
    $contrasena_valida = false;
    if (password_get_info($usuarioData['contrasena'])['algo'] !== null) {
        // Es una contraseña hasheada
        $contrasena_valida = password_verify($contrasena, $usuarioData['contrasena']);
    } else {
        // Es texto plano (para compatibilidad con datos existentes)
        $contrasena_valida = ($contrasena === $usuarioData['contrasena']);
    }
    
    if (!$contrasena_valida) {
        header('Location: index.php?error=credenciales');
        exit();
    }
    
    // Validar rol
    if (empty($usuarioData['rol'])) {
        header('Location: index.php?error=rol');
        exit();
    }
    
    $stmtUpdate = $pdo->prepare("
        UPDATE usuarios 
        SET ultimo_acceso = NOW() 
        WHERE id = :id
    ");
    $stmtUpdate->execute([':id' => $usuarioData['id']]);

    // Registrar asistencia del día (si usa el sistema)
    $stmtAsistencia = $pdo->prepare("
        INSERT IGNORE INTO asistencia (usuario_id, fecha, hora_entrada) 
        VALUES (:id, CURDATE(), NOW())
    ");
    $stmtAsistencia->execute([':id' => $usuarioData['id']]);
    
    // Crear sesión
    $_SESSION['usuario_id'] = $usuarioData['id'];
    $_SESSION['usuario'] = $usuarioData['usuario'];
    $_SESSION['nombre'] = $usuarioData['nombre'];
    $_SESSION['rol'] = $usuarioData['rol'];
    $_SESSION['ip'] = getClientIP();
    $_SESSION['fecha_inicio'] = time();
    
    // Redirigir según rol
    switch ($usuarioData['rol']) {
        case 'encargado':
            header('Location: encargado/dashboard.php');
            break;
        case 'cajero':
            header('Location: cajero/ventas.php');
            break;
        case 'farmaceutico':
            header('Location: farmaceutico/productos.php');
            break;
        default:
            header('Location: index.php?error=rol');
    }
    exit();
    
} catch (PDOException $e) {
    header('Location: index.php?error=servidor');
    exit();
}
?>
