<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
requiereRol(['admin', 'encargado']);
$paginaTitulo = 'Reportes';
$menuActivo = 'reportes';

$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$inicioMes = date('Y-m-01');
$inicioAnio = date('Y-01-01');

// Periodo del filtro activo
$periodo = $_GET['periodo'] ?? 'semana';
if ($periodo == 'dia')         $fechaFiltro = $hoy;
elseif ($periodo == 'semana')  $fechaFiltro = $inicioSemana;
else                           $fechaFiltro = $inicioMes;

// Tarjetas superiores por periodo — prepared statements
$stmtRV = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE estado='completada' AND DATE(fecha_venta) >= ?");
$stmtRV->execute([$fechaFiltro]);
$repVentas = $stmtRV->fetchColumn() ?: 0;

$stmtRF = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta) >= ?");
$stmtRF->execute([$fechaFiltro]);
$repFacturacion = $stmtRF->fetchColumn();

$stmtRP = $pdo->prepare("SELECT COALESCE(SUM(dv.cantidad),0) FROM detalle_ventas dv JOIN ventas v ON dv.venta_id = v.id WHERE v.estado='completada' AND DATE(v.fecha_venta) >= ?");
$stmtRP->execute([$fechaFiltro]);
$repProductos = $stmtRP->fetchColumn();

// Comparativa: ventas (monto) por hoy, ayer y promedio de esta semana
$stmtCH = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta)=?");
$stmtCH->execute([$hoy]); $compHoy = $stmtCH->fetchColumn();

$stmtCA = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta)=?");
$stmtCA->execute([$ayer]); $compAyer = $stmtCA->fetchColumn();

$stmtCS = $pdo->prepare("SELECT COALESCE(SUM(total)/7,0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta)>=?");
$stmtCS->execute([$inicioSemana]); $compSemana = $stmtCS->fetchColumn();

$maxComp = max($compHoy, $compAyer, $compSemana, 1);

// Por día (últimos 7 días) con balance — prepared statements
$porDia = [];
$stmtVD  = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta)=?");
$stmtAD  = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos_cuentas WHERE DATE(fecha_pago)=?");
$stmtCD  = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM entradas WHERE estado='recibida' AND DATE(fecha_entrada)=?");
for($i=6; $i>=0; $i--) {
    $fechaD = date('Y-m-d', strtotime("-$i days"));
    $stmtVD->execute([$fechaD]); $ventasD = $stmtVD->fetchColumn();
    $stmtAD->execute([$fechaD]); $abonosD = $stmtAD->fetchColumn();
    $stmtCD->execute([$fechaD]); $costosD = $stmtCD->fetchColumn();
    $tieneVentas = ($ventasD > 0 || $abonosD > 0);
    
    $porDia[] = [
        'fecha'    => $fechaD,
        'ingresos' => $ventasD + $abonosD,
        'costos'   => $costosD,
        'balance'  => ($ventasD + $abonosD) - $costosD,
        'con_ventas' => $tieneVentas
    ];
}
$totalMontoDia = array_sum(array_column($porDia, 'ingresos'));
// Días con al menos una venta en los últimos 7 días
$totalNumDia = count(array_filter($porDia, fn($d) => $d['con_ventas']));

// Por semana (últimas 4 semanas) con balance sumado — prepared statements
$porSemana = [];
$stmS = $pdo->query("SELECT YEARWEEK(fecha_venta,1) as sem, MIN(DATE(fecha_venta)) as fecha_inicio, COALESCE(SUM(total),0) as vtotal FROM ventas WHERE estado='completada' AND fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK) GROUP BY sem ORDER BY sem DESC");
$stmtAbSem = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos_cuentas WHERE DATE(fecha_pago) BETWEEN ? AND ?");
$stmtCoSem = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM entradas WHERE estado='recibida' AND DATE(fecha_entrada) BETWEEN ? AND ?");
while($s = $stmS->fetch()) {
    $fIn = $s['fecha_inicio'];
    $fFin = date('Y-m-d', strtotime($fIn . ' +6 days'));
    $stmtAbSem->execute([$fIn, $fFin]); $abSem = $stmtAbSem->fetchColumn();
    $stmtCoSem->execute([$fIn, $fFin]); $coSem = $stmtCoSem->fetchColumn();
    $porSemana[] = [
        'inicio'  => $fIn,
        'ventas'  => $s['vtotal'],
        'abonos'  => $abSem,
        'costos'  => $coSem,
        'balance' => ($s['vtotal'] + $abSem) - $coSem
    ];
}

