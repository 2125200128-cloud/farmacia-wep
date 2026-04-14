<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
require_once '../includes/funciones_promociones.php';

$paginaTitulo = 'Nueva Venta';
$menuActivo = 'ventas';

// Inicializar sesión carrito
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}
if (!isset($_SESSION['cliente_venta_id'])) {
    $_SESSION['cliente_venta_id'] = '';
    $_SESSION['cliente_venta_nombre'] = '';
}

$mensaje_error = '';
$mensaje_exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_producto') {
        $busqueda = sanitize($_POST['busqueda_producto'] ?? '');
        unset($_SESSION['opciones_busqueda']); // Limpiamos opciones previas
        if (!empty($busqueda)) {
            $stmt = $pdo->prepare("
                SELECT * FROM productos 
                WHERE estado = 'activo' 
                  AND stock_actual > 0
                  AND (codigo = :busqueda OR nombre LIKE :busqueda_like)
                ORDER BY nombre ASC
                LIMIT 5
            ");
            $stmt->execute([':busqueda' => $busqueda, ':busqueda_like' => "%$busqueda%"]);
            $productos_hallados = $stmt->fetchAll();

            if (count($productos_hallados) === 1) {
                // Agregar directo
                $producto = $productos_hallados[0];
                $id = $producto['id'];
                $qty = isset($_SESSION['carrito'][$id]) ? $_SESSION['carrito'][$id]['cantidad'] + 1 : 1;

                if ($qty <= $producto['stock_actual']) {
                    if (!isset($_SESSION['carrito'][$id])) {
                        $_SESSION['carrito'][$id] = [
                            'id' => $id,
                            'codigo' => $producto['codigo'],
                            'nombre' => $producto['nombre'],
                            'precio' => $producto['precio_venta'],
                            'stock' => $producto['stock_actual'],
                            'imagen_path' => $producto['imagen_path'],
                            'cantidad' => 1
                        ];
                    } else {
                        $_SESSION['carrito'][$id]['cantidad']++;
                    }
                    $mensaje_exito = "Producto agregado correctamente.";
                } else {
                    $mensaje_error = "Stock insuficiente para " . $producto['nombre'];
                }
            } elseif (count($productos_hallados) > 1) {
                $_SESSION['opciones_busqueda'] = $productos_hallados;
            } else {
                $mensaje_error = "Producto no encontrado o se encuentra sin stock.";
            }
        }
    } elseif ($accion === 'agregar_producto_exacto') {
        $id = intval($_POST['producto_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = :id AND estado = 'activo' AND stock_actual > 0");
        $stmt->execute([':id' => $id]);
        $producto = $stmt->fetch();
        if ($producto) {
            $qty = isset($_SESSION['carrito'][$id]) ? $_SESSION['carrito'][$id]['cantidad'] + 1 : 1;
            if ($qty <= $producto['stock_actual']) {
                if (!isset($_SESSION['carrito'][$id])) {
                    $_SESSION['carrito'][$id] = [
                        'id' => $id,
                        'codigo' => $producto['codigo'],
                        'nombre' => $producto['nombre'],
                        'precio' => $producto['precio_venta'],
                        'stock' => $producto['stock_actual'],
                        'imagen_path' => $producto['imagen_path'],
                        'cantidad' => 1
                    ];
                } else {
                    $_SESSION['carrito'][$id]['cantidad']++;
                }
                $mensaje_exito = "Producto agregado correctamente.";
                unset($_SESSION['opciones_busqueda']);
            } else {
                $mensaje_error = "Stock insuficiente para " . $producto['nombre'];
            }
        }
    } elseif ($accion === 'cancelar_busqueda') {
        unset($_SESSION['opciones_busqueda']);
    } elseif ($accion === 'eliminar_producto') {
        $id = intval($_POST['producto_id'] ?? 0);
        if (isset($_SESSION['carrito'][$id])) {
            unset($_SESSION['carrito'][$id]);
        }
    } elseif ($accion === 'actualizar_cantidad') {
        $id = intval($_POST['producto_id'] ?? 0);
        $cantidad = intval($_POST['cantidad'] ?? 1);
        
        if (isset($_SESSION['carrito'][$id])) {
             if ($cantidad > 0 && $cantidad <= $_SESSION['carrito'][$id]['stock']) {
                 $_SESSION['carrito'][$id]['cantidad'] = $cantidad;
             } elseif ($cantidad > $_SESSION['carrito'][$id]['stock']) {
                 $mensaje_error = "La cantidad excede el stock disponible.";
             } else {
                 unset($_SESSION['carrito'][$id]);
             }
        }
    } elseif ($accion === 'seleccionar_cliente') {
        $busqueda_cliente = sanitize($_POST['busqueda_cliente'] ?? '');
        if (!empty($busqueda_cliente)) {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE nombre LIKE :busqueda OR rfc = :busqueda_exacta LIMIT 1");
            $stmt->execute([':busqueda' => "%$busqueda_cliente%", ':busqueda_exacta' => $busqueda_cliente]);
            $cliente = $stmt->fetch();
            if ($cliente) {
                $_SESSION['cliente_venta_id'] = $cliente['id'];
                $_SESSION['cliente_venta_nombre'] = $cliente['nombre'];
                $_SESSION['cliente_venta_puntos'] = $cliente['puntos_lealtad'];
                $_SESSION['cliente_venta_total'] = $cliente['total_compras'];
                $_SESSION['cliente_venta_ultima'] = $cliente['ultimo_compra'];
            } else {
                $mensaje_error = "Cliente no encontrado.";
            }
        }
    } elseif ($accion === 'quitar_cliente') {
        $_SESSION['cliente_venta_id'] = '';
        $_SESSION['cliente_venta_nombre'] = '';
        $_SESSION['cliente_venta_puntos'] = 0;
        $_SESSION['cliente_venta_total'] = 0;
    } elseif ($accion === 'limpiar') {
        $_SESSION['carrito'] = [];
        $_SESSION['cliente_venta_id'] = '';
        $_SESSION['cliente_venta_nombre'] = '';
        $_SESSION['cliente_venta_puntos'] = 0;
        $_SESSION['cliente_venta_total'] = 0;
    } elseif ($accion === 'cobrar') {
         if (!empty($_SESSION['carrito'])) {
             $subtotal = 0;
             foreach ($_SESSION['carrito'] as $item) {
                 $subtotal += $item['precio'] * $item['cantidad'];
             }
             $iva = 0; // No se cobra IVA
             
             // Calcular descuento del motor de promociones
             $promoParaCobro   = calcularPromocion(array_values($_SESSION['carrito']), $pdo);
             $descuento_promo  = $promoParaCobro['descuento_total'] ?? 0;
             $total = $subtotal - $descuento_promo;
             
             // Validación FIAR
             $fiar = isset($_POST['fiar']) && $_POST['fiar'] == '1';
             if ($fiar) {
                 $f_nombre = trim($_POST['fiar_nombre'] ?? '');
                 $f_telefono = trim($_POST['fiar_telefono'] ?? '');
                 $f_monto = floatval($_POST['fiar_monto'] ?? 0);
                 
                 if (empty($f_nombre) || empty($f_telefono) || $f_monto <= 0) {
                     throw new Exception("Para 'Fiar' debe ingresar Nombre, Teléfono y Monto de deuda.");
                 }
             }
             
             try {
                $pdo->beginTransaction();
                $numero_venta = 'VT-' . date('YmdHis');
                $cliente_id = !empty($_SESSION['cliente_venta_id']) ? $_SESSION['cliente_venta_id'] : null;
                $usuario_id = $_SESSION['usuario_id'] ?? 1;
                
                $stmtVenta = $pdo->prepare("
                    INSERT INTO ventas (
                        numero_venta, cliente_id, usuario_id, 
                        subtotal, descuento, iva, total, metodo_pago, 
                        estado
                    ) VALUES (
                        :numero_venta, :cliente_id, :usuario_id,
                        :subtotal, :descuento, :iva, :total, :metodo,
                        'completada'
                    )
                ");
                $stmtVenta->execute([
                    ':numero_venta' => $numero_venta,
                    ':cliente_id' => $cliente_id,
                    ':usuario_id' => $usuario_id,
                    ':subtotal' => $subtotal,
                    ':descuento' => $descuento_promo,
                    ':iva' => $iva,
                    ':total' => $total,
                    ':metodo' => $fiar ? 'credito' : 'efectivo'
                ]);

                $ventaId = $pdo->lastInsertId();

                // Si es fiado, actualizar deuda del cliente
                if ($fiar && $cliente_id) {
                    $pdo->prepare("UPDATE clientes SET deuda_actual = deuda_actual + :monto WHERE id = :cid")
                        ->execute([':monto' => $f_monto, ':cid' => $cliente_id]);
                }
                
                $stmtDetalle = $pdo->prepare("
                    INSERT INTO detalle_ventas (venta_id, producto_id, cantidad, precio_unitario, subtotal)
                    VALUES (:venta_id, :producto_id, :cantidad, :precio_unitario, :subtotal)
                ");
                
                $stmtStock = $pdo->prepare("UPDATE productos SET stock_actual = stock_actual - :cantidad WHERE id = :producto_id");
                
                foreach ($_SESSION['carrito'] as $item) {
                     $stmtDetalle->execute([
                         ':venta_id' => $ventaId,
                         ':producto_id' => $item['id'],
                         ':cantidad' => $item['cantidad'],
                         ':precio_unitario' => $item['precio'],
                         ':subtotal' => $item['cantidad'] * $item['precio']
                     ]);
                     
                     $stmtStock->execute([
                         ':cantidad' => $item['cantidad'],
                         ':producto_id' => $item['id']
                     ]);
                     
                     if (function_exists('registrarMovimientoInventario')) {
                         registrarMovimientoInventario($item['id'], 'venta', $item['cantidad'], $item['stock'], $item['stock'] - $item['cantidad']);
                     }
                }
                
                // Insertar productos de regalo si los hay
                foreach ($promoParaCobro['productos_regalo'] as $regalo) {
                    $stmtDetalle->execute([
                        ':venta_id'       => $ventaId,
                        ':producto_id'    => $regalo['producto_id'],
                        ':cantidad'       => 1,
                        ':precio_unitario'=> 0,
                        ':subtotal'       => 0
                    ]);
                    // Descontar del stock aunque sea gratis
                    $stmtStock->execute([
                        ':cantidad'    => 1,
                        ':producto_id' => $regalo['producto_id']
                    ]);
                    // Registrar en auditoría de inventario
                    if (function_exists('registrarMovimientoInventario')) {
                        $stmtStockActual = $pdo->prepare("SELECT stock_actual FROM productos WHERE id = ?");
                        $stmtStockActual->execute([$regalo['producto_id']]);
                        $stockActualRegalo = (int) $stmtStockActual->fetchColumn();
                        registrarMovimientoInventario(
                            $regalo['producto_id'],
                            'regalo_promocion',
                            1,
                            $stockActualRegalo + 1,
                            $stockActualRegalo
                        );
                    }
                }
                
                if ($cliente_id) {
                     $stmtCliente = $pdo->prepare("UPDATE clientes SET ultimo_compra = NOW(), total_compras = total_compras + :total WHERE id = :cliente_id");
                     $stmtCliente->execute([':total' => $total, ':cliente_id' => $cliente_id]);
                }
                
                $pdo->commit();
                
                logEvent('ventas', 'INSERT', null, ['venta_id' => $ventaId, 'numero_venta' => $numero_venta, 'total' => $total]);
                
                $_SESSION['carrito'] = [];
                $_SESSION['cliente_venta_id'] = '';
                $_SESSION['cliente_venta_nombre'] = '';
                $_SESSION['cliente_venta_puntos'] = 0;
                $_SESSION['cliente_venta_total'] = 0;
                header('Location: nota_venta.php?id=' . $ventaId);
                exit;
                
             } catch(Exception $e) {
                 $pdo->rollBack();
                 $mensaje_error = "Error al registrar la venta: " . $e->getMessage();
             }
         } else {
             $mensaje_error = "El carrito está vacío.";
         }
    }
}

// Calcular totales
$subtotal_carrito = 0;
$articulos_carrito = 0;
foreach ($_SESSION['carrito'] as $item) {
    $subtotal_carrito += $item['precio'] * $item['cantidad'];
    $articulos_carrito += $item['cantidad'];
}

$iva_carrito = 0; // No se cobra IVA
$total_carrito = $subtotal_carrito;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">
    <div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Nueva Venta</h2>
          <p class="pagina-subtitulo" id="fecha-nota"><?= date('d/m/Y') ?></p>
        </div>
        <a href="ventas.php" class="btn-cancelar">
          <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($mensaje_error): ?>
        <div style="background-color:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $mensaje_error ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_exito): ?>
        <div style="background-color:#dcfce7; color:#15803d; padding:15px; border-radius:8px; margin-bottom:20px;">
            <i class="fa-solid fa-check-circle"></i> <?= $mensaje_exito ?>
        </div>
    <?php endif; ?>

    <?php
        $todos_productos = $pdo->query("SELECT p.id, p.codigo, p.nombre, p.stock_actual, p.imagen_path, (SELECT COUNT(*) FROM detalle_ventas WHERE producto_id = p.id) as ventas_count FROM productos p WHERE p.estado = 'activo' ORDER BY ventas_count DESC, p.nombre ASC")->fetchAll();
        $todos_clientes = $pdo->query("SELECT id, nombre, rfc, total_compras, puntos_lealtad, ultimo_compra FROM clientes WHERE estado = 'activo' ORDER BY total_compras DESC, nombre ASC")->fetchAll();
    ?>

    <div class="layout-venta">
        <div class="panel-productos">
            <!-- Buscar producto -->
            <div class="card-venta">
                <h3 class="card-titulo-venta">Buscar producto</h3>
                <form method="POST" style="display:flex; gap:10px;" id="form_agregar_fast">
                    <input type="hidden" name="accion" value="agregar_producto">
                    <div class="buscador-producto" style="flex:1; position:relative;">
                      <i class="fa-solid fa-magnifying-glass"></i>
                      <input type="text" name="busqueda_producto" id="input_producto" placeholder="Escribe el nombre o escanea el código..." autocomplete="off" required />
                      <div id="sugerencias_producto" class="sugerencias-dropdown"></div>
                    </div>
                    <button type="submit" class="btn-secundario" style="padding:10px; border-radius:8px;">Buscar</button>
                </form>

                <?php if (!empty($_SESSION['opciones_busqueda'])): ?>
                <div style="margin-top:15px; border:1px solid var(--color-border); border-radius:8px; padding:10px; background:#fff;">
                    <p style="margin:0 0 10px 0; font-weight:bold; color:var(--text-dark);">Se encontraron varios resultados. Elige uno:</p>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($_SESSION['opciones_busqueda'] as $opcion): ?>
                        <form method="POST" style="display:flex; justify-content:space-between; align-items:center; padding:8px; border-radius:6px; background:#f8fafc; border:1px solid #e2e8f0;">
                            <input type="hidden" name="accion" value="agregar_producto_exacto">
                            <input type="hidden" name="producto_id" value="<?= $opcion['id'] ?>">
                            <div style="display:flex; flex-direction:column;">
                                <strong style="color:var(--text-dark);"><?= htmlspecialchars($opcion['nombre']) ?></strong>
                                <span style="font-size:0.8rem; color:var(--text-muted);">Cód: <?= htmlspecialchars($opcion['codigo']) ?> | Stock: <?= $opcion['stock_actual'] ?> | $<?= number_format($opcion['precio_venta'], 2) ?></span>
                            </div>
                            <button type="submit" class="btn-primario" style="padding:6px 12px; font-size:0.85rem;"><i class="fa-solid fa-plus"></i> Elegir</button>
                        </form>
                        <?php endforeach; ?>
                        <form method="POST" style="text-align:right;">
                            <input type="hidden" name="accion" value="cancelar_busqueda">
                            <button type="submit" class="btn-secundario" style="padding:6px 12px; font-size:0.85rem;">Cancelar</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tabla de productos -->
            <div class="card-venta">
                <div class="card-titulo-row">
                    <h3 class="card-titulo-venta">Productos en esta venta</h3>
                    <span class="conteo-productos"><?= $articulos_carrito ?> productos</span>
                </div>

                <div class="tabla-wrapper">
                    <table class="tabla-productos-venta">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Precio unit.</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($_SESSION['carrito'])): ?>
                                <tr>
                                    <td colspan="6" class="tabla-vacia">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                        <p>Busca y agrega productos</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($_SESSION['carrito'] as $item): ?>
                                <tr>
                                    <td><?= $item['codigo'] ?></td>
                                    <td>
                                      <div style="display:flex;align-items:center;gap:10px;">
                                          <?php if(!empty($item['imagen_path'])): ?>
                                              <img src="../<?= htmlspecialchars($item['imagen_path']) ?>" alt="img" style="width:30px;height:30px;border-radius:4px;object-fit:cover;"/>
                                          <?php else: ?>
                                              <div style="width:30px;height:30px;border-radius:4px;background:var(--color-border);display:flex;justify-content:center;align-items:center;color:white;font-size:0.7rem;"><i class="fa-solid fa-pills"></i></div>
                                          <?php endif; ?>
                                          <?= $item['nombre'] ?>
                                      </div>
                                    </td>
                                    <td>$<?= number_format($item['precio'], 2) ?></td>
                                    <td style="width:120px;">
                                        <form method="POST" style="display:flex; gap:5px; align-items:center; margin:0;">
                                            <input type="hidden" name="accion" value="actualizar_cantidad">
                                            <input type="hidden" name="producto_id" value="<?= $item['id'] ?>">
                                            <input type="number" name="cantidad" value="<?= $item['cantidad'] ?>" min="1" max="<?= $item['stock'] ?>" 
                                                   onchange="this.form.submit()"
                                                   style="width:60px; padding:5px; border:1px solid #ddd; border-radius:4px;">
                                            <button type="submit" title="Actualizar" style="display:none;"></button>
                                        </form>
                                    </td>
                                    <td><strong>$<?= number_format($item['precio'] * $item['cantidad'], 2) ?></strong></td>
                                    <td>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="accion" value="eliminar_producto">
                                            <input type="hidden" name="producto_id" value="<?= $item['id'] ?>">
                                            <button type="submit" style="color:var(--color-danger); background:none; border:none; cursor:pointer; font-size:1.2rem;"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel-resumen">
            <!-- Cliente -->
            <div class="card-venta">
                <h3 class="card-titulo-venta">Cliente</h3>
                <?php if (empty($_SESSION['cliente_venta_id'])): ?>
                    <form method="POST" style="display:flex; gap:10px; margin-bottom:15px;">
                        <input type="hidden" name="accion" value="seleccionar_cliente">
                        <div class="buscador-cliente" style="flex:1; position:relative;">
                          <i class="fa-solid fa-user"></i>
                          <input type="text" name="busqueda_cliente" id="input_cliente" placeholder="Nombre o RFC..." autocomplete="off" required />
                          <div id="sugerencias_cliente" class="sugerencias-dropdown"></div>
                        </div>
                        <button type="submit" class="btn-primario" style="margin:0;"><i class="fa-solid fa-plus"></i></button>
                    </form>
                <?php else: ?>
                    <div class="cliente-seleccionado" style="display:flex; align-items: flex-start;">
                        <div class="cliente-avatar-grande">
                            <span><?= strtoupper(substr($_SESSION['cliente_venta_nombre'], 0, 1)) ?></span>
                        </div>
                        <div class="cliente-datos" style="flex:1;">
                            <p class="cliente-nombre-sel"><?= $_SESSION['cliente_venta_nombre'] ?></p>
                            <?php 
                                $puntos = $_SESSION['cliente_venta_puntos'] ?? 0;
                                $totalComp = $_SESSION['cliente_venta_total'] ?? 0;
                                $uCompra = $_SESSION['cliente_venta_ultima'] ?? '';
                                
                                // Lógica de Confianza:
                                // Confiar si compras > 1000 o puntos > 50
                                // Desconfiar si compras < 200 y no ha comprado en 3 meses
                                $diasUltima = $uCompra ? (time() - strtotime($uCompra)) / 86400 : 999;
                                $confiable = ($puntos >= 50 || $totalComp >= 1000);
                                $desconfiable = (!$confiable && $totalComp < 200 && $diasUltima > 90);
                                
                                $colorTrust = $confiable ? '#10b981' : ($desconfiable ? '#ef4444' : '#f59e0b');
                                $labelTrust = $confiable ? 'Cliente de Confiar' : ($desconfiable ? 'Desconfiar (Inactivo/Poco Historial)' : 'Historial Regular');
                            ?>
                            <div style="display:flex; align-items:center; gap:5px; margin-top:5px;">
                                <span style="background:<?= $colorTrust ?>; width:10px; height:10px; border-radius:50%;"></span>
                                <span style="color:<?= $colorTrust ?>; font-weight:bold; font-size:0.85rem;"><?= $labelTrust ?></span>
                            </div>
                            <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                                Puntos: <?= $puntos ?> | Total: $<?= number_format($totalComp, 2) ?>
                            </p>
                        </div>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="accion" value="quitar_cliente">
                            <button type="submit" class="btn-quitar-cliente" title="Quitar cliente">
                              <i class="fa-solid fa-xmark"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="campos-venta" style="margin-top:20px;">
                  <div class="campo-grupo">
                    <label class="campo-label">Fecha</label>
                    <input type="text" class="campo-input" value="<?= date('Y-m-d') ?>" readonly />
                  </div>
                </div>
            </div>

            <!-- Resumen -->
            <div class="card-venta resumen-totales">
                <h3 class="card-titulo-venta">Resumen</h3>
                <div class="linea-resumen">
                  <span>Subtotal</span>
                  <span>$<?= number_format($subtotal_carrito, 2) ?></span>
                </div>
                <div class="linea-resumen">
                  <span>Artículos</span>
                  <span><?= $articulos_carrito ?></span>
                </div>
                <div class="separador-resumen"></div>
                <div class="linea-resumen total">
                  <span>Total a pagar</span>
                  <span id="total_pagar_val">$<?= number_format($total_carrito, 2) ?></span>
                </div>

                <!-- Sección de Promociones Activas -->
                <?php
                    $promoData = !empty($_SESSION['carrito']) ? calcularPromocion(array_values($_SESSION['carrito']), $pdo) : ['descuento_total'=>0,'promociones_aplicadas'=>[],'productos_regalo'=>[]];
                ?>
                <div id="seccion-promos" style="<?= $promoData['descuento_total'] > 0 ? '' : 'display:none;' ?> background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px; margin-top:8px;">
                  <div style="display:flex; align-items:center; gap:6px; font-weight:bold; color:#15803d; margin-bottom:8px;">
                    <i class="fa-solid fa-tag"></i>
                    <span>Promociones aplicadas</span>
                  </div>
                  <div id="lista-promos">
                  <?php foreach($promoData['promociones_aplicadas'] as $promo_ap): ?>
                  <div class="linea-resumen" style="color:#15803d;">
                    <span><?= htmlspecialchars($promo_ap['nombre']) ?></span>
                    <span>-$<?= number_format($promo_ap['monto'], 2) ?></span>
                  </div>
                  <?php endforeach; ?>
                  </div>
                  <div class="separador-resumen"></div>
                  <div class="linea-resumen" style="font-weight:bold; color:#15803d;">
                    <span>Ahorro total</span>
                    <span id="ahorro-total">-$<?= number_format($promoData['descuento_total'], 2) ?></span>
                  </div>
                </div>

                <div class="separador-resumen"></div>

                <div class="campo-pago" style="margin-top: 15px;">
                  <div class="campo-grupo">
                    <label class="campo-label">Efectivo recibido</label>
                    <div class="input-prefijo">
                      <span class="prefijo">$</span>
                      <input type="number" id="monto_pagado" class="campo-input" placeholder="0.00" min="0" step="0.01" style="font-size: 1.2rem; font-weight: bold; color: var(--color-primary);"/>
                    </div>
                  </div>
                </div>

                <!-- Sección FIAR -->
                <div class="separador-resumen"></div>
                <div style="margin: 15px 0;">
                    <?php if (!empty($_SESSION['cliente_venta_id'])): ?>
                        <?php 
                            $puntos = $_SESSION['cliente_venta_puntos'] ?? 0;
                            $totalComp = $_SESSION['cliente_venta_total'] ?? 0;
                            $uCompra = $_SESSION['cliente_venta_ultima'] ?? '';
                            $diasUltima = $uCompra ? (time() - strtotime($uCompra)) / 86400 : 999;
                            
                            $confiable = ($puntos >= 50 || $totalComp >= 1000);
                            $desconfiable = (!$confiable && $totalComp < 200 && $diasUltima > 90);
                            
                            $bannerColor = $confiable ? '#dcfce7' : ($desconfiable ? '#fee2e2' : '#fef9c3');
                            $bannerText = $confiable ? 'CRÉDITO AUTORIZADO' : ($desconfiable ? 'CRÉDITO RESTRINGIDO / ALTO RIESGO' : 'CRÉDITO BAJO OBSERVACIÓN');
                            $bannerTextColor = $confiable ? '#166534' : ($desconfiable ? '#991b1b' : '#854d0e');
                            $bannerIcon = $confiable ? 'fa-check-circle' : ($desconfiable ? 'fa-triangle-exclamation' : 'fa-circle-info');
                        ?>
                        <div style="background:<?= $bannerColor ?>; color:<?= $bannerTextColor ?>; padding:10px; border-radius:8px; border:1px solid <?= $bannerTextColor ?>44; margin-bottom:10px; font-weight:bold; display:flex; align-items:center; gap:8px;">
                            <i class="fa-solid <?= $bannerIcon ?>"></i>
                            <span><?= $bannerText ?></span>
                        </div>

                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:bold; color:var(--color-primary);">
                            <input type="checkbox" name="fiar" value="1" id="toggle_fiar" onchange="document.getElementById('seccion_fiar').style.display = this.checked ? 'block' : 'none'">
                            Habilitar Venta al Crédito (Fiar)
                        </label>
                    <?php else: ?>
                        <div style="background:#f1f5f9; color:#64748b; padding:10px; border-radius:8px; border:1px dashed #cbd5e1; font-size:0.9rem; text-align:center;">
                            <i class="fa-solid fa-user-lock"></i>
                            Seleccione un cliente para ver opciones de crédito
                        </div>
                    <?php endif; ?>

                    <div id="seccion_fiar" style="display:none; margin-top:10px; background:#fff; padding:15px; border-radius:8px; border:1px solid #ddd; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <p style="font-size:0.85rem; color:#64748b; margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:8px;">
                            <strong>Protocolo de Deuda:</strong> Se registrará el monto en el histórico del cliente <u><?= $_SESSION['cliente_venta_nombre'] ?? '' ?></u>.
                        </p>
                        <input type="hidden" name="fiar_nombre" value="<?= $_SESSION['cliente_venta_nombre'] ?? '' ?>">
                        <div class="campo-grupo">
                            <label style="font-size:0.75rem; color:#94a3b8; font-weight:bold;">CLIENTE</label>
                            <input type="text" class="campo-input" value="<?= $_SESSION['cliente_venta_nombre'] ?? '' ?>" readonly style="margin-bottom:8px; background:#f8fafc; color:#1e293b; border-color:#e2e8f0;">
                        </div>
                        <div class="campo-grupo">
                            <label style="font-size:0.75rem; color:#94a3b8; font-weight:bold;">TELÉFONO DE CONTACTO</label>
                            <input type="text" name="fiar_telefono" class="campo-input" placeholder="Ej. 3312345678" style="margin-bottom:8px;" required>
                        </div>
                        <div class="campo-grupo">
                            <label style="font-size:0.75rem; color:#94a3b8; font-weight:bold;">MONTO A DEBER ($)</label>
                            <input type="number" name="fiar_monto" class="campo-input" step="0.01" value="<?= $total_carrito ?>" style="font-weight:bold; color:var(--color-primary);">
                        </div>
                    </div>
                </div>

                <form method="POST" style="margin-bottom: 10px;" id="form_final_cobrar">
                    <input type="hidden" name="accion" value="cobrar">
                    <!-- Estos inputs serán clonados o movidos vía JS si se necesita enviarlos con el mismo form -->
                    <div id="hidden_fiar_container"></div>
                    <button type="submit" class="btn-cobrar" <?= empty($_SESSION['carrito']) ? 'disabled' : '' ?> onclick="syncFiarFields()">
                      <i class="fa-solid fa-check"></i> Registrar venta
                    </button>
                </form>

                <form method="POST">
                    <input type="hidden" name="accion" value="limpiar">
                    <button type="submit" class="btn-limpiar" <?= empty($_SESSION['carrito']) && empty($_SESSION['cliente_venta_id']) ? 'disabled' : '' ?>>
                      <i class="fa-solid fa-trash"></i> Limpiar todo
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<style>
.sugerencias-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--color-border);
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}
.sugerencia-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
}
.sugerencia-item:hover {
    background: #f8fafc;
}
.sug-titulo {
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    font-size: 0.9rem;
}
.sug-sub {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0;
}
.sug-title-default {
    padding: 10px 15px 5px 15px;
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: bold;
    text-transform: uppercase;
    background: #fafafa;
}
</style>

