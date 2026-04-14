<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
requiereRol(['admin', 'encargado']);

$paginaTitulo = 'Promociones';
$menuActivo   = 'promociones';

$hoy = date('Y-m-d');
$msg = '';

// Crear nueva promoción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_promo') {
    $nombre        = trim($_POST['nombre'] ?? '');
    $tipo          = $_POST['tipo'] ?? 'porcentaje';
    $valor         = floatval($_POST['valor'] ?? 0);
    $fechaInicio   = $_POST['fecha_inicio'] ?? $hoy;
    $fechaFin      = $_POST['fecha_fin'] ?? $hoy;
    $condTipo      = $_POST['condicion_tipo'] ?? '';
    $condValor     = '';

    if ($condTipo === 'producto_especifico') {
        $condValor = trim($_POST['condicion_valor_producto'] ?? '');
    } elseif ($condTipo === 'categoria') {
        $condValor = trim($_POST['condicion_valor_categoria'] ?? '');
    } elseif ($condTipo === 'monto_minimo') {
        $condValor = trim($_POST['condicion_valor_monto'] ?? '');
    }
    $regaloId      = intval($_POST['producto_regalo_id'] ?? 0) ?: null;
    $activa        = isset($_POST['activa']) ? 1 : 0;

    if (!empty($nombre)) {
        $stmt = $pdo->prepare("
            INSERT INTO promociones (nombre, tipo, valor, fecha_inicio, fecha_fin, activa, condicion_tipo, condicion_valor, producto_regalo_id)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$nombre, $tipo, $valor, $fechaInicio, $fechaFin, $activa, $condTipo, $condValor, $regaloId]);
        $msg = 'success:Promoción creada correctamente.';
    } else {
        $msg = 'error:Nombre es obligatorio.';
    }
}

// Eliminar promoción inactiva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar_promo') {
    $id_eliminar = intval($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM promociones WHERE id = ? AND activa = 0")->execute([$id_eliminar]);
    $msg = 'success:Promoción inactiva eliminada.';
}

// Toggle activa/inactiva vía AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'toggle_promo') {
    header('Content-Type: application/json');
    $id     = intval($_POST['id'] ?? 0);
    $activa = intval($_POST['activa'] ?? 0);
    $stmt   = $pdo->prepare("UPDATE promociones SET activa = ? WHERE id = ?");
    $stmt->execute([$activa, $id]);
    echo json_encode(['success' => true, 'activa' => $activa]);
    exit;
}

// Listar promociones
$promociones = $pdo->query("SELECT * FROM promociones ORDER BY fecha_inicio DESC")->fetchAll();

// Productos para el selector (regalo y condición)
$productos = $pdo->query("SELECT id, nombre, codigo FROM productos WHERE estado='activo' ORDER BY nombre ASC")->fetchAll();

// Categorías (si existen)
$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
} catch(Exception $e) { /* tabla puede no existir */ }

// Contador de activas
$totalActivas = 0;
foreach ($promociones as $p) {
    if ($p['activa'] && $p['fecha_inicio'] <= $hoy && $p['fecha_fin'] >= $hoy) $totalActivas++;
}

include '../includes/header.php';
include '../includes/sidebar.php';

$tipos = ['porcentaje' => 'Porcentaje (%)', 'monto_fijo' => 'Monto Fijo ($)', '2x1' => '2x1', 'producto_gratis' => 'Producto Gratis'];
$condTipos = ['producto_especifico' => 'Producto específico', 'categoria' => 'Categoría', 'monto_minimo' => 'Monto mínimo de compra'];
?>

<main class="contenido-main">

<div class="pagina-header">
    <div>
        <h2 class="pagina-titulo-inner">
            Promociones
            <?php if($totalActivas > 0): ?>
            <span style="background:#10b981; color:white; font-size:0.7rem; padding:3px 10px; border-radius:999px; margin-left:8px; vertical-align:middle;">
                <?= $totalActivas ?> activa<?= $totalActivas > 1 ? 's' : '' ?>
            </span>
            <?php endif; ?>
        </h2>
        <p class="pagina-subtitulo">Gestión del motor de descuentos y ofertas</p>
    </div>
</div>

<?php if($msg): ?>
<?php [$tipo_msg, $texto_msg] = explode(':', $msg, 2); ?>
<div style="background:<?= $tipo_msg==='success'?'#dcfce7':'#fee2e2' ?>; color:<?= $tipo_msg==='success'?'#15803d':'#b91c1c' ?>; padding:14px 18px; border-radius:8px; margin-bottom:20px;">
    <i class="fa-solid fa-<?= $tipo_msg==='success'?'check-circle':'triangle-exclamation' ?>"></i> <?= htmlspecialchars($texto_msg) ?>