// Por mes (año actual) con balance — prepared statements
$porMes = [];
$stmtVMes = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta) BETWEEN ? AND ?");
$stmtAMes = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos_cuentas WHERE DATE(fecha_pago) BETWEEN ? AND ?");
$stmtCMes = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM entradas WHERE estado='recibida' AND DATE(fecha_entrada) BETWEEN ? AND ?");
for($m=12; $m>=1; $m--) {
    $primerD = date('Y-'.sprintf('%02d', $m).'-01');
    if ($primerD > date('Y-m-d')) continue;
    $ultimoD = date('Y-m-t', strtotime($primerD));
    
    $stmtVMes->execute([$primerD, $ultimoD]); $vMes = $stmtVMes->fetchColumn();
    $stmtAMes->execute([$primerD, $ultimoD]); $aMes = $stmtAMes->fetchColumn();
    $stmtCMes->execute([$primerD, $ultimoD]); $cMes = $stmtCMes->fetchColumn();
    
    if($vMes > 0 || $aMes > 0 || $cMes > 0) {
        $porMes[] = [
            'mes'     => $m,
            'ventas'  => $vMes,
            'abonos'  => $aMes,
            'costos'  => $cMes,
            'balance' => ($vMes + $aMes) - $cMes
        ];
    }
}
$mesesNombres = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];


// Más vendidos (últimos 30 días) — GROUP BY p.id para evitar duplicados
$masVendidos = $pdo->query("SELECT p.id, p.nombre, p.imagen_path, SUM(dv.cantidad) as unidades, COALESCE(SUM(dv.subtotal),0) as total FROM detalle_ventas dv JOIN ventas v ON dv.venta_id=v.id JOIN productos p ON dv.producto_id=p.id WHERE v.estado='completada' AND v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY p.id ORDER BY unidades DESC LIMIT 5")->fetchAll();


// Vendedor del mes
$vendedorMes = $pdo->query("SELECT u.nombre, COALESCE(SUM(v.total),0) as facturacion FROM ventas v JOIN usuarios u ON v.usuario_id=u.id WHERE v.estado='completada' AND MONTH(v.fecha_venta)=MONTH(CURDATE()) AND YEAR(v.fecha_venta)=YEAR(CURDATE()) GROUP BY u.id ORDER BY facturacion DESC LIMIT 1")->fetch();

// Ganancia Neta filtrada por periodo — prepared statements
$stmtTV = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado='completada' AND DATE(fecha_venta) >= ?");
$stmtTV->execute([$fechaFiltro]); $totalVentas = $stmtTV->fetchColumn();

$stmtTC = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM entradas WHERE estado='recibida' AND DATE(fecha_entrada) >= ?");
$stmtTC->execute([$fechaFiltro]); $totalCostos = $stmtTC->fetchColumn();

try {
    $stmtTR = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM retiros_caja WHERE DATE(fecha_retiro) >= ?");
    $stmtTR->execute([$fechaFiltro]); $totalRetiros = $stmtTR->fetchColumn() ?: 0;
} catch(Exception $e) {
    $totalRetiros = 0;
}

// Salarios calculados proporcionalmente al periodo seleccionado
$periodoDias = ($periodo == 'dia' ? 1 : ($periodo == 'semana' ? 7 : 30));
$stmtSalarios = $pdo->query("SELECT salario, destare, frecuencia_pago FROM usuarios WHERE estado='activo'");
$totalSalarios = 0;
while ($u = $stmtSalarios->fetch()) {
    $sal = ($u['salario'] ?? 0) - ($u['destare'] ?? 0);
    if ($sal <= 0) continue;
    $div = ($u['frecuencia_pago'] == 'semanal' ? 7 : ($u['frecuencia_pago'] == 'quincenal' ? 15 : 30));
    $proporcion = ($sal / $div) * $periodoDias;
    $totalSalarios += $proporcion;
}

