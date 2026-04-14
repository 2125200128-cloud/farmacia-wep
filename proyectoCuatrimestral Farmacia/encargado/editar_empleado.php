<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
requiereRol(['admin', 'encargado']);

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: empleados.php'); exit; }

$emp = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$emp->execute([$id]);
$emp = $emp->fetch();
if (!$emp) { header('Location: empleados.php'); exit; }

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = sanitize($_POST['nombre']);
    $correo   = sanitize($_POST['correo']);
    $telefono = sanitize($_POST['telefono']);
    $rol      = sanitize($_POST['rol']);
    $estado   = sanitize($_POST['estado']);
    $salario     = floatval($_POST['salario'] ?? 0);
    $destare     = floatval($_POST['destare'] ?? 0);
    $usa_sistema = isset($_POST['usa_sistema']) ? 1 : 0;
    $frecuencia  = sanitize($_POST['frecuencia_pago']);

    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, correo=?, telefono=?, rol=?, estado=?, salario=?, destare=?, usa_sistema=?, frecuencia_pago=? WHERE id=?");
        $stmt->execute([$nombre, $correo, $telefono, $rol, $estado, $salario, $destare, $usa_sistema, $frecuencia, $id]);
        $_SESSION['exito'] = 'Empleado actualizado correctamente.';
        header('Location: empleados.php');
        exit;
    } catch(PDOException $e) {
        $error = 'Error al actualizar: ' . $e->getMessage();
    }
}

$paginaTitulo = 'Editar Empleado';
$menuActivo = 'empleados';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<main class="contenido-main">
  <div class="pagina-header">
    <div>
      <h2 class="pagina-titulo-inner">Editar Empleado</h2>
      <p class="pagina-subtitulo"><?= htmlspecialchars($emp['nombre']) ?></p>
    </div>
    <a href="empleados.php" class="btn-cancelar"><i class="fa-solid fa-arrow-left"></i> Volver</a>
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
          <h3 class="seccion-titulo"><i class="fa-solid fa-user"></i> Datos personales</h3>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">Nombre completo <span class="requerido">*</span></label>
              <input type="text" class="campo-input" name="nombre" value="<?= htmlspecialchars($emp['nombre']) ?>" required>
            </div>
            <div class="campo-grupo">
              <label class="campo-label">Correo electrónico</label>
              <input type="email" class="campo-input" name="correo" value="<?= htmlspecialchars($emp['correo'] ?? '') ?>">
            </div>
          </div>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">Teléfono</label>
              <input type="tel" class="campo-input" name="telefono" value="<?= htmlspecialchars($emp['telefono'] ?? '') ?>">
            </div>
            <div class="campo-grupo">
              <label class="campo-label">Estado</label>
              <select class="campo-input" name="estado">
                <option value="activo" <?= $emp['estado']==='activo'?'selected':'' ?>>Activo</option>
                <option value="inactivo" <?= $emp['estado']==='inactivo'?'selected':'' ?>>Inactivo</option>
              </select>
            </div>
          </div>
        </div>
        <div class="seccion-form">
          <h3 class="seccion-titulo"><i class="fa-solid fa-user-gear"></i> Rol y Nómina</h3>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">Rol <span class="requerido">*</span></label>
              <select class="campo-input" name="rol" required>
                <option value="encargado" <?= $emp['rol']==='encargado'?'selected':'' ?>>Encargado</option>
                <option value="cajero" <?= $emp['rol']==='cajero'?'selected':'' ?>>Cajero</option>
                <option value="farmaceutico" <?= $emp['rol']==='farmaceutico'?'selected':'' ?>>Farmacéutico</option>
                <option value="intendencia" <?= $emp['rol']==='intendencia'?'selected':'' ?>>Intendencia / Limpieza</option>
                <option value="otros" <?= $emp['rol']==='otros'?'selected':'' ?>>Otros</option>
              </select>
            </div>
            <div class="campo-grupo">
              <label class="campo-label" style="display:flex; align-items:center; gap:10px; cursor:pointer; margin-top:35px;">
                <input type="checkbox" name="usa_sistema" value="1" <?= $emp['usa_sistema']? 'checked':'' ?> style="width:20px; height:20px;">
                Usa el sistema (puede iniciar sesión)
              </label>
            </div>
          </div>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">Salario ($)</label>
              <input type="number" class="campo-input" name="salario" value="<?= $emp['salario'] ?? 0 ?>" step="0.01">
            </div>
            <div class="campo-grupo">
              <label class="campo-label">Periodo de Pago</label>
              <select class="campo-input" name="frecuencia_pago">
                <option value="semanal" <?= $emp['frecuencia_pago']==='semanal'?'selected':'' ?>>Semanal</option>
                <option value="quincenal" <?= $emp['frecuencia_pago']==='quincenal'?'selected':'' ?>>Quincenal</option>
                <option value="mensual" <?= $emp['frecuencia_pago']==='mensual'?'selected':'' ?>>Mensual</option>
              </select>
            </div>
          </div>
          <div class="form-fila">
            <div class="campo-grupo">
              <label class="campo-label">Adelantos / Destare ($)</label>
              <input type="number" class="campo-input" name="destare" value="<?= $emp['destare'] ?? 0 ?>" step="0.01">
            </div>
          </div>
        </div>
        <div class="form-btns">
          <a href="empleados.php" class="btn-cancelar-form">Cancelar</a>
          <button type="submit" class="btn-guardar">
            <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php include '../includes/footer.php'; ?>
