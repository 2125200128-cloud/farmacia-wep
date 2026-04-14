<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
// Solo Admin y Encargado pueden gestionar empleados
requiereRol(['admin', 'encargado']);
$paginaTitulo = 'Empleados';
$menuActivo = 'empleados';

// Filtros
$busqueda = sanitize($_GET['busqueda'] ?? '');
$rol = sanitize($_GET['rol'] ?? '');
$estadoFiltro = sanitize($_GET['estado'] ?? '');

$sql = "SELECT * FROM usuarios WHERE 1=1";
$params = [];

if ($busqueda) {
    $sql .= " AND (nombre LIKE :busqueda OR apellido LIKE :busqueda OR usuario LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($rol) {
    $sql .= " AND rol = :rol";
    $params[':rol'] = $rol;
}
if ($estadoFiltro) {
    $sql .= " AND estado = :estado";
    $params[':estado'] = $estadoFiltro;
}

$sql .= " ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll();

// Estadísticas
$totalEmpleados = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$encargados = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='encargado'")->fetchColumn();
$farmaceuticos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='farmaceutico'")->fetchColumn();
$cajeros = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='cajero'")->fetchColumn();

include '../includes/header.php';
include '../includes/sidebar.php';

$rolesText = ['encargado' => 'Encargado', 'cajero' => 'Cajero', 'farmaceutico' => 'Farmacéutico'];
$rolesColor = ['encargado' => '#3b82f6', 'cajero' => '#f59e0b', 'farmaceutico' => '#10b981'];
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Empleados (PHP)</h2>
          <p class="pagina-subtitulo">Gestión de usuarios y accesos al sistema</p>
        </div>
        <a href="nuevo_empleado.php" class="btn-primario">
          <i class="fa-solid fa-plus"></i>
          Nuevo empleado
        </a>
      </div>
<div class="tarjetas-empleados">
        <div class="tarjeta-emp-stat">
          <div class="emp-icono turquesa">
            <i class="fa-solid fa-users"></i>
          </div>
          <div>
            <p class="emp-etiqueta">Total empleados</p>
            <p class="emp-valor"><?= $totalEmpleados ?></p>
          </div>
        </div>
        <div class="tarjeta-emp-stat">
          <div class="emp-icono azul">
            <i class="fa-solid fa-user-tie"></i>
          </div>
          <div>
            <p class="emp-etiqueta">Encargados</p>
            <p class="emp-valor"><?= $encargados ?></p>
          </div>
        </div>
        <div class="tarjeta-emp-stat">
          <div class="emp-icono verde">
            <i class="fa-solid fa-flask"></i>
          </div>
          <div>
            <p class="emp-etiqueta">Farmacéuticos</p>
            <p class="emp-valor"><?= $farmaceuticos ?></p>
          </div>
        </div>
        <div class="tarjeta-emp-stat">
          <div class="emp-icono naranja">
            <i class="fa-solid fa-cash-register"></i>
          </div>
          <div>
            <p class="emp-etiqueta">Cajeros</p>
            <p class="emp-valor"><?= $cajeros ?></p>
          </div>
        </div>
      </div>
<div class="card-empleados">
<form method="GET" class="filtros-empleados">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por nombre, usuario o código..." />
          </div>
          <div class="filtros-derecha">
            <select class="select-filtro" name="rol">
              <option value="">Todos los roles</option>
              <option value="encargado" <?= $rol=='encargado'?'selected':'' ?>>Encargado</option>
              <option value="farmaceutico" <?= $rol=='farmaceutico'?'selected':'' ?>>Farmacéutico</option>
              <option value="cajero" <?= $rol=='cajero'?'selected':'' ?>>Cajero</option>
            </select>
            <select class="select-filtro" name="estado">
              <option value="">Todos los estados</option>
              <option value="activo" <?= $estadoFiltro=='activo'?'selected':'' ?>>Activo</option>
              <option value="inactivo" <?= $estadoFiltro=='inactivo'?'selected':'' ?>>Inactivo</option>
            </select>
            <button type="submit" class="btn-secundario" style="margin-right:10px; padding:10px;">Filtrar</button>
            <a href="empleados.php" class="btn-secundario" style="padding:10px; text-decoration:none;">Limpiar</a>
          </div>
        </form>
<div class="tabla-wrapper">
          <table class="tabla-empleados">
            <thead>
              <tr>
                <th>Código</th>
                <th>Empleado</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Salario</th>
                <th>Destare</th>
                <th>Neto</th>
                <th>Último acceso</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($empleados)): ?>
              <tr>
                <td colspan="8" class="tabla-vacia">
                  <i class="fa-solid fa-user-tie"></i>
                  <p>Sin empleados registrados</p>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach($empleados as $emp): 
                    $iniciales = strtoupper(substr($emp['nombre'], 0, 1) . (isset($emp['apellido']) ? substr($emp['apellido'], 0, 1) : ''));
                    $ultimoAcceso = $emp['ultimo_acceso'] ? date('d/M/Y H:i', strtotime($emp['ultimo_acceso'])) : 'Sin acceso';
                    $colorRol = $rolesColor[$emp['rol']] ?? '#6b7280';
                ?>
                <tr>
                    <td style="font-family:monospace;font-weight:bold;color:var(--color-primary,#2bbba0)">#<?= $emp['id'] ?></td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="background:<?= $colorRol ?>;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:0.85rem;"><?= $iniciales ?></div>
                            <div>
                                <p style="margin:0;font-weight:600">
                                    <?= htmlspecialchars($emp['nombre'] . ' ' . ($emp['apellido'] ?? '')) ?>
                                    <?php if($emp['estado'] === 'inactivo'): ?>
                                        <span style="background:#ef4444; color:white; font-size:0.65rem; padding:2px 6px; border-radius:4px; margin-left:4px;">Inactivo</span>
                                    <?php endif; ?>
                                </p>
                                <small style="color:#64748b"><?= htmlspecialchars($emp['correo'] ?? 'Sin correo') ?></small>
                            </div>
                        </div>
                    </td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>><?= htmlspecialchars($emp['usuario']) ?></td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>><span style="background:<?= $colorRol ?>22;color:<?= $colorRol ?>;padding:4px 10px;border-radius:20px;font-size:0.8rem;font-weight:600"><?= $rolesText[$emp['rol']] ?? ucfirst($emp['rol']) ?></span></td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>>$<?= number_format($emp['salario'] ?? 0, 2) ?></td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>>$<?= number_format($emp['destare'] ?? 0, 2) ?></td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>><strong>$<?= number_format(($emp['salario'] ?? 0) - ($emp['destare'] ?? 0), 2) ?></strong></td>
                    <td <?= $emp['estado'] === 'inactivo' ? 'style="opacity:0.6;"' : '' ?>><small style="color:#64748b"><?= $ultimoAcceso ?></small></td>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <a href="editar_empleado.php?id=<?= $emp['id'] ?>" title="Editar" style="padding:6px 10px;background:var(--color-primary,#2bbba0);color:white;border-radius:6px;text-decoration:none;font-size:0.85rem;"><i class="fa-solid fa-pen"></i></a>
                            <a href="eliminar_empleado.php?id=<?= $emp['id'] ?>" title="Eliminar" style="padding:6px 10px;background:#ef4444;color:white;border-radius:6px;text-decoration:none;font-size:0.85rem;" onclick="return confirm('¿Eliminar empleado?')"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<div class="paginacion">
          <p class="paginacion-info">Mostrando <?= count($empleados) ?> empleados</p>
        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