// Abonos de clientes en el periodo (Ingreso adicional) — prepared statement
$stmtTA = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos_cuentas WHERE DATE(fecha_pago) >= ?");
$stmtTA->execute([$fechaFiltro]); $totalAbonos = $stmtTA->fetchColumn();


// Pagos a proveedores = igual que totalCostos (ya se calculó arriba)
$totalProveedores = $totalCostos;

// Deudores con saldo pendiente (esto es absoluto/actual)
$deudores = $pdo->query("SELECT id, nombre, telefono, correo, deuda_actual FROM clientes WHERE deuda_actual > 0 ORDER BY deuda_actual DESC")->fetchAll();
$totalDeudas = $pdo->query("SELECT COALESCE(SUM(deuda_actual),0) FROM clientes WHERE deuda_actual > 0")->fetchColumn();

// Balance real del periodo (Ventas + Abonos - Compras - Salarios - Retiros)
$balanceReal = $totalVentas + $totalAbonos - $totalCostos - $totalSalarios - $totalRetiros;

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Reportes (PHP)</h2>
          <p class="pagina-subtitulo">Ventas por día, semana y mes</p>
        </div>
        <div class="acciones-header">
<form method="GET" class="filtros-periodo" style="display:flex; gap:10px;">
            <button type="submit" name="periodo" value="dia" class="periodo-btn <?= $periodo=='dia'?'activo':'' ?>">Hoy</button>
            <button type="submit" name="periodo" value="semana" class="periodo-btn <?= $periodo=='semana'?'activo':'' ?>">Semana</button>
            <button type="submit" name="periodo" value="mes" class="periodo-btn <?= $periodo=='mes'?'activo':'' ?>">Mes</button>
          </form>
          <button class="btn-exportar-pdf" onclick="window.print()">
            <i class="fa-solid fa-file-pdf"></i>
            Imprimir Resumen
          </button>
      </div>

      <!-- RESUMEN SOLO PARA IMPRESIÓN (Simplificado para evitar errores de hoja en blanco) -->
      <div id="resumen-impresion" style="display: none;">
          <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px;">
              <h1 style="margin: 0; font-size: 24px;">REPORTE DE FARMACIA - <?= strtoupper($periodo) ?></h1>
              <p style="margin: 5px 0; color: #666;">Fecha de generación: <?= date('d/m/Y H:i') ?></p>
          </div>

          <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
              <tr>
                  <td style="padding: 15px; border: 1px solid #ddd; text-align: center;">
                      <div style="font-size: 12px; color: #666; text-transform: uppercase;">Ventas Totales</div>
                      <div style="font-size: 20px; font-weight: bold;">$<?= number_format($totalVentas, 2) ?></div>
                  </td>
                  <td style="padding: 15px; border: 1px solid #ddd; text-align: center;">
                      <div style="font-size: 12px; color: #666; text-transform: uppercase;">Abonos Recibidos</div>
                      <div style="font-size: 20px; font-weight: bold;">$<?= number_format($totalAbonos, 2) ?></div>
                  </td>
              </tr>
              <tr>
                  <td style="padding: 15px; border: 1px solid #ddd; text-align: center;">
                      <div style="font-size: 12px; color: #666; text-transform: uppercase;">Compras/Costos</div>
                      <div style="font-size: 20px; font-weight: bold; color: #e53e3e;">$<?= number_format($totalCostos, 2) ?></div>
                  </td>
                  <td style="padding: 15px; border: 1px solid #ddd; text-align: center;">
                      <div style="font-size: 12px; color: #666; text-transform: uppercase;">Gastos Nómina</div>
                      <div style="font-size: 20px; font-weight: bold; color: #e53e3e;">$<?= number_format($totalSalarios, 2) ?></div>
                  </td>
              </tr>
          </table>

          <div style="background: #f8fafc; padding: 20px; border: 2px solid #333; border-radius: 8px;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                  <span style="font-size: 18px; font-weight: bold;">BALANCE NETO REAL:</span>
                  <span style="font-size: 24px; font-weight: 900; color: <?= $balanceReal >= 0 ? '#10b981' : '#ef4444' ?>;">
                      $<?= number_format($balanceReal, 2) ?>
                  </span>
              </div>
          </div>
          
          <div style="margin-top: 30px; font-size: 12px; color: #999; text-align: center;">
              Documento generado automáticamente por el Sistema de Farmacia - MediClick
          </div>
      </div>

      <style>
      @media print {
          /* OCULTAR TODO EL SISTEMA */
          body * {
              visibility: hidden !important;
          }
          /* MOSTRAR SOLO EL RESUMEN */
          #resumen-impresion, #resumen-impresion * {
              visibility: visible !important;
          }
          #resumen-impresion {
              display: block !important;
              position: absolute !important;
              left: 0 !important;
              top: 0 !important;
              width: 100% !important;
              margin: 0 !important;
              padding: 0 !important;
          }
          html, body {
              background: #fff !important;
              overflow: visible !important;
              height: auto !important;
          }
      }
      </style>

      <!-- SECCIÓN DE GRÁFICAS -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
      <div style="display: block; margin-bottom: 24px; height: 400px;">
          <div class="card-reporte" style="padding:20px;">
              <h3 class="card-titulo-rep">Balance General Financiero (<?= ucfirst($periodo) ?>)</h3>
              <div style="height:320px;"><canvas id="chartBalance"></canvas></div>
          </div>
      </div>
