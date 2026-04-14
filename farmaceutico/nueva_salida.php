<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Nueva Salida';
$menuActivo = 'productos';

$exito = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = intval($_POST['producto_id']);
    $cantidad = intval($_POST['cantidad']);
    $motivo = sanitize($_POST['motivo']);
    $notas = sanitize($_POST['notas']);
    $folio = 'SAL-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $fecha = $_POST['fecha'] ?? date('Y-m-d H:i:s');

    if ($producto_id && $cantidad > 0 && $motivo) {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO movimiento_inventario 
                    (producto_id, tipo_movimiento, cantidad, motivo, notas, usuario_id, numero_movimiento, fecha_movimiento) 
                    VALUES (?, 'salida', ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $producto_id, $cantidad, $motivo, $notas, $_SESSION['usuario_id'], $folio, $fecha
            ]);

            $pdo->exec("UPDATE productos SET stock_actual = stock_actual - $cantidad WHERE id = $producto_id");

            $pdo->commit();
            logEvent("auth", "Nueva salida $folio ($cantidad unid. de prod #$producto_id, Motivo: $motivo)");
            $_SESSION['exito'] = 'Salida registrada correctamente.';
            header('Location: salidas.php');
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar: " . $e->getMessage();
        }
    } else {
        $error = "El producto, la cantidad y el motivo son obligatorios.";
    }
}

// Fetch productos para el buscador (siempre que tengan stock)
$productos = $pdo->query("SELECT p.id, p.nombre, p.codigo, p.stock_actual, p.imagen_path, (SELECT COUNT(*) FROM movimiento_inventario WHERE producto_id = p.id) as freq FROM productos p WHERE p.stock_actual > 0 ORDER BY freq DESC, p.nombre ASC")->fetchAll();

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

    <div class="pagina-header">
      <div>
        <h2 class="pagina-titulo-inner">Registrar salida (PHP)</h2>
        <p class="pagina-subtitulo">Descuenta unidades del inventario por merma, vencimiento o ajuste</p>
      </div>
      <a href="salidas.php" class="btn-cancelar">
        <i class="fa-solid fa-arrow-left"></i> Volver a salidas
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
              <i class="fa-solid fa-circle-info"></i> Información general
            </h3>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="folio">Folio</label>
                <input type="text" class="campo-input" value="Generado automáticamente" readonly disabled/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="fecha">Fecha <span class="requerido">*</span></label>
                <input type="date" class="campo-input" name="fecha" value="<?= date('Y-m-d') ?>" required/>
              </div>
            </div>
          </div>

          <div class="seccion-form">
            <h3 class="seccion-titulo">
              <i class="fa-solid fa-pills"></i> Producto afectado
            </h3>
            <div class="campo-grupo full" style="position:relative;">
              <label class="campo-label" for="busqueda_producto">Producto <span class="requerido">*</span></label>
              <div class="buscador-producto" style="width:100%;">
                <i class="fa-solid fa-pills"></i>
                <input type="text" id="busqueda_producto_input" class="campo-input" placeholder="Nombre o código del producto..." autocomplete="off" required/>
                <input type="hidden" name="producto_id" id="producto_id_hidden" required/>
                <div id="sugerencias_prod" class="sugerencias-dropdown"></div>
              </div>
            </div>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="cantidad">Cantidad <span class="requerido">*</span></label>
                <input type="number" class="campo-input" name="cantidad" placeholder="0" min="1" required/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="motivo">Motivo <span class="requerido">*</span></label>
                <select class="campo-input" name="motivo" required>
                  <option value="">Seleccionar...</option>
                  <option value="merma">Merma</option>
                  <option value="vencimiento">Vencimiento</option>
                  <option value="ajuste">Ajuste de inventario</option>
                  <option value="devolucion">Devolución</option>
                  <option value="otro">Otro</option>
                </select>
              </div>
            </div>
            <div class="campo-grupo full">
              <label class="campo-label" for="notas">Notas u observaciones</label>
              <textarea class="campo-input campo-textarea" name="notas" placeholder="Describe el motivo detalladamente..." rows="2" maxlength="300"></textarea>
            </div>
          </div>

          <div class="form-btns">
            <a href="salidas.php" class="btn-cancelar-form">Cancelar</a>
            <button type="submit" class="btn-guardar">
              <i class="fa-solid fa-arrow-up-from-bracket"></i>
              Registrar salida
            </button>
          </div>

        </form>
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
.buscador-producto {
    position: relative;
    display: flex;
    align-items: center;
}
.buscador-producto i {
    position: absolute;
    left: 15px;
    color: var(--text-muted);
}
.buscador-producto .campo-input {
    padding-left: 40px;
}
</style>

<script>
    const productos = <?= json_encode($productos) ?>;

    function setupAutocomplete() {
        const input = document.getElementById('busqueda_producto_input');
        const hidden = document.getElementById('producto_id_hidden');
        const sugs = document.getElementById('sugerencias_prod');
        const inputCantidad = document.querySelector('input[name="cantidad"]');
        if (!input || !sugs) return;

        function render(busqueda) {
            sugs.innerHTML = '';
            let filtrados = [];
            let isDefault = false;

            if(!busqueda) {
                filtrados = productos.slice(0, 5);
                isDefault = true;
            } else {
                const q = busqueda.toLowerCase();
                filtrados = productos.filter(d => 
                    d.nombre.toLowerCase().includes(q) || d.codigo.toLowerCase().includes(q)
                ).slice(0, 50);
            }

            if(filtrados.length === 0 && busqueda) {
                sugs.style.display = 'none';
                return;
            }

            if (filtrados.length > 0) {
                sugs.style.display = 'block';
                if (isDefault) {
                    const head = document.createElement('div');
                    head.className = 'sug-title-default';
                    head.innerText = 'Productos frecuentes';
                    sugs.appendChild(head);
                }

                filtrados.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'sugerencia-item';
                    const img = item.imagen_path ? `<img src="../${item.imagen_path}" style="width:32px;height:32px;object-fit:cover;border-radius:4px;border:1px solid #ccc;"/>` : `<div style="width:32px;height:32px;border-radius:4px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#64748b;font-size:12px;"><i class="fa-solid fa-pills"></i></div>`;
                    
                    div.innerHTML = `
                        <div style="display:flex;align-items:center;gap:10px;">
                            ${img}
                            <div>
                                <p class="sug-titulo">${item.nombre}</p>
                                <p class="sug-sub">Cód: ${item.codigo} | Stock: ${item.stock_actual}</p>
                            </div>
                        </div>`;
                    
                    div.onclick = () => {
                        input.value = item.nombre;
                        hidden.value = item.id;
                        if(inputCantidad) inputCantidad.max = item.stock_actual;
                        sugs.style.display = 'none';
                    };
                    sugs.appendChild(div);
                });
            } else {
                sugs.style.display = 'none';
            }
        }

        input.addEventListener('input', e => {
            hidden.value = '';
            render(e.target.value);
        });
        input.addEventListener('focus', e => render(e.target.value));
        
        document.addEventListener('click', e => {
            if(!input.contains(e.target) && !sugs.contains(e.target)) {
                sugs.style.display = 'none';
            }
        });
    }

    setupAutocomplete();
</script>

<?php include '../includes/footer.php'; ?>
