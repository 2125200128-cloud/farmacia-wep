<?php
/**
 * Motor de Promociones — FarmaControl
 * Archivo: includes/funciones_promociones.php
 *
 * Uso:
 *   require_once '../includes/funciones_promociones.php';
 *   $resultado = calcularPromocion($carrito, $pdo);
 *
 * Retorna:
 *   [
 *     'descuento_total'       => float,
 *     'promociones_aplicadas' => [ ['nombre'=>..., 'monto'=>...], ... ],
 *     'productos_regalo'      => [ ['producto_id'=>..., 'nombre'=>...], ... ]
 *   ]
 */

function calcularPromocion(array $carrito, PDO $pdo): array
{
    $hoy = date('Y-m-d');

    // 1. Obtener todas las promociones activas cuya ventana de fechas cubre hoy
    $stmt = $pdo->prepare("
        SELECT * FROM promociones
        WHERE activa = 1
          AND fecha_inicio <= :hoy1
          AND fecha_fin    >= :hoy2
    ");
    $stmt->execute([':hoy1' => $hoy, ':hoy2' => $hoy]);
    $promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($promociones) || empty($carrito)) {
        return ['descuento_total' => 0, 'promociones_aplicadas' => [], 'productos_regalo' => []];
    }

    // Pre-calcular totales del carrito
    $subtotalCarrito = 0;
    $idsEnCarrito = [];
    $categoriasEnCarrito = [];

    foreach ($carrito as $item) {
        $subtotalCarrito += $item['precio'] * $item['cantidad'];
        $idsEnCarrito[] = (int) $item['id'];
    }

    // Obtener categorías de los productos en el carrito (si existe la columna)
    if (!empty($idsEnCarrito)) {
        $placeholders = implode(',', array_fill(0, count($idsEnCarrito), '?'));
        try {
            $stmtCat = $pdo->prepare("SELECT id, categoria_id FROM productos WHERE id IN ($placeholders)");
            $stmtCat->execute($idsEnCarrito);
            while ($row = $stmtCat->fetch()) {
                if (!empty($row['categoria_id'])) {
                    $categoriasEnCarrito[] = (int) $row['categoria_id'];
                }
            }
        } catch (Exception $e) {
            // La columna categoria_id puede no existir aún; continuar sin filtro de categoría
        }
    }
    $categoriasEnCarrito = array_unique($categoriasEnCarrito);

    $descuentoTotal = 0.0;
    $promocionesAplicadas = [];
    $productosRegalo = [];

    foreach ($promociones as $promo) {
        $condTipo = $promo['condicion_tipo'] ?? '';
        $condValor = $promo['condicion_valor'] ?? '';
        $tipo = $promo['tipo'];
        $valor = (float) $promo['valor'];
        $cumple = false;

        // 2. Evaluar condición
        switch ($condTipo) {
            case 'producto_especifico':
                $cumple = in_array((int) $condValor, $idsEnCarrito);
                break;

            case 'categoria':
                $cumple = in_array((int) $condValor, $categoriasEnCarrito);
                break;

            case 'monto_minimo':
                $cumple = $subtotalCarrito >= (float) $condValor;
                break;

            default:
                // Sin condición específica → aplica siempre
                $cumple = true;
                break;
        }

        if (!$cumple)
            continue;

        // 3. Calcular descuento según tipo
        $montoDescuento = 0.0;

        switch ($tipo) {
            case 'porcentaje':
                $montoDescuento = $subtotalCarrito * ($valor / 100);
                break;

            case 'monto_fijo':
                $montoDescuento = min($valor, $subtotalCarrito);
                break;

            case '2x1':
                // Aplica sobre el producto de menor precio en el carrito (o el específico)
                if ($condTipo === 'producto_especifico') {
                    foreach ($carrito as $item) {
                        if ((int) $item['id'] === (int) $condValor && $item['cantidad'] >= 2) {
                            $montoDescuento = (float) $item['precio'];
                            break;
                        }
                    }
                } else {
                    // Producto más barato del carrito
                    $precios = array_column($carrito, 'precio');
                    if (!empty($precios)) {
                        $montoDescuento = (float) min($precios);
                    }
                }
                break;

            case 'producto_gratis':
                // El descuento es el precio del producto regalo
                $idRegalo = (int) ($promo['producto_regalo_id'] ?? 0);
                if ($idRegalo > 0) {
                    $stmtP = $pdo->prepare("SELECT id, nombre, precio_venta FROM productos WHERE id = ? AND stock_actual > 0 LIMIT 1");
                    $stmtP->execute([$idRegalo]);
                    $prod = $stmtP->fetch();
                    if ($prod) {
                        $montoDescuento = (float) $prod['precio_venta'];
                        $productosRegalo[] = [
                            'producto_id' => $prod['id'],
                            'nombre' => $prod['nombre'],
                            'precio' => 0,
                        ];
                    }
                }
                break;
        }

        if ($montoDescuento > 0) {
            $descuentoTotal += $montoDescuento;
            $promocionesAplicadas[] = [
                'nombre' => $promo['nombre'],
                'monto' => round($montoDescuento, 2),
            ];
        }
    }

    return [
        'descuento_total' => round($descuentoTotal, 2),
        'promociones_aplicadas' => $promocionesAplicadas,
        'productos_regalo' => $productosRegalo,
    ];
}
