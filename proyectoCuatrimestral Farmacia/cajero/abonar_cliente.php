<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: clientes.php'); exit; }

$cli = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$cli->execute([$id]);
$cli = $cli->fetch();
if (!$cli) { header('Location: clientes.php'); exit; }

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto = floatval($_POST['monto'] ?? 0);
    $metodo = sanitize($_POST['metodo_pago'] ?? 'efectivo');
    $notas = sanitize($_POST['notas'] ?? '');

    if ($monto <= 0) {
        $error = "El monto a abonar debe ser mayor a cero.";
    } elseif ($monto > floatval($cli['deuda_actual'])) {
        $error = "El abono ($" . number_format($monto, 2) . ") no puede ser mayor que la deuda actual ($" . number_format($cli['deuda_actual'], 2) . ").";
    } else {
        try {
            $pdo->beginTransaction();
            
            // 1. Registrar el pago
            $stmtPago = $pdo->prepare("INSERT INTO pagos_cuentas (cliente_id, usuario_id, monto, metodo_pago, notas) VALUES (?, ?, ?, ?, ?)");
            $stmtPago->execute([$id, $_SESSION['usuario_id'], $monto, $metodo, $notas]);
            
            // 2. Restar de la deuda del cliente
            $stmtUpdate = $pdo->prepare("UPDATE clientes SET deuda_actual = deuda_actual - ? WHERE id = ?");
            $stmtUpdate->execute([$monto, $id]);
            
            $pdo->commit();
            
            $_SESSION['exito'] = "Abono de $" . number_format($monto, 2) . " registrado correctamente para " . $cli['nombre'];
            header('Location: clientes.php');
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Error al procesar el pago: " . $e->getMessage();
        }
    }
}

$paginaTitulo = 'Registrar Abono';
$menuActivo = 'clientes';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">
  <div class="pagina-header">
    <div>
      <h2 class="pagina-titulo-inner">Registrar Abono / Pago</h2>
      <p class="pagina-subtitulo">Cliente: <?= htmlspecialchars($cli['nombre']) ?></p>
    </div>
    <a href="clientes.php" class="btn-cancelar"><i class="fa-solid fa-arrow-left"></i> Volver</a>
  </div>

  <div class="layout-cliente">
    <div class="panel-form-cliente" style="max-width: 500px; margin: 0 auto;">
      
      <div class="card-pago-info" style="background:var(--color-primary); color:white; padding:20px; border-radius:12px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
          <div>
              <p style="margin:0; opacity:0.8; font-size:0.9rem;">Deuda Pendiente</p>
              <h1 style="margin:0; font-size:2rem;">$<?= number_format($cli['deuda_actual'], 2) ?></h1>
          </div>
          <div style="font-size:3rem; opacity:0.3;"><i class="fa-solid fa-hand-holding-dollar"></i></div>
      </div>

      <?php if($error): ?>
      <div style="background:#fee2e2; color:#b91c1c; padding:12px 20px; border-radius:8px; margin-bottom:20px;">
          <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
      </div>
      <?php endif; ?>

      <form class="card-form" method="POST">
          <div class="seccion-form">
              <div class="campo-grupo">
                  <label class="campo-label">Monto a abonar ($) <span class="requerido">*</span></label>
                  <input type="number" class="campo-input" name="monto" value="<?= $cli['deuda_actual'] ?>" step="0.01" min="0.01" max="<?= $cli['deuda_actual'] ?>" required style="font-size:1.5rem; font-weight:bold; height:60px; text-align:center; color:var(--color-primary);">
                  <p style="font-size:0.8rem; color:var(--text-muted); margin-top:5px; text-align:center;">Para liquidar la cuenta, mantén el monto total.</p>
              </div>
              
              <div class="form-fila">
                  <div class="campo-grupo">
                      <label class="campo-label">Método de Pago</label>
                      <select class="campo-input" name="metodo_pago" required>
                          <option value="efectivo">Efectivo</option>
                          <option value="tarjeta">Tarjeta</option>
                          <option value="transferencia">Transferencia</option>
                      </select>
                  </div>
              </div>

              <div class="campo-grupo">
                  <label class="campo-label">Notas u Observaciones (Opcional)</label>
                  <textarea class="campo-input" name="notas" placeholder="Ej: Pago parcial de la semana..." rows="2"></textarea>
              </div>
          </div>

          <div class="form-btns" style="margin-top:10px;">
              <button type="submit" class="btn-guardar" style="width:100%; height:50px; font-size:1.1rem; justify-content:center;">
                  <i class="fa-solid fa-check-circle"></i> Confirmar Pago
              </button>
          </div>
      </form>
    </div>
  </div>
</main>

<?php include '../includes/footer.php'; ?>