<div class="tarjetas-reporte">
        <div class="tarjeta-reporte">
          <div class="rep-header">
            <p class="rep-etiqueta">Ventas</p>
            <a href="../cajero/ventas.php" class="rep-link">Ver reporte</a>
          </div>
<p class="rep-valor"><?= $repVentas ?></p>
        </div>

        <div class="tarjeta-reporte">
          <div class="rep-header">
            <p class="rep-etiqueta">Facturación</p>
            <a href="../cajero/ventas.php" class="rep-link">Ver reporte</a>
          </div>
          <p class="rep-valor">$<?= number_format($repFacturacion, 2) ?></p>
        </div>

        <div class="tarjeta-reporte">
          <div class="rep-header">
            <p class="rep-etiqueta">Productos vendidos</p>
            <a href="../farmaceutico/productos.php" class="rep-link">Ver reporte</a>
          </div>
          <p class="rep-valor"><?= $repProductos ?></p>
        </div>
      </div>
<div class="fila-principal">
<div class="card-reporte grande">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Ventas del período</h3>
            <div class="leyenda-grafica">
              <span class="leyenda-punto turquesa"></span>
              <span class="leyenda-texto">Ventas</span>
            </div>
          </div>
          <div class="grafica-wrapper-grande">
            <canvas id="grafica-principal"></canvas>
          </div>
        </div>

        
        <div class="card-reporte chica">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Por día (Últ 7 días)</h3>
          </div>
          <div class="tabla-wrapper">
            <table class="tabla-rep">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Ingresos</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($porDia)): ?>
                <tr>
                  <td colspan="3" class="tabla-vacia">Sin datos</td>
                </tr>
                <?php else: ?>
                    <?php foreach($porDia as $d): ?>
                    <tr>
                        <td><?= date('d/M', strtotime($d['fecha'])) ?></td>
                        <td>$<?= number_format($d['ingresos'], 2) ?></td>
                        <td style="font-weight:bold; color:<?= $d['balance']>=0?'#10b981':'#ef4444' ?>;">
                            $<?= number_format($d['balance'], 2) ?>
                            <?php if($d['balance'] < 0): ?>
                            <span title="Los costos de compras superaron los ingresos en este período" style="cursor:help; color:#f59e0b; margin-left:4px;">ⓘ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr class="fila-total">
                  <td>Total</td>
                  <td><?= $totalNumDia ?></td>
                  <td class="total-verde">$<?= number_format($totalMontoDia, 2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>

      
      <div class="fila-inferior-rep">
<div class="card-reporte">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Por semana</h3>
            <span class="chip-periodo semana">Últimas 4</span>
          </div>
          <div class="tabla-wrapper">
            <table class="tabla-rep">
              <thead>
                <tr>
                  <th>Inicio semana</th>
                  <th>Ingresos</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($porSemana)): ?>
                <tr>
                  <td colspan="3" class="tabla-vacia">Sin datos</td>
                </tr>
                <?php else: ?>
                    <?php foreach($porSemana as $s): ?>
                    <tr>
                        <td><?= date('d/M', strtotime($s['inicio'])) ?></td>
                        <td>$<?= number_format($s['ventas'] + $s['abonos'], 2) ?></td>
                        <td style="font-weight:bold; color:<?= $s['balance']>=0?'#10b981':'#ef4444' ?>;">
                            $<?= number_format($s['balance'], 2) ?>
                            <?php if($s['balance'] < 0): ?>
                            <span title="Los costos de compras superaron los ingresos en este período" style="cursor:help; color:#f59e0b; margin-left:4px;">ⓘ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
