<?php


require_once 'base.php';
requiereRol(['cajero', 'encargado']);

switch ($metodo) {
    case 'GET':
        obtenerVentas();
        break;
    case 'POST':
        crearVenta();
        break;
    case 'PUT':
        actualizarVenta();
        break;
    default:
        enviarError('Método no permitido', 405);
}


function obtenerVentas() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    $fecha_inicio = $_GET['fecha_inicio'] ?? null;
    $fecha_fin = $_GET['fecha_fin'] ?? null;
    $estado = $_GET['estado'] ?? null;
    
    try {
        if ($id) {
            
            $stmt = $pdo->prepare("
                SELECT v.*, 
                       u.nombre as vendedor,
                       c.nombre as cliente_nombre,
                       c.rfc as cliente_rfc,
                       c.telefono as cliente_telefono,
                       c.direccion as cliente_direccion
                FROM ventas v
                LEFT JOIN usuarios u ON v.usuario_id = u.id
                LEFT JOIN clientes c ON v.cliente_id = c.id
                WHERE v.id = :id
            ");
            $stmt->execute([':id' => $id]);
            $venta = $stmt->fetch();
            
            if (!$venta) {
                return enviarError('Venta no encontrada', 404);
            }
            
            
            $stmtDetalles = $pdo->prepare("
                SELECT dv.*, p.nombre as producto_nombre, p.codigo
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = :venta_id
            ");
            $stmtDetalles->execute([':venta_id' => $id]);
            $venta['detalles'] = $stmtDetalles->fetchAll();
            
            return enviarExito('Venta obtenida', $venta);
        }
        
        
        $sql = "
            SELECT v.*, 
                   u.nombre as vendedor,
                   c.nombre as cliente_nombre
            FROM ventas v
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            LEFT JOIN clientes c ON v.cliente_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($estado) {
            $sql .= " AND v.estado = :estado";
            $params[':estado'] = $estado;
        }
        
        if ($fecha_inicio) {
            $sql .= " AND DATE(v.fecha_venta) >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if ($fecha_fin) {
            $sql .= " AND DATE(v.fecha_venta) <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        
        $sql .= " ORDER BY v.fecha_venta DESC LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ventas = $stmt->fetchAll();
        
        enviarExito('Ventas obtenidas', $ventas);
    } catch (PDOException $e) {
        enviarError('Error en base de datos: ' . $e->getMessage(), 500);
    }
}


function crearVenta() {
    global $pdo, $input;
    
    
    if (empty($input['numero_venta']) || empty($input['detalles']) || !is_array($input['detalles'])) {
        return enviarError('Datos incompletos para crear venta', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        
        $subtotal = 0;
        $detallesValidados = [];
        
        $validarDetallesRecursivo = function($detalles) use (&$validarDetallesRecursivo, &$subtotal, &$detallesValidados, $pdo)
         {
            if (empty($detalles)) {
                return;
            }
            
            $detalle = array_shift($detalles);
            
            $stmtProd = $pdo->prepare("SELECT * FROM productos WHERE id = :id");
            $stmtProd->execute([':id' => $detalle['producto_id']]);
            $producto = $stmtProd->fetch();
            
            if (!$producto) {
                throw new Exception("Producto {$detalle['producto_id']} no encontrado");
            }
            
            if ($producto['stock_actual'] < $detalle['cantidad']) {
                throw new Exception("Stock insuficiente para {$producto['nombre']}");
            }
            
            $subtotalDetalle = $detalle['cantidad'] * $detalle['precio_unitario'];
            $subtotal += $subtotalDetalle;
            
            $detallesValidados[] = [
                'producto_id' => $detalle['producto_id'],
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio_unitario'],
                'subtotal' => $subtotalDetalle,
                'producto' => $producto
            ];
            
            $validarDetallesRecursivo($detalles);
        };
        
        $validarDetallesRecursivo($input['detalles']);
        
        
    // El descuento viene del frontend (motor de promociones). Si no se envía, es 0.
    $descuento = floatval($input['descuento'] ?? 0);

    $subtotalConDesc = $subtotal - $descuento;
    $iva = $subtotalConDesc * TASA_IVA;
    $total = $subtotalConDesc + $iva;
        
        
        $stmtVenta = $pdo->prepare("
            INSERT INTO ventas (
                numero_venta, cliente_id, usuario_id, 
                subtotal, iva, total, metodo_pago, 
                estado, notas
            ) VALUES (
                :numero_venta, :cliente_id, :usuario_id,
                :subtotal, :iva, :total, :metodo_pago,
                :estado, :notas
            )
        ");
        
        $stmtVenta->execute([
            ':numero_venta' => $input['numero_venta'],
            ':cliente_id' => $input['cliente_id'] ?? null,
            ':usuario_id' => $_SESSION['usuario_id'],
            ':subtotal' => $subtotal,
            ':iva' => $iva,
            ':total' => $total,
            ':metodo_pago' => $input['metodo_pago'] ?? 'efectivo',
            ':estado' => 'completada',
            ':notas' => $input['notas'] ?? null
        ]);
        
        $ventaId = $pdo->lastInsertId();
        
        
        $stmtDetalle = $pdo->prepare("
            INSERT INTO detalle_ventas (
                venta_id, producto_id, cantidad, 
                precio_unitario, subtotal
            ) VALUES (
                :venta_id, :producto_id, :cantidad,
                :precio_unitario, :subtotal
            )
        ");
        
        $stmtUpdateStock = $pdo->prepare("
            UPDATE productos SET stock_actual = stock_actual - :cantidad
            WHERE id = :producto_id
        ");
        
        $insertarDetallesRecursivo = function($detalles, $ventaId) use (&$insertarDetallesRecursivo, $stmtDetalle, $stmtUpdateStock) {
            if (empty($detalles)) {
                return;
            }
            
            $detalle = array_shift($detalles);
            
            $stmtDetalle->execute([
                ':venta_id' => $ventaId,
                ':producto_id' => $detalle['producto_id'],
                ':cantidad' => $detalle['cantidad'],
                ':precio_unitario' => $detalle['precio_unitario'],
                ':subtotal' => $detalle['subtotal']
            ]);
            
            
            $stmtUpdateStock->execute([
                ':cantidad' => $detalle['cantidad'],
                ':producto_id' => $detalle['producto_id']
            ]);
            
            
            registrarMovimientoInventario(
                $detalle['producto_id'],
                'venta',
                $detalle['cantidad'],
                $detalle['producto']['stock_actual'],
                $detalle['producto']['stock_actual'] - $detalle['cantidad']
            );
            
            $insertarDetallesRecursivo($detalles, $ventaId);
        };
        
        $insertarDetallesRecursivo($detallesValidados, $ventaId);
        
        
        if (!empty($input['cliente_id'])) {
            $stmtCliente = $pdo->prepare("
                UPDATE clientes 
                SET ultimo_compra = NOW(), total_compras = total_compras + :total
                WHERE id = :cliente_id
            ");
            $stmtCliente->execute([
                ':total' => $total,
                ':cliente_id' => $input['cliente_id']
            ]);
        }
        
        logEvent('ventas', 'INSERT', null, ['id' => $ventaId], $_SESSION['usuario_id']);
        
        $pdo->commit();
        
        enviarExito('Venta creada exitosamente', [
            'venta_id' => $ventaId,
            'numero_venta' => $input['numero_venta'],
            'total' => $total
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        enviarError('Error al crear venta: ' . $e->getMessage(), 500);
    }
}


function actualizarVenta() {
    global $pdo, $input;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID de venta requerido', 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        
        $stmt = $pdo->prepare("SELECT * FROM ventas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $venta = $stmt->fetch();
        
        if (!$venta) {
            return enviarError('Venta no encontrada', 404);
        }
        
        
        if ($input['estado'] === 'cancelada') {
            $stmtDetalles = $pdo->prepare("
                SELECT dv.*, p.stock_actual as stock_actual_before
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id = p.id
                WHERE dv.venta_id = :venta_id
            ");
            $stmtDetalles->execute([':venta_id' => $id]);
            $detalles = $stmtDetalles->fetchAll();
            
            $stmtUpdateStock = $pdo->prepare("
                UPDATE productos SET stock_actual = stock_actual + :cantidad
                WHERE id = :producto_id
            ");
            
            foreach ($detalles as $detalle) {
                $stmtUpdateStock->execute([
                    ':cantidad'    => $detalle['cantidad'],
                    ':producto_id' => $detalle['producto_id']
                ]);
                // Trazabilidad: registrar devolución al inventario
                $stockAntes  = $detalle['stock_actual_before'];
                $stockDespues = $stockAntes + $detalle['cantidad'];
                registrarMovimientoInventario(
                    $detalle['producto_id'],
                    'cancelacion_venta',
                    $detalle['cantidad'],
                    $stockAntes,
                    $stockDespues
                );
            }
            logEvent('ventas', 'CANCEL', $venta, ['estado' => 'cancelada'], $_SESSION['usuario_id'] ?? null);
        }
        
        
        $updateStmt = $pdo->prepare("UPDATE ventas SET estado = :estado WHERE id = :id");
        $updateStmt->execute([
            ':estado' => $input['estado'],
            ':id' => $id
        ]);
        
        logEvent('ventas', 'UPDATE', $venta, $input, $_SESSION['usuario_id']);
        
        $pdo->commit();
        
        enviarExito('Venta actualizada exitosamente');
    } catch (Exception $e) {
        $pdo->rollBack();
        enviarError('Error al actualizar: ' . $e->getMessage(), 500);
    }
}


// registrarMovimientoInventario se define globalmente en includes/config.php

?>
