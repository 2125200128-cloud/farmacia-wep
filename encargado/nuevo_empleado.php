<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Nuevo Empleado';
$menuActivo = 'dashboard';

$exito = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre']);
    $correo = sanitize($_POST['correo']);
    $telefono = sanitize($_POST['telefono']);
    $sucursal = sanitize($_POST['sucursal']);
    $usuario = sanitize($_POST['usuario']);
    $rol = sanitize($_POST['rol']);
    $contrasena = $_POST['contrasena'];
    $estado = sanitize($_POST['estado']);

    if (!empty($nombre) && !empty($usuario) && !empty($rol) && !empty($contrasena)) {
        try {
            $salario = floatval($_POST['salario'] ?? 0);
            $destare = floatval($_POST['destare'] ?? 0);
            $usa_sistema = isset($_POST['usa_sistema']) ? 1 : 0;
            $frecuencia = sanitize($_POST['frecuencia_pago']);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, correo, telefono, sucursal, usuario, rol, contrasena, estado, salario, destare, usa_sistema, frecuencia_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $correo, $telefono, $sucursal, $usuario, $rol, $hash, $estado, $salario, $destare, $usa_sistema, $frecuencia]);
            
            $_SESSION['exito'] = 'Empleado creado correctamente.';
            header('Location: empleados.php');
            exit;
        } catch(PDOException $e) {
            // Error code 23000 typically means integrity constraint violation (e.g. unique username)
            if ($e->getCode() == 23000) {
                $error = "Ese nombre de usuario ya está en uso.";
            } else {
                $error = "Error al crear empleado: " . $e->getMessage();
            }
        }
    } else {
        $error = "Los campos obligatorios deben estar llenos.";
    }
}

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

    <div class="pagina-header">
      <div>
        <h2 class="pagina-titulo-inner">Nuevo empleado (PHP)</h2>
        <p class="pagina-subtitulo">Registra un nuevo usuario en el sistema</p>
      </div>
      <a href="empleados.php" class="btn-cancelar">
        <i class="fa-solid fa-arrow-left"></i> Volver a empleados
      </a>
    </div>

    <?php if($error): ?>
    <div style="background:var(--color-danger); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <div class="layout-cliente">
      <div class="panel-form-cliente">
        <form class="card-form" method="POST" enctype="multipart/form-data">

          <div class="seccion-form">
            <h3 class="seccion-titulo">
              <i class="fa-solid fa-user"></i> Datos personales
            </h3>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="nombre">Nombre completo <span class="requerido">*</span></label>
                <input type="text" class="campo-input" name="nombre" placeholder="Juan Pérez" required maxlength="100"/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="correo">Correo electrónico</label>
                <input type="email" class="campo-input" name="correo" placeholder="empleado@farmacia.com" maxlength="100"/>
              </div>
            </div>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="telefono">Teléfono</label>
                <input type="tel" class="campo-input" name="telefono" placeholder="33 1234 5678" maxlength="15"/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="foto">Foto de empleado <span class="requerido">*</span></label>
                <input type="file" class="campo-input" name="foto" accept="image/*" required/>
              </div>
            </div>
            <div class="campo-grupo">
              <label class="campo-label" for="salario">Salario ($) <span class="requerido">*</span></label>
              <input type="number" class="campo-input" name="salario" placeholder="0.00" step="0.01" required/>
            </div>
            <div class="campo-grupo">
              <label class="campo-label" for="frecuencia_pago">Periodo de Pago <span class="requerido">*</span></label>
              <select class="campo-input" name="frecuencia_pago" required>
                <option value="semanal">Semanal</option>
                <option value="quincenal">Quincenal</option>
                <option value="mensual" selected>Mensual</option>
              </select>
            </div>
            <div class="campo-grupo">
              <label class="campo-label" for="destare">Adelantos/Destare</label>
              <input type="number" class="campo-input" name="destare" placeholder="0.00" step="0.01" value="0.00"/>
            </div>
          </div>

          <div class="seccion-form">
            <h3 class="seccion-titulo">
              <i class="fa-solid fa-lock"></i> Acceso al sistema
            </h3>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="usuario">Nombre de usuario <span class="requerido">*</span></label>
                <input type="text" class="campo-input" name="usuario" placeholder="caja_01" required maxlength="50"/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="rol">Rol <span class="requerido">*</span></label>
                <select class="campo-input" name="rol" required>
                  <option value="">Seleccionar...</option>
                  <option value="encargado">Encargado</option>
                  <option value="cajero">Cajero</option>
                  <option value="farmaceutico">Farmacéutico</option>
                  <option value="intendencia">Intendencia / Limpieza</option>
                  <option value="otros">Otros</option>
                </select>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" style="display:flex; align-items:center; gap:10px; cursor:pointer; margin-top:35px;">
                  <input type="checkbox" name="usa_sistema" value="1" checked style="width:20px; height:20px;">
                  Usa el sistema (puede iniciar sesión)
                </label>
              </div>
            </div>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="contrasena">Contraseña <span class="requerido">*</span></label>
                <input type="password" class="campo-input" name="contrasena" placeholder="Mínimo 6 caracteres" required minlength="6" maxlength="100"/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="estado">Estado</label>
                <select class="campo-input" name="estado">
                  <option value="activo">Activo</option>
                  <option value="inactivo">Inactivo</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-btns">
            <a href="empleados.php" class="btn-cancelar-form">Cancelar</a>
            <button type="submit" class="btn-guardar">
              <i class="fa-solid fa-floppy-disk"></i>
              Guardar empleado
            </button>
          </div>

        </form>
      </div>
    </div>
  
</main>

<?php include '../includes/footer.php'; ?>
