<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Cierre del Día';
$menuActivo = 'cierre';

$hoy = date('Y-m-d');

// Fetch ventas de hoy
$sqlVentas = "SELECT v.*, c.nombre as cliente_nombre 
              FROM ventas v 
              LEFT JOIN clientes c ON v.cliente_id = c.id 
              WHERE DATE(v.fecha_venta) = :hoy
              ORDER BY v.fecha_venta DESC";
$stmtVentas = $pdo->prepare($sqlVentas);
$stmtVentas->execute([':hoy' => $hoy]);
$ventasDia = $stmtVentas->fetchAll();

// Estadísticas del día
$totalDia = 0;
$numVentas = count($ventasDia);
$clientesIds = [];
$productosVendidos = 0;

if ($numVentas > 0) {
    // Calcular totales y clientes únicos
    foreach ($ventasDia as $v) {
        if ($v['estado'] != 'cancelada') {
            $totalDia += $v['total'];
            if ($v['cliente_id']) {
                $clientesIds[$v['cliente_id']] = true;
            }
        }
    }
    
    // Calcular total de productos vendidos hoy
    $stmtProd = $pdo->prepare("SELECT SUM(dv.cantidad) 
                               FROM detalle_ventas dv 
                               JOIN ventas v ON dv.venta_id = v.id 
                               WHERE DATE(v.fecha_venta) = :hoy AND v.estado != 'cancelada'");
    $stmtProd->execute([':hoy' => $hoy]);
    $productosVendidos = $stmtProd->fetchColumn() ?: 0;
}
$numClientes = count($clientesIds);

// Manejar finalización de jornada (Checkout)
if (isset($_POST['finalizar_jornada'])) {
    $stmtOut = $pdo->prepare("UPDATE asistencia SET hora_salida = NOW() WHERE usuario_id = :id AND fecha = :hoy");
    if ($stmtOut->execute([':id' => $_SESSION['usuario_id'], ':hoy' => $hoy])) {
        $mensaje_asistencia = "Jornada finalizada correctamente. ¡Hasta mañana!";
    }
}

// Verificar si ya hizo checkout
$stmtCheckOut = $pdo->prepare("SELECT hora_salida FROM asistencia WHERE usuario_id = :id AND fecha = :hoy");
$stmtCheckOut->execute([':id' => $_SESSION['usuario_id'], ':hoy' => $hoy]);
$asistenciaHoy = $stmtCheckOut->fetch();
$yaFinalizo = !empty($asistenciaHoy['hora_salida']);

// Manejar Registro de Corte de Caja
if (isset($_POST['registrar_corte'])) {
    $total_sistema = floatval($_POST['total_sistema']);
    $total_contado = floatval($_POST['total_contado']);
    $notas = trim($_POST['notas'] ?? '');
    
    $stmtCorte = $pdo->prepare("
        INSERT INTO cortes_caja (usuario_id, fecha_corte, total_sistema, total_contado, notas)
        VALUES (:uid, :fecha, :sis, :cont, :not)
        ON DUPLICATE KEY UPDATE 
            total_sistema = VALUES(total_sistema),
            total_contado = VALUES(total_contado),
            notas = VALUES(notas)
    ");
    $stmtCorte->execute([
        ':uid' => $_SESSION['usuario_id'],
        ':fecha' => $hoy,
        ':sis' => $total_sistema,
        ':cont' => $total_contado,
        ':not' => $notas
    ]);
    
    $mensaje_corte = "Corte de caja registrado exitosamente.";
}

// Obtener el corte actual si existe
$stmtExistingCorte = $pdo->prepare("SELECT * FROM cortes_caja WHERE usuario_id = :uid AND fecha_corte = :hoy");
$stmtExistingCorte->execute([':uid' => $_SESSION['usuario_id'], ':hoy' => $hoy]);
$corteHoy = $stmtExistingCorte->fetch();

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Cierre del Día (PHP)</h2>
          <p class="pagina-subtitulo"><?= date('l, d F Y') ?></p>
        </div>
        <div class="acciones-header">
          <button class="btn-imprimir" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            Imprimir resumen
          </button>
        </div>
      </div>
<div class="estado-dia" id="estado-dia">
        <div class="estado-icono abierto">
          <i class="fa-solid fa-store"></i>
        </div>
        <div>
          <p class="estado-titulo">Día en curso</p>
          <p class="estado-subtitulo">Las ventas del día aún pueden registrarse</p>
        </div>
      </div>

      <?php if(isset($mensaje_asistencia)): ?>
      <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
          <i class="fa-solid fa-circle-check"></i> <?= $mensaje_asistencia ?>
      </div>
      <?php endif; ?>

      <div class="card-cierre" style="margin-bottom:24px; padding:20px; display:flex; justify-content:space-between; align-items:center; background:#f8fafc;">
          <div>
            <h3 style="margin:0; font-size:1.1rem; color:var(--text-dark);">Asistencia y Turno</h3>
            <p style="margin:5px 0 0 0; font-size:0.85rem; color:var(--text-muted);">Registra tu salida oficial para el cálculo de nómina.</p>
          </div>
          <?php if($yaFinalizo): ?>
            <div style="color:#10b981; font-weight:bold; background:#dcfce7; padding:10px 20px; border-radius:8px;">
               <i class="fa-solid fa-check-double"></i> Jornada Finalizada (<?= date('H:i', strtotime($asistenciaHoy['hora_salida'])) ?>)
            </div>
          <?php else: ?>
            <form method="POST">
               <button type="submit" name="finalizar_jornada" class="btn-primario" style="background:#ef4444;">
                 <i class="fa-solid fa-clock-rotate-left"></i> Finalizar Jornada (Check-out)
               </button>
            </form>
          <?php endif; ?>
      </div>

      
      <div class="layout-cierre">

        
        <div class="panel-resumen-dia">
<div class="card-cierre resumen-oscuro">
            <h3 class="card-titulo-cierre blanco">Resumen del día</h3>

            <div class="linea-cierre">
              <span>Total de ventas</span>
              <span><?= $numVentas ?></span>
            </div>
            <div class="linea-cierre">
              <span>Productos vendidos</span>
              <span><?= $productosVendidos ?></span>
            </div>
            <div class="linea-cierre">
              <span>Clientes difer. atendidos</span>
              <span><?= $numClientes ?></span>
            </div>

            <div class="separador-cierre"></div>

            <div class="linea-cierre grande">
              <span>Total del día</span>
<span>$<?= number_format($totalDia, 2) ?></span>
            </div>
          </div>


        </div>

        
        <div class="panel-ventas-dia">
          <div class="card-cierre">
            <div class="card-header-cierre">
              <h3 class="card-titulo-cierre">Ventas del día</h3>
              <span class="conteo-ventas-dia"><?= $numVentas ?> ventas</span>
            </div>

            <div class="tabla-wrapper">
              <table class="tabla-cierre">
                <thead>
                  <tr>
                    <th>Folio</th>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Total</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(empty($ventasDia)): ?>
                  <tr>
                    <td colspan="6" class="tabla-vacia">
                      <i class="fa-solid fa-receipt"></i>
                      <p>Sin ventas registradas hoy</p>
                    </td>
                  </tr>
                  <?php else: ?>
                    <?php foreach($ventasDia as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['numero_venta']) ?></strong></td>
                        <td><?= date('H:i', strtotime($v['fecha_venta'])) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Mostrador') ?></td>
                        <td><span class="chip <?= $v['estado'] == 'cancelada' ? 'sin-stock' : 'en-stock' ?>"><?= ucfirst(htmlspecialchars($v['estado'])) ?></span></td>
                        <td><strong>$<?= number_format($v['total'], 2) ?></strong></td>
                        <td><a href="nota_venta.php?id=<?= $v['id'] ?>" class="btn-accion"><i class="fa-solid fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
<div class="pie-tabla-cierre">
              <span class="pie-label">Total efectivo del día:</span>
              <span class="pie-total">$<?= number_format($totalDia, 2) ?></span>
            </div>

          </div>
        </div>

      </div>

      <!-- SECCIÓN DE CORTE DE CAJA FÍSICO -->
      <div class="card-cierre" style="margin-top:24px; padding:20px; background:#fff; border-top: 4px solid var(--azul-medio);">
          <h3 style="margin-top:0; margin-bottom:15px; color:var(--text-dark);">
              <i class="fa-solid fa-cash-register"></i> Corte de Caja Físico
          </h3>
          
          <?php if(isset($mensaje_corte)): ?>
          <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
              <i class="fa-solid fa-circle-check"></i> <?= $mensaje_corte ?>
          </div>
          <?php endif; ?>

          <?php if ($corteHoy): ?>
              <?php
                  $dif = $corteHoy['diferencia'];
                  $colorFondo = $dif >= 0 ? '#dcfce7' : '#fee2e2';
                  $colorTexto = $dif >= 0 ? '#166534' : '#b91c1c';
                  $icono = $dif >= 0 ? 'fa-check-circle' : 'fa-triangle-exclamation';
              ?>
              <div style="background:<?= $colorFondo ?>; color:<?= $colorTexto ?>; padding:15px; border-radius:8px; margin-bottom:20px;">
                  <h4 style="margin:0 0 10px 0;"><i class="fa-solid <?= $icono ?>"></i> Resumen del Corte Registrado</h4>
                  <ul style="margin:0; padding-left:20px; font-size:0.95rem;">
                      <li>Total del sistema: <strong>$<?= number_format($corteHoy['total_sistema'], 2) ?></strong></li>
                      <li>Efectivo físico ingresado: <strong>$<?= number_format($corteHoy['total_contado'], 2) ?></strong></li>
                      <li>Diferencia detectada: <strong>$<?= number_format($dif, 2) ?></strong></li>
                  </ul>
                  <?php if (!empty($corteHoy['notas'])): ?>
                      <p style="margin:10px 0 0 0; font-size:0.9rem;"><em>Notas: <?= htmlspecialchars($corteHoy['notas']) ?></em></p>
                  <?php endif; ?>
              </div>
          <?php endif; ?>

          <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; align-items:start;">
              <div class="campo-grupo">
                  <label class="campo-label" style="font-weight:bold;">Total del sistema (Suma automática)</label>
                  <input type="text" class="campo-input" name="total_sistema" value="<?= number_format($totalDia, 2, '.', '') ?>" readonly style="background:#f1f5f9; color:#64748b; font-weight:bold; cursor:not-allowed;">
              </div>
              
              <div class="campo-grupo">
                  <label class="campo-label" for="total_contado" style="font-weight:bold;">Efectivo contado en caja *</label>
                  <div class="input-prefijo">
                      <span class="prefijo">$</span>
                      <input type="number" step="0.01" min="0" class="campo-input" id="total_contado" name="total_contado" value="<?= $corteHoy['total_contado'] ?? '' ?>" required placeholder="0.00">
                  </div>
              </div>

              <div class="campo-grupo full" style="grid-column: 1 / -1;">
                  <label class="campo-label" for="notas">Notas (Opcional)</label>
                  <textarea class="campo-input campo-textarea" id="notas" name="notas" rows="2" placeholder="Agrega alguna observación de la caja..."><?= htmlspecialchars($corteHoy['notas'] ?? '') ?></textarea>
              </div>

              <div style="grid-column: 1 / -1; display:flex; justify-content:flex-end;">
                  <button type="submit" name="registrar_corte" class="btn-primario">
                      <i class="fa-solid fa-floppy-disk"></i> Registrar y Calcular Diferencia
                  </button>
              </div>
          </form>
      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
