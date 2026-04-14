<?php


require_once 'base.php';
requiereRol(['encargado']);

$tipo = $_GET['tipo'] ?? null;

if (!$tipo) {
    return enviarError('Especifique tipo de reporte: ventas, inventario, clientes, empleados', 400);
}

switch ($tipo) {
    case 'ventas':
        reporteVentas();
        break;
    case 'inventario':
        reporteInventario();
        break;
    case 'clientes':
        reporteClientes();
        break;
    case 'empleados':
        reporteEmpleados();
        break;
    case 'dashboard':
        reporteDashboard();
        break;
    default:
        enviarError('Tipo de reporte no válido', 400);
}


function reporteVentas() {
    global $pdo;
    
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
    
    try {
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(id) as total_ventas,
                SUM(subtotal) as subtotal_total,
                SUM(iva) as iva_total,
                SUM(total) as total_ventas_dinero,
                AVG(total) as promedio_venta,
                COUNT(CASE WHEN metodo_pago = 'efectivo' THEN 1 END) as pago_efectivo,
                COUNT(CASE WHEN metodo_pago = 'tarjeta' THEN 1 END) as pago_tarjeta,
                COUNT(CASE WHEN metodo_pago = 'transferencia' THEN 1 END) as pago_transferencia
            FROM ventas
            WHERE DATE(fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
            AND estado = 'completada'
        ");
        
        $stmt->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        $resumen = $stmt->fetch();
        
        
        $stmtDia = $pdo->prepare("
            SELECT 
                DATE(fecha_venta) as fecha,
                COUNT(id) as cantidad,
                SUM(total) as total
            FROM ventas
            WHERE DATE(fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
            AND estado = 'completada'
            GROUP BY DATE(fecha_venta)
            ORDER BY fecha DESC
        ");
        
        $stmtDia->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        $ventasPorDia = $stmtDia->fetchAll();
        
        
        $stmtProductos = $pdo->prepare("
            SELECT 
                p.id, p.nombre, p.codigo,
                SUM(dv.cantidad) as cantidad_vendida,
                SUM(dv.subtotal) as total_vendido
            FROM detalle_ventas dv
            JOIN productos p ON dv.producto_id = p.id
            JOIN ventas v ON dv.venta_id = v.id
            WHERE DATE(v.fecha_venta) BETWEEN :fecha_inicio AND :fecha_fin
            AND v.estado = 'completada'
            GROUP BY p.id
            ORDER BY cantidad_vendida DESC
            LIMIT 10
        ");
        
        $stmtProductos->execute([
            ':fecha_inicio' => $fecha_inicio,
            ':fecha_fin' => $fecha_fin
        ]);
        $productosVendidos = $stmtProductos->fetchAll();
        
        enviarExito('Reporte de ventas generado', [
            'resumen' => $resumen,
            'ventas_por_dia' => $ventasPorDia,
            'productos_top' => $productosVendidos,
            'periodo' => [
                'inicio' => $fecha_inicio,
                'fin' => $fecha_fin
            ]
        ]);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function reporteInventario() {
    global $pdo;
    
    try {
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(id) as total_productos,
                COUNT(CASE WHEN stock_actual <= stock_minimo THEN 1 END) as productos_bajo_stock,
                COUNT(CASE WHEN stock_actual = 0 THEN 1 END) as productos_sin_stock,
                SUM(stock_actual * precio_venta) as valor_inventario_total
            FROM productos
            WHERE estado = 'activo'
        ");
        
        $stmt->execute();
        $resumen = $stmt->fetch();
        
        
        $stmtBajoStock = $pdo->prepare("
            SELECT id, codigo, nombre, stock_actual, stock_minimo, stock_maximo
            FROM productos
            WHERE estado = 'activo'
            AND stock_actual <= stock_minimo
            ORDER BY stock_actual ASC
        ");
        $stmtBajoStock->execute();
        $productosBajoStock = $stmtBajoStock->fetchAll();
        
        
        $stmtInactivos = $pdo->prepare("
            SELECT p.id, p.codigo, p.nombre, p.stock_actual, p.fecha_creacion,
                   (SELECT MAX(v.fecha_venta) FROM detalle_ventas dv
                    JOIN ventas v ON dv.venta_id = v.id
                    WHERE dv.producto_id = p.id) as ultima_venta
            FROM productos p
            WHERE p.estado = 'activo'
            AND (SELECT MAX(v.fecha_venta) FROM detalle_ventas dv
                 JOIN ventas v ON dv.venta_id = v.id
                 WHERE dv.producto_id = p.id) IS NULL
            OR (SELECT MAX(v.fecha_venta) FROM detalle_ventas dv
                JOIN ventas v ON dv.venta_id = v.id
                WHERE dv.producto_id = p.id) < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtInactivos->execute();
        $productosInactivos = $stmtInactivos->fetchAll();
        
        enviarExito('Reporte de inventario', [
            'resumen' => $resumen,
            'bajo_stock' => $productosBajoStock,
            'productos_inactivos' => $productosInactivos
        ]);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function reporteClientes() {
    global $pdo;
    
    try {
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(id) as total_clientes,
                COUNT(CASE WHEN estado = 'activo' THEN 1 END) as clientes_activos,
                SUM(total_compras) as total_compras,
                AVG(total_compras) as promedio_compra,
                MAX(total_compras) as mayor_compra
            FROM clientes
        ");
        $stmt->execute();
        $resumen = $stmt->fetch();
        
        
        $stmtTop = $pdo->prepare("
            SELECT id, nombre, rfc, correo, total_compras, 
                   COUNT(v.id) as numero_compras, ultimo_compra
            FROM clientes c
            LEFT JOIN ventas v ON c.id = v.cliente_id
            WHERE c.estado = 'activo'
            GROUP BY c.id
            ORDER BY total_compras DESC
            LIMIT 15
        ");
        $stmtTop->execute();
        $clientesTop = $stmtTop->fetchAll();
        
        enviarExito('Reporte de clientes', [
            'resumen' => $resumen,
            'clientes_top' => $clientesTop
        ]);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function reporteEmpleados() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.id, e.nombre, e.apellido, e.rol, e.sucursal,
                u.usuario, u.ultimo_acceso, e.estado,
                COUNT(v.id) as numero_ventas,
                SUM(v.total) as total_vendido
            FROM empleados e
            JOIN usuarios u ON e.usuario_id = u.id
            LEFT JOIN ventas v ON u.id = v.usuario_id
            WHERE e.rol != 'encargado'
            GROUP BY e.id
            ORDER BY total_vendido DESC
        ");
        $stmt->execute();
        $empleados = $stmt->fetchAll();
        
        enviarExito('Reporte de empleados', $empleados);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}


function reporteDashboard() {
    global $pdo;
    
    $hoy = date('Y-m-d');
    
    try {
        
        $stmtVentas = $pdo->prepare("
            SELECT 
                COUNT(id) as ventas_hoy,
                SUM(total) as total_hoy
            FROM ventas
            WHERE DATE(fecha_venta) = :fecha
            AND estado = 'completada'
        ");
        $stmtVentas->execute([':fecha' => $hoy]);
        $ventasHoy = $stmtVentas->fetch();
        
        
        $stmtBajo = $pdo->prepare("
            SELECT COUNT(id) as productos_bajo_stock
            FROM productos
            WHERE stock_actual <= stock_minimo
            AND estado = 'activo'
        ");
        $stmtBajo->execute();
        $bajoStock = $stmtBajo->fetch();
        
        
        $stmtInventario = $pdo->prepare("
            SELECT SUM(stock_actual * precio_venta) as valor_inventario
            FROM productos
            WHERE estado = 'activo'
        ");
        $stmtInventario->execute();
        $inventario = $stmtInventario->fetch();
        
        
        $stmtUltimas = $pdo->prepare("
            SELECT v.numero_venta, v.fecha_venta, v.total, u.nombre as vendedor
            FROM ventas v
            JOIN usuarios u ON v.usuario_id = u.id
            ORDER BY v.fecha_venta DESC
            LIMIT 5
        ");
        $stmtUltimas->execute();
        $ultimasVentas = $stmtUltimas->fetchAll();
        
        enviarExito('Dashboard', [
            'ventas_hoy' => $ventasHoy,
            'bajo_stock' => $bajoStock,
            'valor_inventario' => $inventario,
            'ultimas_ventas' => $ultimasVentas,
            'fecha' => $hoy
        ]);
    } catch (PDOException $e) {
        enviarError('Error: ' . $e->getMessage(), 500);
    }
}

?>
