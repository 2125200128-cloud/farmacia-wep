<?php
/**
 * AJAX: Calcular promociones activas para el carrito actual
 * Endpoint: encargado/ajax_promociones.php (o cajero, accesible por ambos roles)
 * Método: POST
 * Body JSON: { carrito: [ { id, precio, cantidad }, ... ] }
 */
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
require_once '../includes/funciones_promociones.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$carrito = $input['carrito'] ?? [];

if (empty($carrito) || !is_array($carrito)) {
    echo json_encode(['descuento_total' => 0, 'promociones_aplicadas' => [], 'productos_regalo' => []]);
    exit;
}

// Normalizar: asegurarse de que cada item tenga los campos mínimos
$carritoNormalizado = [];
foreach ($carrito as $item) {
    if (isset($item['id'], $item['precio'], $item['cantidad'])) {
        $carritoNormalizado[] = [
            'id'       => (int)   $item['id'],
            'precio'   => (float) $item['precio'],
            'cantidad' => (int)   $item['cantidad'],
        ];
    }
}

try {
    $resultado = calcularPromocion($carritoNormalizado, $pdo);
    echo json_encode($resultado);
} catch (Exception $e) {
    echo json_encode(['descuento_total' => 0, 'promociones_aplicadas' => [], 'productos_regalo' => [], 'error' => $e->getMessage()]);
}
