<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Salidas';
$menuActivo = 'productos';

// Filtros básicos
$busqueda = sanitize($_GET['busqueda'] ?? '');
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$motivo = sanitize($_GET['motivo'] ?? '');

$sql = "SELECT m.*, p.nombre as producto_nombre, p.imagen_path, p.codigo as producto_codigo, u.nombre as usuario 
        FROM movimiento_inventario m 
        JOIN productos p ON m.producto_id = p.id 
        LEFT JOIN usuarios u ON m.usuario_id = u.id 
        WHERE m.tipo_movimiento = 'salida'";
$params = [];

if ($busqueda) {
    $sql .= " AND (p.nombre LIKE :busqueda OR p.codigo LIKE :busqueda OR m.motivo LIKE :busqueda)";
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
if ($motivo) {
    $sql .= " AND m.motivo = :motivo";
    $params[':motivo'] = $motivo;
}

$sql .= " ORDER BY m.fecha_movimiento DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salidas = $stmt->fetchAll();

// Estadísticas
$hoy = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$inicioMes = date('Y-m-01');

$salidasHoy = $pdo->query("SELECT COUNT(*) FROM movimiento_inventario WHERE tipo_movimiento='salida' AND DATE(fecha_movimiento) = '$hoy'")->fetchColumn();
$salidasSemana = $pdo->query("SELECT COUNT(*) FROM movimiento_inventario WHERE tipo_movimiento='salida' AND DATE(fecha_movimiento) >= '$inicioSemana'")->fetchColumn();
$unidadesMes = $pdo->query("SELECT SUM(cantidad) FROM movimiento_inventario WHERE tipo_movimiento='salida' AND DATE(fecha_movimiento) >= '$inicioMes'")->fetchColumn() ?: 0;

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Salidas de inventario (PHP)</h2>
          <p class="pagina-subtitulo">Registro de mermas, vencimientos y ajustes</p>
        </div>
        <a href="nueva_salida.php" class="btn-primario">
          <i class="fa-solid fa-plus"></i>
          Registrar salida
        </a>
      </div>
<div class="tarjetas-salidas">
        <div class="tarjeta-salida-stat">
          <div class="sal-icono naranja">
            <i class="fa-solid fa-arrow-up-from-bracket"></i>
          </div>
          <div>
            <p class="sal-etiqueta">Salidas hoy</p>
            <p class="sal-valor"><?= $salidasHoy ?></p>
          </div>
        </div>
        <div class="tarjeta-salida-stat">
          <div class="sal-icono azul">
            <i class="fa-solid fa-calendar-week"></i>
          </div>
          <div>
            <p class="sal-etiqueta">Esta semana</p>
            <p class="sal-valor"><?= $salidasSemana ?></p>
          </div>
        </div>
        <div class="tarjeta-salida-stat">
          <div class="sal-icono rojo">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <div>
            <p class="sal-etiqueta">Unidades este mes</p>
            <p class="sal-valor"><?= $unidadesMes ?></p>
          </div>
        </div>
      </div>
<div class="card-salidas">
<form method="GET" class="filtros-salidas">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por producto o folio..."/>
          </div>
          <div class="filtros-derecha">
            <select class="select-filtro" name="motivo">
              <option value="">Todos los motivos</option>
              <option value="merma" <?= $motivo=='merma'?'selected':'' ?>>Merma</option>
              <option value="vencimiento" <?= $motivo=='vencimiento'?'selected':'' ?>>Vencimiento</option>
              <option value="ajuste" <?= $motivo=='ajuste'?'selected':'' ?>>Ajuste de inventario</option>
              <option value="devolucion" <?= $motivo=='devolucion'?'selected':'' ?>>Devolución</option>
              <option value="otro" <?= $motivo=='otro'?'selected':'' ?>>Otro</option>
            </select>
            <div class="rango-fechas">
              <input type="date" name="fecha_desde" value="<?= $fecha_desde ?>" class="input-fecha" title="Desde"/>
              <span class="separador-fechas">—</span>
              <input type="date" name="fecha_hasta" value="<?= $fecha_hasta ?>" class="input-fecha" title="Hasta"/>
            </div>
            <button type="submit" class="btn-secundario" style="margin-right:10px; padding:10px;">Filtrar</button>
            <a href="salidas.php" class="btn-secundario" style="padding:10px; text-decoration:none;">Limpiar</a>
          </div>
        </form>
<div class="tabla-wrapper">
          <table class="tabla-salidas">
            <thead>
              <tr>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Motivo</th>
                <th class="text-center">Cantidad</th>
                <th>Notas</th>
                <th>Registró</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($salidas)): ?>
              <tr>
                <td colspan="8" class="tabla-vacia">
                  <i class="fa-solid fa-arrow-up-from-bracket"></i>
                  <p>Sin salidas registradas</p>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach($salidas as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['numero_movimiento'] ?? '-') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($s['fecha_movimiento'])) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <?php if(!empty($s['imagen_path'])): ?>
                                <img src="../<?= htmlspecialchars($s['imagen_path']) ?>" alt="img" style="width:30px;height:30px;border-radius:4px;object-fit:cover;border:1px solid #ddd;"/>
                            <?php else: ?>
                                <div style="width:30px;height:30px;border-radius:4px;background:var(--color-border);display:flex;justify-content:center;align-items:center;color:white;font-size:0.7rem;"><i class="fa-solid fa-pills"></i></div>
                            <?php endif; ?>
                            <div>
                                <strong><?= htmlspecialchars($s['producto_nombre']) ?></strong>
                                <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($s['producto_codigo']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="chip" style="background:#fff3cd;color:#856404;font-weight:600;padding:2px 8px;border-radius:12px;"><?= ucfirst(htmlspecialchars($s['tipo_salida'] ?? $s['motivo'] ?? '-')) ?></span></td>
                    <td class="text-center" style="color:var(--color-danger);font-weight:bold;">-<?= $s['cantidad'] ?></td>
                    <td><?= htmlspecialchars($s['notas'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($s['usuario'] ?? '-') ?></td>
                    <td><button type="button" class="btn-accion"><i class="fa-solid fa-eye"></i></button></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<div class="paginacion">
          <p class="paginacion-info">Mostrando <?= count($salidas) ?> salidas</p>
        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
