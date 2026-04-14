<?php


require_once 'base.php';
requiereRol(['cajero', 'encargado']);

switch ($metodo) {
    case 'GET':
        obtenerClientes();
        break;
    case 'POST':
        crearCliente();
        break;
    case 'PUT':
        actualizarCliente();
        break;
    case 'DELETE':
        eliminarCliente();
        break;
    default:
        enviarError('Método no permitido', 405);
}


function obtenerClientes() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    $buscar = $_GET['buscar'] ?? null;
    $estado = $_GET['estado'] ?? 'activo';
    
    try {
        if ($id) {
            
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $cliente = $stmt->fetch();
            
            if (!$cliente) {
                return enviarError('Cliente no encontrado', 404);
            }
            
            
            $stmtVentas = $pdo->prepare("
                SELECT v.numero_venta, v.fecha_venta, v.total, v.estado
                FROM ventas v
                WHERE v.cliente_id = :cliente_id
                ORDER BY v.fecha_venta DESC
                LIMIT 10
            ");
            $stmtVentas->execute([':cliente_id' => $id]);
            $cliente['ultimas_ventas'] = $stmtVentas->fetchAll();
            
            return enviarExito('Cliente obtenido', $cliente);
        }
        
        
        $sql = "
            SELECT * FROM clientes 
            WHERE estado = :estado
        ";
        $params = [':estado' => $estado];
        
        if ($buscar) {
            $sql .= " AND (nombre LIKE :buscar OR rfc LIKE :buscar OR correo LIKE :buscar)";
            $params[':buscar'] = "%{$buscar}%";
        }
        
        $sql .= " ORDER BY nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clientes = $stmt->fetchAll();
        
        enviarExito('Clientes obtenidos', $clientes);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function crearCliente() {
    global $pdo, $input;
    
    if (empty($input['nombre'])) {
        return enviarError('Nombre del cliente es requerido', 400);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO clientes (
                rfc, nombre, apellido, correo, telefono,
                direccion, ciudad, estado_provincia, codigo_postal, estado
            ) VALUES (
                :rfc, :nombre, :apellido, :correo, :telefono,
                :direccion, :ciudad, :estado_provincia, :codigo_postal, :estado
            )
        ");
        
        $stmt->execute([
            ':rfc' => $input['rfc'] ?? null,
            ':nombre' => $input['nombre'],
            ':apellido' => $input['apellido'] ?? null,
            ':correo' => $input['correo'] ?? null,
            ':telefono' => $input['telefono'] ?? null,
            ':direccion' => $input['direccion'] ?? null,
            ':ciudad' => $input['ciudad'] ?? null,
            ':estado_provincia' => $input['estado_provincia'] ?? null,
            ':codigo_postal' => $input['codigo_postal'] ?? null,
            ':estado' => 'activo'
        ]);
        
        $clienteId = $pdo->lastInsertId();
        logEvent('clientes', 'INSERT', null, ['id' => $clienteId], $_SESSION['usuario_id']);
        
        enviarExito('Cliente creado exitosamente', ['id' => $clienteId]);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function actualizarCliente() {
    global $pdo, $input;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID requerido', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $clienteActual = $stmt->fetch();
        
        if (!$clienteActual) {
            return enviarError('Cliente no encontrado', 404);
        }
        
        $campos = [];
        $params = [':id' => $id];
        
        $permitidos = ['nombre', 'apellido', 'rfc', 'correo', 'telefono', 
                      'direccion', 'ciudad', 'estado_provincia', 'codigo_postal', 'estado'];
        
        foreach ($permitidos as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }
        
        if (empty($campos)) {
            return enviarError('No hay campos para actualizar', 400);
        }
        
        $sql = "UPDATE clientes SET " . implode(', ', $campos) . " WHERE id = :id";
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($params);
        
        logEvent('clientes', 'UPDATE', $clienteActual, $input, $_SESSION['usuario_id']);
        
        enviarExito('Cliente actualizado exitosamente');
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function eliminarCliente() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID requerido', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            return enviarError('Cliente no encontrado', 404);
        }
        
        
        $updateStmt = $pdo->prepare("UPDATE clientes SET estado = 'inactivo' WHERE id = :id");
        $updateStmt->execute([':id' => $id]);
        
        logEvent('clientes', 'DELETE', $cliente, null, $_SESSION['usuario_id']);
        
        enviarExito('Cliente eliminado exitosamente');
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}

?>
