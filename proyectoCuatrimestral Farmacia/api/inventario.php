<?php


require_once 'base.php';
requiereRol(['encargado', 'farmaceutico']);


switch ($metodo) {
    case 'GET':
        obtenerMovimientos();
        break;
    case 'POST':
        if (isset($_GET['tipo']) && $_GET['tipo'] === 'salida') {
            registrarSalida();
        } else {
            registrarEntrada();
        }
        break;
    default:
        enviarError('Método no permitido', 405);
}


function obtenerMovimientos() {
    global $pdo;
    
    $tipo = $_GET['tipo'] ?? null; 
    $producto_id = $_GET['producto_id'] ?? null;
    
    try {
        if ($tipo === 'entrada') {
            $sql = "SELECT e.*, u.nombre as usuario, p.nombre as producto_nombre, de.cantidad, de.precio_unitario, de.subtotal
                    FROM entradas e
                    JOIN detalle_entradas de ON e.id = de.entrada_id
                    JOIN productos p ON de.producto_id = p.id
                    JOIN usuarios u ON e.usuario_id = u.id";
            if ($producto_id) $sql .= " WHERE de.producto_id = :producto_id";
            $sql .= " ORDER BY e.fecha_entrada DESC";
        } else if ($tipo === 'salida') {
            $sql = "SELECT s.*, u.nombre as usuario, p.nombre as producto_nombre, ds.cantidad, ds.precio_unitario, ds.subtotal
                    FROM salidas s
                    JOIN detalle_salidas ds ON s.id = ds.salida_id
                    JOIN productos p ON ds.producto_id = p.id
                    JOIN usuarios u ON s.usuario_id = u.id";
            if ($producto_id) $sql .= " WHERE ds.producto_id = :producto_id";
            $sql .= " ORDER BY s.fecha_salida DESC";
        } else {
            
            $sql = "SELECT m.*, p.nombre as producto_nombre, u.nombre as usuario
                    FROM movimiento_inventario m
                    JOIN productos p ON m.producto_id = p.id
                    JOIN usuarios u ON m.usuario_id = u.id";
            if ($producto_id) $sql .= " WHERE m.producto_id = :producto_id";
            $sql .= " ORDER BY m.fecha_movimiento DESC";
        }

        $stmt = $pdo->prepare($sql);
        if ($producto_id) {
            $stmt->execute([':producto_id' => $producto_id]);
        } else {
            $stmt->execute();
        }
        
        enviarExito('Movimientos obtenidos', $stmt->fetchAll());
    } catch (PDOException $e) {
        enviarError('Error en base de datos: ' . $e->getMessage(), 500);
    }
}


function registrarEntrada() {
    global $pdo, $input;
    
    $campos = ['producto_id', 'cantidad', 'folio'];
    foreach ($campos as $campo) {
        if (empty($input[$campo])) return enviarError("Campo '$campo' es requerido", 400);
    }

    try {
        $pdo->beginTransaction();

        
        $stmtEntrada = $pdo->prepare("INSERT INTO entradas (numero_entrada, usuario_id, total, notas, estado) VALUES (:num, :uid, :total, :notas, 'recibida')");
        $total = ($input['cantidad'] * ($input['costo_unitario'] ?? 0));
        $stmtEntrada->execute([
            ':num' => $input['folio'],
            ':uid' => $_SESSION['usuario_id'],
            ':total' => $total,
            ':notas' => $input['notas'] ?? ''
        ]);
        $entradaId = $pdo->lastInsertId();

        
        $stmtDetalle = $pdo->prepare("INSERT INTO detalle_entradas (entrada_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (:eid, :pid, :cant, :prec, :sub)");
        $stmtDetalle->execute([
            ':eid' => $entradaId,
            ':pid' => $input['producto_id'],
            ':cant' => $input['cantidad'],
            ':prec' => $input['costo_unitario'] ?? 0,
            ':sub' => $total
        ]);

        
        actualizarStock($input['producto_id'], $input['cantidad'], 'entrada');

        $pdo->commit();
        enviarExito('Entrada registrada correctamente');
    } catch (Exception $e) {
        $pdo->rollBack();
        enviarError('Error al registrar entrada: ' . $e->getMessage(), 500);
    }
}


function registrarSalida() {
    global $pdo, $input;
    
    $campos = ['producto_id', 'cantidad', 'folio'];
    foreach ($campos as $campo) {
        if (empty($input[$campo])) return enviarError("Campo '$campo' es requerido", 400);
    }

    try {
        $pdo->beginTransaction();

        
        $stmtStock = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = :id FOR UPDATE");
        $stmtStock->execute([':id' => $input['producto_id']]);
        $prod = $stmtStock->fetch();
        if (!$prod || $prod['stock_actual'] < $input['cantidad']) {
            throw new Exception("Stock insuficiente (Disponible: " . ($prod['stock_actual'] ?? 0) . ")");
        }

        
        $stmtSalida = $pdo->prepare("INSERT INTO salidas (numero_salida, usuario_id, tipo_salida, motivo, total, estado) VALUES (:num, :uid, :tipo, :motivo, :total, 'completada')");
        $stmtSalida->execute([
            ':num' => $input['folio'],
            ':uid' => $_SESSION['usuario_id'],
            ':tipo' => $input['tipo_salida'] ?? 'ajuste',
            ':motivo' => $input['motivo'] ?? '',
            ':total' => 0 
        ]);
        $salidaId = $pdo->lastInsertId();

        
        $stmtDetalle = $pdo->prepare("INSERT INTO detalle_salidas (salida_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (:sid, :pid, :cant, :prec, :sub)");
        $stmtDetalle->execute([
            ':sid' => $salidaId,
            ':pid' => $input['producto_id'],
            ':cant' => $input['cantidad'],
            ':prec' => 0,
            ':sub' => 0
        ]);

        
        actualizarStock($input['producto_id'], $input['cantidad'], 'salida');

        $pdo->commit();
        enviarExito('Salida registrada correctamente');
    } catch (Exception $e) {
        $pdo->rollBack();
        enviarError($e->getMessage(), 400);
    }
}

function actualizarStock($productoId, $cantidad, $tipo) {
    global $pdo;
    
    
    $stmt = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = :id");
    $stmt->execute([':id' => $productoId]);
    $actual = $stmt->fetchColumn();
    
    $nuevo = ($tipo === 'entrada') ? ($actual + $cantidad) : ($actual - $cantidad);
    
    
    $upd = $pdo->prepare("UPDATE productos SET stock_actual = :nuevo WHERE id = :id");
    $upd->execute([':nuevo' => $nuevo, ':id' => $productoId]);
    
    
    $log = $pdo->prepare("INSERT INTO movimiento_inventario (producto_id, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, usuario_id) 
                          VALUES (:pid, :tipo, :cant, :ant, :nue, :uid)");
    $log->execute([
        ':pid' => $productoId,
        ':tipo' => $tipo,
        ':cant' => $cantidad,
        ':ant' => $actual,
        ':nue' => $nuevo,
        ':uid' => $_SESSION['usuario_id']
    ]);
}