<div class="card-reporte">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Por mes</h3>
            <span class="chip-periodo mes">Año actual</span>
          </div>
          <div class="tabla-wrapper">
            <table class="tabla-rep">
              <thead>
                <tr>
                  <th>Mes</th>
                  <th>Ingresos</th>
                  <th>Balance</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($porMes)): ?>
                <tr>
                  <td colspan="3" class="tabla-vacia">Sin datos</td>
                </tr>
                <?php else: ?>
                    <?php foreach($porMes as $m): ?>
                    <tr>
                        <td><?= $mesesNombres[$m['mes']] ?></td>
                        <td>$<?= number_format($m['ventas'] + $m['abonos'], 2) ?></td>
                        <td style="font-weight:bold; color:<?= $m['balance']>=0?'#10b981':'#ef4444' ?>;">
                            $<?= number_format($m['balance'], 2) ?>
                            <?php if($m['balance'] < 0): ?>
                            <span title="Los costos de compras superaron los ingresos en este período" style="cursor:help; color:#f59e0b; margin-left:4px;">ⓘ</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
<div class="card-reporte">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Más vendidos</h3>
            <span class="chip-periodo">Últ. 30 días</span>
          </div>
          <div class="tabla-wrapper">
            <table class="tabla-rep">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Unidades</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($masVendidos)): ?>
                <tr>
                  <td colspan="3" class="tabla-vacia">Sin datos</td>
                </tr>
                <?php else: ?>
                    <?php foreach($masVendidos as $mv): ?>
                    <tr>
                        <td>
                          <div style="display:flex;align-items:center;gap:10px;">
                              <?php if(!empty($mv['imagen_path'])): ?>
                                  <img src="../<?= htmlspecialchars($mv['imagen_path']) ?>" alt="img" style="width:24px;height:24px;border-radius:4px;object-fit:cover;border:1px solid #ddd;"/>
                              <?php else: ?>
                                  <div style="width:24px;height:24px;border-radius:4px;background:var(--color-border);display:flex;justify-content:center;align-items:center;color:white;font-size:0.6rem;"><i class="fa-solid fa-pills"></i></div>
                              <?php endif; ?>
                              <span><?= htmlspecialchars($mv['nombre']) ?></span>
                          </div>
                        </td>
                        <td><?= $mv['unidades'] ?></td>
                        <td>$<?= number_format($mv['total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

<div class="fila-inferior-rep" style="margin-top: 24px;">
<div class="card-reporte">
            <h3 class="card-titulo-rep">Comparativa</h3>
            <div class="comparativa-item">
              <div class="comp-info">
                <p class="comp-etiqueta">Ayer</p>
                <p class="comp-valor">$<?= number_format($compAyer, 2) ?></p>
              </div>
              <div class="comp-barra-wrap">
                <div class="comp-barra" style="width: <?= ($compAyer/$maxComp)*100 ?>%"></div>
              </div>
            </div>
            <div class="comparativa-item">
              <div class="comp-info">
                <p class="comp-etiqueta">Hoy</p>
                <p class="comp-valor turquesa">$<?= number_format($compHoy, 2) ?></p>
              </div>
              <div class="comp-barra-wrap">
                <div class="comp-barra turquesa" style="width: <?= ($compHoy/$maxComp)*100 ?>%"></div>
              </div>
            </div>
            <div class="comparativa-item">
              <div class="comp-info">
                <p class="comp-etiqueta">Promedio semana (por día)</p>
                <p class="comp-valor">$<?= number_format($compSemana, 2) ?></p>
              </div>
              <div class="comp-barra-wrap">
                <div class="comp-barra azul" style="width: <?= ($compSemana/$maxComp)*100 ?>%"></div>
              </div>
            </div>
          </div>

          <!-- Vendedor del Mes y Balance Simplificado -->
          <div class="card-reporte">
            <h3 class="card-titulo-rep">Vendedor del Mes</h3>
            <?php if($vendedorMes): ?>
              <div style="text-align:center; padding:20px;">
                <div style="font-size:3rem; color:var(--color-primary);"><i class="fa-solid fa-award"></i></div>
                <p style="font-weight:bold; font-size:1.2rem; margin:10px 0;"><?= htmlspecialchars($vendedorMes['nombre']) ?></p>
                <p style="color:var(--text-muted);">Facturación: <strong>$<?= number_format($vendedorMes['facturacion'], 2) ?></strong></p>
              </div>
            <?php else: ?>
              <p class="tabla-vacia">Sin datos este mes</p>
            <?php endif; ?>
          </div>

          <!-- Balance Rápido eliminado: se usa solo el Balance General completo abajo -->
      </div>

      <!-- SECCIÓN DE DEUDORES Y NÓMINA -->
      <div style="margin-top: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="card-reporte">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Deudores Pendientes</h3>
          </div>
          <div class="tabla-wrapper">
             <table class="tabla-rep">
               <thead>
                 <tr>
                   <th>Cliente</th>
                   <th>Teléfono</th>
                   <th>Deuda</th>
                 </tr>
               </thead>
               <tbody>
                  <?php if(empty($deudores)): ?>
                    <tr><td colspan="3" class="tabla-vacia">Sin deudores</td></tr>
                  <?php else: ?>
                    <?php foreach($deudores as $d): ?>
                    <tr>
                      <td><?= htmlspecialchars($d['nombre']) ?></td>
                      <td><?= htmlspecialchars($d['telefono'] ?? '-') ?></td>
                      <td style="color:#ef4444; font-weight:bold;">$<?= number_format($d['deuda_actual'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
               </tbody>
               <tfoot>
                 <tr class="fila-total">
                   <td colspan="2">Total Deuda</td>
                   <td style="color:#ef4444;">$<?= number_format($totalDeudas, 2) ?></td>
                 </tr>
               </tfoot>
             </table>
          </div>
        </div>

        <div class="card-reporte">
          <div class="card-header-rep">
            <h3 class="card-titulo-rep">Nómina de Empleados</h3>
          </div>
          <div class="tabla-wrapper">
             <?php 
               $empleadosSal = $pdo->query("SELECT nombre, salario, destare FROM usuarios WHERE estado='activo'")->fetchAll();
             ?>
             <table class="tabla-rep">
               <thead>
                 <tr>
                   <th>Nombre</th>
                   <th>Salario Base</th>
                   <th>Destare</th>
                   <th>Neto</th>
                 </tr>
               </thead>
               <tbody>
                  <?php foreach($empleadosSal as $e): ?>
                  <tr>
                    <td><?= htmlspecialchars($e['nombre']) ?></td>
                    <td>$<?= number_format($e['salario'] ?? 0, 2) ?></td>
                    <td style="color:#ef4444;">-$<?= number_format($e['destare'] ?? 0, 2) ?></td>
                    <td style="font-weight:bold; color:#10b981;">$<?= number_format(($e['salario'] ?? 0) - ($e['destare'] ?? 0), 2) ?></td>
                  </tr>
                  <?php endforeach; ?>
               </tbody>
               <tfoot>
                 <tr class="fila-total">
                   <td colspan="3">Total Nómina</td>
                   <td style="color:#10b981;">$<?= number_format($totalSalarios, 2) ?></td>
                 </tr>
               </tfoot>
             </table>
          </div>
        </div>
      </div>

      <!-- BALANCE GENERAL COMPLETO -->
      <div style="margin-top: 24px;">
          <div class="card-reporte" style="max-width: 600px; margin: 0 auto;">
            <h3 class="card-titulo-rep" style="text-align:center; margin-bottom: 20px;">Balance General (<?= ucfirst($periodo) ?>)</h3>
             <div class="balance-detallado">
                <style>
                  .balance-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                  .balance-item:last-child { border-bottom: none; }
                  .balance-item.total { margin-top: 15px; border-top: 2px solid #333; font-weight: bold; font-size: 1.2rem; }
                  .pos { color: #10b981; }
                  .neg { color: #ef4444; }
                </style>
                <div class="balance-item">
                  <span>(+) Ventas del periodo</span>
                  <span class="pos">$<?= number_format($totalVentas, 2) ?></span>
                </div>
                <div class="balance-item">
                  <span>(+) Abonos de Cuentas</span>
                  <span class="pos">$<?= number_format($totalAbonos, 2) ?></span>
                </div>
                <div class="balance-item">
                  <span>(-) Compras de Inventario</span>
                  <span class="neg">$<?= number_format($totalCostos, 2) ?></span>
                </div>
                <div class="balance-item">
                  <span>(-) Proyectado Nómina</span>
                  <span class="neg">$<?= number_format($totalSalarios, 2) ?></span>
                </div>
                <div class="balance-item">
                  <span>(-) Retiros de Caja</span>
                  <span class="neg">$<?= number_format($totalRetiros, 2) ?></span>
                </div>
                <div class="balance-item total">
                  <span>Balance Real</span>
                  <?php $final = $totalVentas + $totalAbonos - $totalCostos - $totalSalarios - $totalRetiros; ?>
                  <span class="<?= $final >= 0 ? 'pos' : 'neg' ?>">$<?= number_format($final, 2) ?></span>
                </div>
                <div class="balance-item" style="background: #f8fafc; padding: 10px; margin-top: 15px; border-radius: 8px;">
                  <span>Cuentas por Cobrar (Deudores)</span>
                  <span style="color: #f59e0b; font-weight: bold;">$<?= number_format($totalDeudas, 2) ?></span>
                </div>
             </div>
         </div>
      </div>

    
</main>

<?php
// Reporte finalizado
?>
<script>
<?php
$fechasGraf = []; $ingresosGraf = []; $costosGraf = []; $balanceGraf = [];
foreach($porDia as $pd) {
    $fechasGraf[] = date('d M', strtotime($pd['fecha']));
    $ingresosGraf[] = $pd['ingresos'];
    $costosGraf[] = $pd['costos'];
    $balanceGraf[] = $pd['balance'];
}
?>
const ctxBalance = document.getElementById('chartBalance').getContext('2d');
new Chart(ctxBalance, {
    type: 'bar',
    data: {
        labels: <?= json_encode($fechasGraf) ?>,
        datasets: [
            {
                label: 'Ingresos ($)',
                data: <?= json_encode($ingresosGraf) ?>,
                backgroundColor: '#2bbba0',
                borderRadius: 5
            },
            {
                label: 'Costos/Compras ($)',
                data: <?= json_encode($costosGraf) ?>,
                backgroundColor: '#ef4444',
                borderRadius: 5
            },
            {
                label: 'Balance Neta ($)',
                data: <?= json_encode($balanceGraf) ?>,
                type: 'line',
                borderColor: '#3b82f6',
                backgroundColor: '#3b82f6',
                borderWidth: 3,
                fill: false,
                tension: 0.3
            }
        ]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
