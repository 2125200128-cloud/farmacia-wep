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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = sanitize($_POST['nombre']);
    $apellido  = sanitize($_POST['apellido']);
    $correo    = sanitize($_POST['correo']);
    $telefono  = sanitize($_POST['telefono']);
    $rfc       = sanitize($_POST['rfc']);
    $direccion = sanitize($_POST['direccion']);

    try {
        $pdo->prepare("UPDATE clientes SET nombre=?, apellido=?, correo=?, telefono=?, rfc=?, direccion=? WHERE id=?")
            ->execute([$nombre, $apellido, $correo, $telefono, $rfc, $direccion, $id]);
        $_SESSION['exito'] = 'Cliente actualizado correctamente.';
        header('Location: clientes.php');
        exit;
    } catch(PDOException $e) {
        $error = 'Error al actualizar: ' . $e->getMessage();
    }
}

$paginaTitulo = 'Editar Cliente';
$menuActivo = 'clientes';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="contenido-main">
  <div class="pagina-header">
    <div>
      <h2 class="pagina-titulo-inner">Editar Cliente</h2>
      <p class="pagina-subtitulo"><?= htmlspecialchars($cli['nombre']) ?></p>
    </div>
    <a href="clientes.php" class="btn-cancelar"><i class="fa-solid fa-arrow-left"></i> Volver</a>
  </div>

  <?php if($error): ?>
  <div style="background:#fee2e2;color:#b91c1c;padding:12px 20px;border-radius:8px;margin-bottom:20px;">
    <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
  </div>
  <?php endif; ?>

  <div class="layout-cliente">
    <div class="panel-form-cliente">
      <form class="card-form" method="POST">
        <div class="seccion-form">
          <h3 class="seccion-titulo"><i class="fa-solid fa-user"></i> Datos del cliente</h3>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">Nombre <span class="requerido">*</span></label>
              <input type="text" class="campo-input" name="nombre" value="<?= htmlspecialchars($cli['nombre']) ?>" required>
            </div>
            <div class="campo-grupo">
              <label class="campo-label">Apellido</label>
              <input type="text" class="campo-input" name="apellido" value="<?= htmlspecialchars($cli['apellido'] ?? '') ?>">
            </div>
          </div>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">RFC</label>
              <input type="text" class="campo-input" name="rfc" value="<?= htmlspecialchars($cli['rfc'] ?? '') ?>" maxlength="13">
            </div>
            <div class="campo-grupo">
              <label class="campo-label">Teléfono</label>
              <input type="tel" class="campo-input" name="telefono" value="<?= htmlspecialchars($cli['telefono'] ?? '') ?>">
            </div>
          </div>
          <div class="campo-grupo full">
            <label class="campo-label">Correo electrónico</label>
            <input type="email" class="campo-input" name="correo" value="<?= htmlspecialchars($cli['correo'] ?? '') ?>">
          </div>
          <div class="campo-grupo full">
            <label class="campo-label">Dirección</label>
            <textarea class="campo-input campo-textarea" name="direccion" rows="2"><?= htmlspecialchars($cli['direccion'] ?? '') ?></textarea>
          </div>
          <div style="padding:10px;background:#f8fafc;border-radius:8px;margin-top:10px;">
            <p style="margin:0;font-size:0.85rem;color:#64748b;">Deuda actual: <strong style="color:<?= $cli['deuda_actual']>0?'#ef4444':'#10b981' ?>">$<?= number_format($cli['deuda_actual']??0,2) ?></strong></p>
          </div>
        </div>
        <div class="form-btns">
          <a href="clientes.php" class="btn-cancelar-form">Cancelar</a>
          <button type="submit" class="btn-guardar">
            <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
