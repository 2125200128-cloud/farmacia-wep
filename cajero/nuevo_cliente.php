<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Nuevo Cliente';
$menuActivo = 'ventas';

$exito = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = sanitize($_POST['nombre']);
    $rfc = sanitize($_POST['rfc']);
    $telefono = sanitize($_POST['telefono']);
    $direccion = sanitize($_POST['direccion']);
    $codigo = 'CLI-' . strtoupper(substr(uniqid(), -5));

    if (!empty($nombre)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, rfc, telefono, direccion, estado) VALUES (?, ?, ?, ?, 'activo')");
            $stmt->execute([$nombre, $rfc, $telefono, $direccion]);
            
            $_SESSION['exito'] = 'Cliente creado correctamente.';
            header('Location: clientes.php');
            exit;
        } catch(Exception $e) {
            $error = "Error al crear cliente: " . $e->getMessage();
        }
    } else {
        $error = "El nombre es obligatorio.";
    }
}

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner" id="titulo-pagina">Nuevo Cliente (PHP)</h2>
          <p class="pagina-subtitulo">Completa los datos del cliente</p>
        </div>
        <a href="clientes.php" class="btn-cancelar">
          <i class="fa-solid fa-arrow-left"></i>
          Volver a clientes
        </a>
      </div>

      <?php if($error): ?>
      <div style="background:var(--color-danger); color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px;">
          <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
      </div>
      <?php endif; ?>

      <div class="layout-cliente">
<div class="panel-form-cliente">
          <form class="card-form" method="POST">
            
            <div class="seccion-form">
              <h3 class="seccion-titulo">
                <i class="fa-solid fa-user"></i>
                Datos generales
              </h3>

              <div class="form-fila">
                <div class="campo-grupo">
                  <label class="campo-label" for="codigo">
                    Código del cliente
                  </label>
<input type="text" class="campo-input" value="Generado automáticamente" readonly disabled />
                </div>
                <div class="campo-grupo">
                  <label class="campo-label" for="nombre">
                    Nombre completo <span class="requerido">*</span>
                  </label>
                  <input type="text" class="campo-input" name="nombre" placeholder="Juan Pérez" required maxlength="100"/>
                </div>
              </div>

            </div>

            <div class="seccion-form">
              <h3 class="seccion-titulo">
                <i class="fa-solid fa-file-invoice"></i>
                Datos fiscales
              </h3>

              <div class="form-fila">
                <div class="campo-grupo">
                  <label class="campo-label" for="rfc">RFC</label>
                  <input type="text" class="campo-input" name="rfc" placeholder="PEPJ800101XXX" maxlength="13"/>
                </div>
                <div class="campo-grupo">
                  <label class="campo-label" for="telefono">Teléfono</label>
                  <input type="tel" class="campo-input" name="telefono" placeholder="33 1234 5678" maxlength="15"/>
                </div>
              </div>

            </div>
            
            <div class="seccion-form">
              <h3 class="seccion-titulo">
                <i class="fa-solid fa-location-dot"></i>
                Dirección
              </h3>

              <div class="campo-grupo full">
                <label class="campo-label" for="direccion">Dirección completa</label>
                <input type="text" class="campo-input" name="direccion" placeholder="Av. Juárez 123..." maxlength="200"/>
              </div>

            </div>

<div class="form-btns">
              <a href="clientes.php" class="btn-cancelar-form">Cancelar</a>
              <button type="submit" class="btn-guardar">
                <i class="fa-solid fa-floppy-disk"></i>
                Guardar cliente
              </button>
            </div>

          </form>
        </div>
        
        <div class="panel-preview">
          <div class="card-preview-titulo">Vista previa</div>

          <div class="tarjeta-cliente-preview">
            <div class="preview-avatar" id="preview-avatar">
              <span id="preview-iniciales">--</span>
            </div>
            <p class="preview-nombre" id="preview-nombre">Nombre del cliente</p>
            <p class="preview-codigo" id="preview-codigo">Código: --</p>

            <div class="preview-separador"></div>

            <div class="preview-dato">
              <i class="fa-solid fa-file-invoice"></i>
              <span id="preview-rfc">RFC: --</span>
            </div>
            <div class="preview-dato">
              <i class="fa-solid fa-phone"></i>
              <span id="preview-telefono">Teléfono: --</span>
            </div>
            <div class="preview-dato">
              <i class="fa-solid fa-location-dot"></i>
              <span id="preview-direccion">Dirección: --</span>
            </div>
          </div>
<div class="nota-info">
            <i class="fa-solid fa-circle-info"></i>
            <p>Solo el nombre es obligatorio. Los demás datos pueden completarse después.</p>
          </div>

        </div>

      </div>
</main>

<script>
  const nombreInput = document.querySelector('input[name="nombre"]');
  const rfcInput = document.querySelector('input[name="rfc"]');
  const telInput = document.querySelector('input[name="telefono"]');
  const dirInput = document.querySelector('input[name="direccion"]');
  
  const pNombre = document.getElementById('preview-nombre');
  const pIniciales = document.getElementById('preview-iniciales');
  const pRfc = document.getElementById('preview-rfc');
  const pTel = document.getElementById('preview-telefono');
  const pDir = document.getElementById('preview-direccion');
  
  function updatePreview() {
      const nom = nombreInput.value.trim() || 'Nombre del cliente';
      pNombre.textContent = nom;
      pIniciales.textContent = nom.charAt(0).toUpperCase();
      pRfc.textContent = 'RFC: ' + (rfcInput.value.trim() || '--');
      pTel.textContent = 'Teléfono: ' + (telInput.value.trim() || '--');
      pDir.textContent = 'Dirección: ' + (dirInput.value.trim() || '--');
  }
  
  nombreInput.addEventListener('input', updatePreview);
  rfcInput.addEventListener('input', updatePreview);
  telInput.addEventListener('input', updatePreview);
  dirInput.addEventListener('input', updatePreview);
</script>

<?php include '../includes/footer.php'; ?>
