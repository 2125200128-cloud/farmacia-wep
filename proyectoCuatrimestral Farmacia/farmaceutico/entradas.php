<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Entradas';
$menuActivo = 'entradas';

// Filtros básicos
$busqueda = sanitize($_GET['busqueda'] ?? '');
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$sql = "SELECT m.*, p.nombre as producto_nombre, p.imagen_path, p.codigo as producto_codigo, u.nombre as usuario 
        FROM movimiento_inventario m 
        JOIN productos p ON m.producto_id = p.id 
        LEFT JOIN usuarios u ON m.usuario_id = u.id 
        WHERE m.tipo_movimiento = 'entrada'";
$params = [];

if ($busqueda) {
    $sql .= " AND (p.nombre LIKE :busqueda OR p.codigo LIKE :busqueda OR m.proveedor LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($fecha_desde) {
    $sql .= " AND DATE(m.fecha_movimiento) >= :desde";
    $params[':desde'] = $fecha_desde;
}
if ($fecha_hasta) {
    $sql .= " AND DATE(m.fecha_movimiento) <= :hasta";
    $params[':hasta'] = $fecha_hasta;
}

$sql .= " ORDER BY m.fecha_movimiento DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entradas = $stmt->fetchAll();

// Estadísticas
$hoy = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$inicioMes = date('Y-m-01');

$entradasHoy = $pdo->query("SELECT COUNT(*) FROM movimiento_inventario WHERE tipo_movimiento='entrada' AND DATE(fecha_movimiento) = '$hoy'")->fetchColumn();
$entradasSemana = $pdo->query("SELECT COUNT(*) FROM movimiento_inventario WHERE tipo_movimiento='entrada' AND DATE(fecha_movimiento) >= '$inicioSemana'")->fetchColumn();
$unidadesMes = $pdo->query("SELECT SUM(cantidad) FROM movimiento_inventario WHERE tipo_movimiento='entrada' AND DATE(fecha_movimiento) >= '$inicioMes'")->fetchColumn() ?: 0;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Entradas de inventario (PHP)</h2>
          <p class="pagina-subtitulo">Registro de compras y reposición de stock</p>
        </div>
        <a href="nueva_entrada.php" class="btn-primario">
          <i class="fa-solid fa-plus"></i>
          Registrar entrada
        </a>
        <a href="nuevo_producto.php" class="btn-secundario" style="margin-left: 8px;">
          <i class="fa-solid fa-box"></i>
          Nuevo producto
        </a>
      </div>
<div class="tarjetas-entradas">
        <div class="tarjeta-entrada-stat">
          <div class="ent-icono turquesa">
            <i class="fa-solid fa-arrow-down-to-bracket"></i>
          </div>
          <div>
            <p class="ent-etiqueta">Entradas hoy</p>
            <p class="ent-valor"><?= $entradasHoy ?></p>
          </div>
        </div>
        <div class="tarjeta-entrada-stat">
          <div class="ent-icono azul">
            <i class="fa-solid fa-calendar-week"></i>
          </div>
          <div>
            <p class="ent-etiqueta">Esta semana</p>
            <p class="ent-valor"><?= $entradasSemana ?></p>
          </div>
        </div>
        <div class="tarjeta-entrada-stat">
          <div class="ent-icono verde">
            <i class="fa-solid fa-boxes-stacked"></i>
          </div>
          <div>
            <p class="ent-etiqueta">Unidades este mes</p>
            <p class="ent-valor"><?= $unidadesMes ?></p>
          </div>
        </div>
      </div>
<div class="card-entradas">
<form method="GET" class="filtros-entradas">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por producto..." />
          </div>
          <div class="filtros-derecha">
            <div class="rango-fechas">
              <input type="date" name="fecha_desde" value="<?= $fecha_desde ?>" class="input-fecha" title="Desde" />
              <span class="separador-fechas">—</span>
              <input type="date" name="fecha_hasta" value="<?= $fecha_hasta ?>" class="input-fecha" title="Hasta" />
            </div>
            <button type="submit" class="btn-secundario" style="margin-right:10px; padding:10px;">Filtrar</button>
            <a href="entradas.php" class="btn-secundario" style="padding:10px; text-decoration:none;">Limpiar</a>
          </div>
        </form>
<div class="tabla-wrapper">
          <table class="tabla-entradas">
            <thead>
              <tr>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Proveedor</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Costo unit.</th>
                <th class="text-right">Total</th>
                <th>Registró</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($entradas)): ?>
              <tr>
                <td colspan="9" class="tabla-vacia">
                  <i class="fa-solid fa-arrow-down-to-bracket"></i>
                  <p>Sin entradas registradas</p>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach($entradas as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['numero_movimiento'] ?? '-') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($e['fecha_movimiento'])) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if(!empty($e['imagen_path'])): ?>
                                <img src="../<?= htmlspecialchars($e['imagen_path']) ?>" alt="img" style="width:30px;height:30px;border-radius:4px;object-fit:cover;border:1px solid #ddd;"/>
                            <?php else: ?>
                                <div style="width:30px;height:30px;border-radius:4px;background:var(--color-border);display:flex;justify-content:center;align-items:center;color:white;font-size:0.7rem;"><i class="fa-solid fa-pills"></i></div>
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($e['producto_nombre']) ?></strong>
                                <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($e['producto_codigo']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($e['proveedor'] ?? '-') ?></td>
                    <td class="text-center"><strong><?= $e['cantidad'] ?></strong></td>
                    <td class="text-right">$<?= number_format($e['costo_unitario'] ?? 0, 2) ?></td>
                    <td class="text-right">$<?= number_format(($e['costo_unitario'] ?? 0) * $e['cantidad'], 2) ?></td>
                    <td><?= htmlspecialchars($e['usuario'] ?? '-') ?></td>
                    <td><button type="button" class="btn-accion"><i class="fa-solid fa-eye"></i></button></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<div class="paginacion">
          <p class="paginacion-info">Mostrando <?= count($entradas) ?> entradas</p>
        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