</div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1fr 380px; gap:24px; align-items:start;">

    <!-- Lista de promociones -->
    <div>
        <div class="card-reporte">
            <div class="card-header-rep">
                <h3 class="card-titulo-rep">Todas las promociones</h3>
            </div>
            <?php if(empty($promociones)): ?>
                <p class="tabla-vacia">No hay promociones registradas.</p>
            <?php else: ?>
            <div class="tabla-wrapper">
                <table class="tabla-rep" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Vigencia</th>
                            <th>Estado</th>
                            <th>Activar</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($promociones as $p): ?>
                    <?php
                        $esVigente = ($p['fecha_inicio'] <= $hoy && $p['fecha_fin'] >= $hoy);
                        if ($p['activa'] && $esVigente)       { $badge = 'activa';   $badgeColor = '#10b981'; $badgeLabel = 'Activa'; }
                        elseif ($p['fecha_fin'] < $hoy)        { $badge = 'vencida';  $badgeColor = '#6b7280'; $badgeLabel = 'Vencida'; }
                        elseif ($p['fecha_inicio'] > $hoy)     { $badge = 'proxima';  $badgeColor = '#3b82f6'; $badgeLabel = 'Próxima'; }
                        else                                   { $badge = 'inactiva'; $badgeColor = '#f59e0b'; $badgeLabel = 'Inactiva'; }
                    ?>
                    <tr id="fila-promo-<?= $p['id'] ?>">
                        <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                        <td><?= $tipos[$p['tipo']] ?? $p['tipo'] ?></td>
                        <td>
                            <?php if($p['tipo'] === 'porcentaje'): ?>
                                <?= $p['valor'] ?>%
                            <?php elseif($p['tipo'] === 'monto_fijo'): ?>
                                $<?= number_format($p['valor'], 2) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.8rem; color:var(--text-muted);">
                            <?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?> –
                            <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?>
                        </td>
                        <td>
                            <span style="background:<?= $badgeColor ?>22; color:<?= $badgeColor ?>; padding:3px 10px; border-radius:999px; font-size:0.78rem; font-weight:bold;">
                                <?= $badgeLabel ?>
                            </span>
                        </td>
                        <td>
                            <label class="toggle-switch" title="<?= $p['activa'] ? 'Desactivar' : 'Activar' ?>">
                                <input type="checkbox"
                                    class="toggle-promo-check"
                                    data-id="<?= $p['id'] ?>"
                                    <?= $p['activa'] ? 'checked' : '' ?>
                                    onchange="togglePromo(this)">
                                <span class="toggle-slider"></span>
                            </label>
                        </td>
                        <td id="acciones-promo-<?= $p['id'] ?>">
                            <?php if (!$p['activa']): ?>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('¿Seguro de eliminar esta promoción?');">
                                <input type="hidden" name="accion" value="eliminar_promo">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" style="color:var(--color-danger, #ef4444); background:none; border:none; cursor:pointer; font-size:1.1rem;" title="Eliminar">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulario nueva promoción -->
    <div>
        <div class="card-reporte">
            <h3 class="card-titulo-rep">Nueva Promoción</h3>
            <form method="POST" style="display:flex; flex-direction:column; gap:14px; margin-top:16px;">
                <input type="hidden" name="accion" value="crear_promo">

                <div class="campo-grupo">
                    <label class="campo-label">Nombre *</label>
                    <input type="text" name="nombre" class="campo-input" placeholder="Ej. Descuento Verano" required>
                </div>

                <div class="campo-grupo">
                    <label class="campo-label">Tipo de descuento *</label>
                    <select name="tipo" id="sel_tipo_promo" class="campo-input" onchange="actualizarCamposPromo()">
                        <?php foreach($tipos as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo-grupo" id="campo_valor_promo">
                    <label class="campo-label" id="label_valor_promo">Valor *</label>
                    <input type="number" name="valor" class="campo-input" step="0.01" min="0" placeholder="Ej. 15">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div class="campo-grupo">
                        <label class="campo-label">Fecha inicio *</label>
                        <input type="date" name="fecha_inicio" class="campo-input" value="<?= $hoy ?>" required>
                    </div>
                    <div class="campo-grupo">
                        <label class="campo-label">Fecha fin *</label>
                        <input type="date" name="fecha_fin" class="campo-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>

                <div class="campo-grupo">
                    <label class="campo-label">Condición</label>
                    <select name="condicion_tipo" id="sel_cond_tipo" class="campo-input" onchange="actualizarCondicion()">
                        <option value="">Sin condición (aplica siempre)</option>
                        <?php foreach($condTipos as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Condición: producto específico -->
                <div class="campo-grupo" id="campo_cond_producto" style="display:none;">
                    <label class="campo-label">Producto</label>
                    <select name="condicion_valor_producto" class="campo-input" id="sel_cond_producto">
                        <option value="">— Seleccionar —</option>
                        <?php foreach($productos as $pr): ?>
                            <option value="<?= $pr['id'] ?>">[<?= htmlspecialchars($pr['codigo']) ?>] <?= htmlspecialchars($pr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Condición: categoría -->
                <?php if(!empty($categorias)): ?>
                <div class="campo-grupo" id="campo_cond_categoria" style="display:none;">
                    <label class="campo-label">Categoría</label>
                    <select name="condicion_valor_categoria" class="campo-input" id="sel_cond_categoria">
                        <option value="">— Seleccionar —</option>
                        <?php foreach($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Condición: monto mínimo -->
                <div class="campo-grupo" id="campo_cond_monto" style="display:none;">
                    <label class="campo-label">Monto mínimo ($)</label>
                    <input type="number" name="condicion_valor_monto" class="campo-input" step="0.01" min="0" placeholder="Ej. 200" id="inp_cond_monto">
                </div>

                <!-- Producto regalo (solo para tipo producto_gratis) -->
                <div class="campo-grupo" id="campo_regalo" style="display:none;">
                    <label class="campo-label">Producto de regalo</label>
                    <select name="producto_regalo_id" class="campo-input">
                        <option value="">— Seleccionar —</option>
                        <?php foreach($productos as $pr): ?>
                            <option value="<?= $pr['id'] ?>">[<?= htmlspecialchars($pr['codigo']) ?>] <?= htmlspecialchars($pr['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo-grupo">
                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                        <input type="checkbox" name="activa" value="1" checked>
                        <span class="campo-label" style="margin:0;">Activar inmediatamente</span>
                    </label>
                </div>

                <button type="submit" class="btn-primario" style="width:100%;">
                    <i class="fa-solid fa-tag"></i> Crear Promoción
                </button>
            </form>
        </div>
    </div>

</div>

</main>

<style>
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:#cbd5e1; border-radius:24px; transition:.3s; }
.toggle-slider:before { content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
input:checked + .toggle-slider { background:var(--color-primary, #2bbba0); }
input:checked + .toggle-slider:before { transform:translateX(20px); }
</style>

<script>
function actualizarCamposPromo() {
    const tipo = document.getElementById('sel_tipo_promo').value;
    const campoValor = document.getElementById('campo_valor_promo');
    const labelValor = document.getElementById('label_valor_promo');
    const campoRegalo = document.getElementById('campo_regalo');

    campoValor.style.display  = tipo === '2x1' ? 'none' : 'block';
    campoRegalo.style.display = tipo === 'producto_gratis' ? 'block' : 'none';

    if (tipo === 'porcentaje') labelValor.textContent = 'Porcentaje (%) *';
    else if (tipo === 'monto_fijo') labelValor.textContent = 'Monto ($) *';
    else labelValor.textContent = 'Valor *';
}

function actualizarCondicion() {
    const cond = document.getElementById('sel_cond_tipo').value;
    document.getElementById('campo_cond_producto').style.display = cond === 'producto_especifico' ? 'block' : 'none';
    const catEl = document.getElementById('campo_cond_categoria');
    if (catEl) catEl.style.display = cond === 'categoria' ? 'block' : 'none';
    document.getElementById('campo_cond_monto').style.display = cond === 'monto_minimo' ? 'block' : 'none';
}

async function togglePromo(checkbox) {
    const id     = checkbox.dataset.id;
    const activa = checkbox.checked ? 1 : 0;
    try {
        const fd = new FormData();
        fd.append('accion', 'toggle_promo');
        fd.append('id', id);
        fd.append('activa', activa);
        const resp = await fetch('', { method:'POST', body: fd });
        const data = await resp.json();
        if (!data.success) { checkbox.checked = !checkbox.checked; }
        else {
            // Recargar la página para mostrar/ocultar el botón de eliminar
            window.location.reload();
        }
    } catch(e) {
        checkbox.checked = !checkbox.checked;
        alert('Error al cambiar estado de la promoción.');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
