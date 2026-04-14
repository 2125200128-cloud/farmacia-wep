<?php


require_once 'base.php';
requiereRol('encargado');

switch ($metodo) {
    case 'GET':
        obtenerEmpleados();
        break;
    case 'POST':
        crearEmpleado();
        break;
    case 'PUT':
        actualizarEmpleado();
        break;
    case 'DELETE':
        eliminarEmpleado();
        break;
    default:
        enviarError('Método no permitido', 405);
}


function obtenerEmpleados() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    $rol = $_GET['rol'] ?? null;
    $estado = $_GET['estado'] ?? 'activo';
    
    try {
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT e.*, u.usuario, u.rol as rol_usuario, u.estado as estado_usuario
                FROM empleados e
                JOIN usuarios u ON e.usuario_id = u.id
                WHERE e.id = :id
            ");
            $stmt->execute([':id' => $id]);
            $empleado = $stmt->fetch();
            
            if (!$empleado) {
                return enviarError('Empleado no encontrado', 404);
            }
            
            return enviarExito('Empleado obtenido', $empleado);
        }
        
        $sql = "
            SELECT e.*, u.usuario, u.ultimo_acceso
            FROM empleados e
            JOIN usuarios u ON e.usuario_id = u.id
            WHERE e.estado = :estado
        ";
        $params = [':estado' => $estado];
        
        if ($rol) {
            $sql .= " AND e.rol = :rol";
            $params[':rol'] = $rol;
        }
        
        $sql .= " ORDER BY e.nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $empleados = $stmt->fetchAll();
        
        enviarExito('Empleados obtenidos', $empleados);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function crearEmpleado() {
    global $pdo, $input;
    
    $campos_requeridos = ['nombre', 'usuario', 'contrasena', 'rol', 'sucursal'];
    foreach ($campos_requeridos as $campo) {
        if (empty($input[$campo])) {
            return enviarError("$campo es requerido", 400);
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        
        $stmtUsuario = $pdo->prepare("
            INSERT INTO usuarios (usuario, contrasena, nombre, rol, correo, telefono, sucursal, estado)
            VALUES (:usuario, :contrasena, :nombre, :rol, :correo, :telefono, :sucursal, :estado)
        ");
        
        $stmtUsuario->execute([
            ':usuario' => $input['usuario'],
            ':contrasena' => $input['contrasena'], 
            ':nombre' => $input['nombre'],
            ':rol' => $input['rol'],
            ':correo' => $input['correo'] ?? null,
            ':telefono' => $input['telefono'] ?? null,
            ':sucursal' => $input['sucursal'],
            ':estado' => 'activo'
        ]);
        
        $usuarioId = $pdo->lastInsertId();
        
        
        $stmtEmpleado = $pdo->prepare("
            INSERT INTO empleados (
                usuario_id, nombre, apellido, correo, telefono,
                sucursal, rol, salario, fecha_ingreso, estado
            ) VALUES (
                :usuario_id, :nombre, :apellido, :correo, :telefono,
                :sucursal, :rol, :salario, :fecha_ingreso, :estado
            )
        ");
        
        $stmtEmpleado->execute([
            ':usuario_id' => $usuarioId,
            ':nombre' => $input['nombre'],
            ':apellido' => $input['apellido'] ?? null,
            ':correo' => $input['correo'] ?? null,
            ':telefono' => $input['telefono'] ?? null,
            ':sucursal' => $input['sucursal'],
            ':rol' => $input['rol'],
            ':salario' => $input['salario'] ?? null,
            ':fecha_ingreso' => date('Y-m-d'),
            ':estado' => 'activo'
        ]);
        
        $empleadoId = $pdo->lastInsertId();
        
        logEvent('empleados', 'INSERT', null, ['id' => $empleadoId], $_SESSION['usuario_id']);
        
        $pdo->commit();
        
        enviarExito('Empleado creado exitosamente', ['id' => $empleadoId, 'usuario_id' => $usuarioId]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            return enviarError('El nombre de usuario ya existe', 400);
        }
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function actualizarEmpleado() {
    global $pdo, $input;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID requerido', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $empleadoActual = $stmt->fetch();
        
        if (!$empleadoActual) {
            return enviarError('Empleado no encontrado', 404);
        }
        
        $campos = [];
        $params = [':id' => $id];
        
        $permitidos = ['nombre', 'apellido', 'correo', 'telefono', 'sucursal', 'rol', 'salario', 'estado'];
        
        foreach ($permitidos as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }
        
        if (empty($campos)) {
            return enviarError('No hay campos para actualizar', 400);
        }
        
        $sql = "UPDATE empleados SET " . implode(', ', $campos) . " WHERE id = :id";
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);
        
        logEvent('empleados', 'UPDATE', $empleadoActual, $input, $_SESSION['usuario_id']);
        
        enviarExito('Empleado actualizado');
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function eliminarEmpleado() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID requerido', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $empleado = $stmt->fetch();
        
        if (!$empleado) {
            return enviarError('Empleado no encontrado', 404);
        }
        
        
        $updateStmt = $pdo->prepare("UPDATE empleados SET estado = 'inactivo', fecha_salida = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $id]);
        
        
        $updateUser = $pdo->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = :usuario_id");
        $updateUser->execute([':usuario_id' => $empleado['usuario_id']]);
        
        logEvent('empleados', 'DELETE', $empleado, null, $_SESSION['usuario_id']);
        
        enviarExito('Empleado eliminado');
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}

?>
