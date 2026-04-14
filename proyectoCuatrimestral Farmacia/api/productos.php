<?php


require_once 'base.php';
requiereRol(['encargado', 'farmaceutico']);


switch ($metodo) {
    case 'GET':
        obtenerProductos();
        break;
    case 'POST':
        crearProducto();
        break;
    case 'PUT':
        actualizarProducto();
        break;
    case 'DELETE':
        eliminarProducto();
        break;
    default:
        enviarError('Método no permitido', 405);
}


function obtenerProductos() {
    global $pdo, $input;
    
    $id = $_GET['id'] ?? null;
    $categoriaId = $_GET['categoria_id'] ?? null;
    $buscar = $_GET['buscar'] ?? null;
    $estado = $_GET['estado'] ?? 'activo';
    
    try {
        if ($id) {
            
            $stmt = $pdo->prepare("
                SELECT p.*, c.nombre as categoria 
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.id = :id
            ");
            $stmt->execute([':id' => $id]);
            $producto = $stmt->fetch();
            
            if (!$producto) {
                return enviarError('Producto no encontrado', 404);
            }
            
            return enviarExito('Producto obtenido', $producto);
        }
        
        
        $sql = "SELECT p.*, c.nombre as categoria FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE 1=1";
        $params = [];
        
        if ($estado) {
            $sql .= " AND p.estado = :estado";
            $params[':estado'] = $estado;
        }
        
        if ($categoriaId) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoriaId;
        }
        
        if ($buscar) {
            $sql .= " AND (p.nombre LIKE :buscar OR p.codigo LIKE :buscar2)";
            $params[':buscar'] = "%{$buscar}%";
            $params[':buscar2'] = "%{$buscar}%";
        }
        
        $sql .= " ORDER BY p.nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $productos = $stmt->fetchAll();
        
        enviarExito('Productos obtenidos', $productos);
    } catch (PDOException $e) {
        enviarError('Error en base de datos: ' . $e->getMessage(), 500);
    }
}


function crearProducto() {
    global $pdo, $input;
    
    
    $campos_requeridos = ['codigo', 'nombre', 'precio_compra', 'precio_venta'];
    foreach ($campos_requeridos as $campo) {
        if (empty($input[$campo])) {
            return enviarError("El campo '$campo' es requerido", 400);
        }
    }
    
    
    $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo = :cod");
    $stmtCheck->execute([':cod' => $input['codigo']]);
    if ($stmtCheck->fetch()) {
        return enviarError("El código '{$input['codigo']}' ya está registrado en otro producto.", 400);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO productos (
                codigo, nombre, descripcion, categoria_id, 
                precio_compra, precio_venta, stock_actual, 
                stock_minimo, stock_maximo, unidad_medida, 
                presentacion, lote, fecha_vencimiento, estado
            ) VALUES (
                :codigo, :nombre, :descripcion, :categoria_id,
                :precio_compra, :precio_venta, :stock_actual,
                :stock_minimo, :stock_maximo, :unidad_medida,
                :presentacion, :lote, :fecha_vencimiento, :estado
            )
        ");
        
        $stmt->execute([
            ':codigo' => $input['codigo'],
            ':nombre' => $input['nombre'],
            ':descripcion' => $input['descripcion'] ?? null,
            ':categoria_id' => $input['categoria_id'] ?? null,
            ':precio_compra' => $input['precio_compra'],
            ':precio_venta' => $input['precio_venta'],
            ':stock_actual' => $input['stock_actual'] ?? 0,
            ':stock_minimo' => $input['stock_minimo'] ?? 10,
            ':stock_maximo' => $input['stock_maximo'] ?? 1000,
            ':unidad_medida' => $input['unidad_medida'] ?? 'un',
            ':presentacion' => $input['presentacion'] ?? null,
            ':lote' => $input['lote'] ?? null,
            ':fecha_vencimiento' => $input['fecha_vencimiento'] ?? null,
            ':estado' => 'activo'
        ]);
        
        $productoId = $pdo->lastInsertId();
        
        
        logEvent('productos', 'INSERT', null, ['id' => $productoId, 'nombre' => $input['nombre']], $_SESSION['usuario_id']);
        
        
        if (!empty($input['stock_actual']) && $input['stock_actual'] > 0) {
            registrarMovimientoInventario($productoId, 'entrada', $input['stock_actual'], 0, $input['stock_actual']);
        }
        
        enviarExito('Producto creado exitosamente', ['id' => $productoId]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            return enviarError('El código del producto ya existe', 400);
        }
        enviarError('Error al crear producto: ' . $e->getMessage(), 500);
    }
}


function actualizarProducto() {
    global $pdo, $input;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID de producto requerido', 400);
    }
    
    try {
        
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $productoActual = $stmt->fetch();
        
        if (!$productoActual) {
            return enviarError('Producto no encontrado', 404);
        }
        
        
        $campos = [];
        $params = [':id' => $id];
        
        $permitidos = ['nombre', 'descripcion', 'categoria_id', 'precio_compra', 'precio_venta', 
                      'stock_minimo', 'stock_maximo', 'presentacion', 'lote', 'fecha_vencimiento', 'estado'];
        
        foreach ($permitidos as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }
        
        if (empty($campos)) {
            return enviarError('No hay campos para actualizar', 400);
        }
        
        $sql = "UPDATE productos SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        
        logEvent('productos', 'UPDATE', $productoActual, $input, $_SESSION['usuario_id']);
        
        enviarExito('Producto actualizado exitosamente');
    } catch (PDOException $e) {
        enviarError('Error al actualizar: ' . $e->getMessage(), 500);
    }
}


function eliminarProducto() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    if (!$id) {
        return enviarError('ID de producto requerido', 400);
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $producto = $stmt->fetch();
        
        if (!$producto) {
            return enviarError('Producto no encontrado', 404);
        }
        
        
        $updateStmt = $pdo->prepare("UPDATE productos SET estado = 'inactivo' WHERE id = :id");
        $updateStmt->execute([':id' => $id]);
        
        logEvent('productos', 'DELETE', $producto, null, $_SESSION['usuario_id']);
        
        enviarExito('Producto eliminado exitosamente');
    } catch (PDOException $e) {
        enviarError('Error al eliminar: ' . $e->getMessage(), 500);
    }
}


?>
