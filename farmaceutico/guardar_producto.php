<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: productos.php");
    exit();
}

try {
    // 1. Generar código numérico corto (5 dígitos, basado en el auto_increment actual)
    $stmtNextId = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'productos'");
    $nextId = (int) ($stmtNextId->fetchColumn() ?? 10000);
    $codigo = str_pad($nextId, 5, '0', STR_PAD_LEFT);

    // 2. Extraer parámetros del POST
    $nombre = trim($_POST['nombre'] ?? '');
    $categoria_id = (int) ($_POST['categoria'] ?? 6);
    $presentacion = trim($_POST['presentacion'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio_venta = (float) ($_POST['precio'] ?? 0);
    $precio_compra = (float) ($_POST['costo'] ?? 0);
    $stock_inicial = (int) ($_POST['stock_inicial'] ?? 0);
    $stock_minimo = (int) ($_POST['stock_minimo'] ?? 5);
    if ($stock_minimo < 1) {
        $stock_minimo = 5;
    }
    $lote = trim($_POST['lote'] ?? '');
    $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    $fecha_vencimiento = !empty($fecha_vencimiento) ? $fecha_vencimiento : null;

    if (empty($nombre) || $precio_venta <= 0) {
        $_SESSION['error'] = 'Nombre y Precio de Venta son obligatorios.';
        header("Location: nuevo_producto.php");
        exit();
    }

    // 3. Subir imagen (si se provee)
    $imagen_path = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $img_tmp = $_FILES['imagen']['tmp_name'];
        $img_name = $_FILES['imagen']['name'];
        $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));

        $valid_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($img_ext, $valid_ext)) {
            $dir = '../assets/img/productos/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $nuevo_nombre = 'prod_' . $codigo . '.' . $img_ext;
            if (move_uploaded_file($img_tmp, $dir . $nuevo_nombre)) {
                $imagen_path = 'assets/img/productos/' . $nuevo_nombre;
            }
        }
    }

    // 4. Guardar en Base de Datos
    $stmt = $pdo->prepare("
        INSERT INTO productos (
            codigo, nombre, imagen_path, descripcion, categoria_id, 
            precio_compra, precio_venta, stock_actual, 
            stock_minimo, presentacion, estado, lote, fecha_vencimiento
        ) VALUES (
            :codigo, :nombre, :imagen_path, :descripcion, :categoria_id,
            :precio_compra, :precio_venta, :stock_actual,
            :stock_minimo, :presentacion, 'activo', :lote, :fecha_vencimiento
        )
    ");

    $stmt->execute([
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':imagen_path' => $imagen_path,
        ':descripcion' => $descripcion,
        ':categoria_id' => $categoria_id,
        ':precio_compra' => $precio_compra,
        ':precio_venta' => $precio_venta,
        ':stock_actual' => $stock_inicial,
        ':stock_minimo' => $stock_minimo,
        ':presentacion' => $presentacion,
        ':lote' => $lote,
        ':fecha_vencimiento' => $fecha_vencimiento
    ]);

    $productoId = $pdo->lastInsertId();

    // Logear la creación
    logEvent('productos', 'INSERT', null, ['id' => $productoId, 'nombre' => $nombre], $_SESSION['usuario_id']);

    if ($stock_inicial > 0) {
        registrarMovimientoInventario($productoId, 'entrada', $stock_inicial, 0, $stock_inicial);
    }

    $_SESSION['exito'] = 'Producto guardado correctamente con el código ' . $codigo;
    header("Location: productos.php");
    exit();

} catch (Exception $e) {
    $_SESSION['error'] = 'Error al guardar el producto: ' . $e->getMessage();
    header("Location: nuevo_producto.php");
    exit();
}
?>