<script>
    const productos = <?= json_encode($todos_productos) ?>;
    const clientes = <?= json_encode($todos_clientes) ?>;

    function setupAutocomplete(inputId, sugerenciasId, data, isProducto) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const sugs = document.getElementById(sugerenciasId);

        function render(busqueda) {
            sugs.innerHTML = '';
            let filtrados = [];
            let isDefault = false;

            if(!busqueda) {
                filtrados = data.slice(0, 5);
                isDefault = true;
            } else {
                const q = busqueda.toLowerCase();
                filtrados = data.filter(d => 
                    isProducto ? 
                        (d.nombre.toLowerCase().includes(q) || d.codigo.toLowerCase().includes(q)) : 
                        (d.nombre.toLowerCase().includes(q) || (d.rfc && d.rfc.toLowerCase().includes(q)))
                ).slice(0, 50);
            }

            if(filtrados.length === 0) {
                sugs.style.display = 'none';
                return;
            }

            sugs.style.display = 'block';

            if (isDefault) {
                const head = document.createElement('div');
                head.className = 'sug-title-default';
                head.innerText = isProducto ? 'Productos frecuentes' : 'Clientes frecuentes';
                sugs.appendChild(head);
            }

            filtrados.forEach(item => {
                const div = document.createElement('div');
                div.className = 'sugerencia-item';
                
                if(isProducto) {
                    const img = item.imagen_path ? `<img src="../${item.imagen_path}" style="width:36px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #ccc;"/>` : `<div style="width:36px;height:36px;border-radius:4px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;"><i class="fa-solid fa-pills"></i></div>`;
                    div.innerHTML = `
                        <div style="display:flex;align-items:center;gap:10px;">
                            ${img}
                            <div>
                                <p class="sug-titulo">${item.nombre}</p>
                                <p class="sug-sub">Cód: ${item.codigo} | Stock: ${item.stock_actual}</p>
                            </div>
                        </div>`;
                    div.onclick = () => { 
                        input.value = item.codigo; 
                        sugs.style.display = 'none'; 
                        document.getElementById('form_agregar_fast').submit();
                    };
                } else {
                    const puntos = parseInt(item.puntos_lealtad) || 0;
                    const totalVal = parseFloat(item.total_compras) || 0;
                    const isConfiable = (puntos >= 50 || totalVal >= 1000);
                    const colorDot = isConfiable ? '#10b981' : (totalVal < 200 ? '#ef4444' : '#f59e0b');
                    const labelEst = isConfiable ? 'Confiable' : (totalVal < 200 ? 'Riesgo' : 'Regular');

                    div.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <p class="sug-titulo">${item.nombre}</p>
                            <p class="sug-sub">RFC: ${item.rfc || 'X'} | Puntos: ${puntos}</p>
                        </div>
                        <span style="background:${colorDot}; width:10px; height:10px; border-radius:50%; margin-right:5px;" title="${labelEst}"></span>
                    </div>`;
                    div.onclick = () => { input.value = item.nombre; sugs.style.display = 'none'; };
                }
                sugs.appendChild(div);
            });
        }

        input.addEventListener('input', e => render(e.target.value));
        input.addEventListener('focus', e => render(e.target.value));
        
        document.addEventListener('click', e => {
            if(!input.contains(e.target) && !sugs.contains(e.target)) {
                sugs.style.display = 'none';
            }
        });
    }

    setupAutocomplete('input_producto', 'sugerencias_producto', productos, true);
    setupAutocomplete('input_cliente', 'sugerencias_cliente', clientes, false);

    // Lógica de cálculo de cambio
    const inputPago = document.getElementById('monto_pagado');
    const displayCambio = document.getElementById('cambio_val');
    const totalPagarRaw = <?= $total_carrito ?>;

    if (inputPago) {
        inputPago.addEventListener('input', function() {
            const pago = parseFloat(this.value) || 0;
            const cambio = pago - totalPagarRaw;
            
            if (cambio >= 0) {
                displayCambio.innerText = '$' + cambio.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                displayCambio.style.color = '#15803d';
            } else {
                displayCambio.innerText = '$0.00';
                displayCambio.style.color = '#b91c1c';
            }
        });
    }

    function syncFiarFields() {
        const container = document.getElementById('hidden_fiar_container');
        container.innerHTML = '';
        if(document.getElementById('toggle_fiar').checked) {
            const fields = ['fiar', 'fiar_nombre', 'fiar_telefono', 'fiar_monto'];
            fields.forEach(f => {
                const ori = document.getElementsByName(f)[0];
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = f;
                hidden.value = ori.value;
                container.appendChild(hidden);
            });
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
