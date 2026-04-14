<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Clientes';
$menuActivo = 'clientes';

// Filtros
$busqueda = sanitize($_GET['busqueda'] ?? '');

$sql = "SELECT c.*, 
        (SELECT COUNT(id) FROM ventas WHERE cliente_id = c.id) as total_compras_num
        FROM clientes c WHERE 1=1";
$params = [];

if ($busqueda) {
    $sql .= " AND (c.nombre LIKE :busqueda OR c.rfc LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}

$sql .= " ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Estadísticas
$totalClientes = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$clientesCompras = $pdo->query("SELECT COUNT(DISTINCT cliente_id) FROM ventas")->fetchColumn();

// Nuevos mes
$inicioMes = date('Y-m-01 00:00:00');
$nuevosMes = $pdo->query("SELECT COUNT(*) FROM clientes WHERE fecha_registro >= '$inicioMes'")->fetchColumn();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Clientes (PHP)</h2>
          <p class="pagina-subtitulo">Registro y seguimiento de clientes</p>
        </div>
        <a href="nuevo_cliente.php" class="btn-primario">
          <i class="fa-solid fa-plus"></i>
          Nuevo cliente
        </a>
      </div>
<div class="tarjetas-clientes">
        <div class="tarjeta-cliente-stat">
          <div class="cli-icono turquesa">
            <i class="fa-solid fa-users"></i>
          </div>
          <div>
            <p class="cli-etiqueta">Total clientes</p>
<p class="cli-valor"><?= $totalClientes ?></p>
          </div>
        </div>
        <div class="tarjeta-cliente-stat">
          <div class="cli-icono verde">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div>
            <p class="cli-etiqueta">Con compras</p>
            <p class="cli-valor"><?= $clientesCompras ?></p>
          </div>
        </div>
        <div class="tarjeta-cliente-stat">
          <div class="cli-icono azul">
            <i class="fa-solid fa-calendar-day"></i>
          </div>
          <div>
            <p class="cli-etiqueta">Nuevos este mes</p>
            <p class="cli-valor"><?= $nuevosMes ?></p>
          </div>
        </div>
      </div>
<div class="card-clientes">
<form method="GET" class="filtros-clientes">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por nombre, código, RFC..."/>
          </div>
          <div class="filtros-derecha">
            <button type="submit" class="btn-secundario" style="margin-right:10px; padding:10px;">Filtrar</button>
            <a href="clientes.php" class="btn-secundario" style="margin-right:10px; padding:10px; text-decoration:none;">Limpiar</a>
            <button type="button" class="btn-exportar">
              <i class="fa-solid fa-file-export"></i>
              Exportar
            </button>
          </div>
        </form>
<div class="tabla-wrapper">
          <table class="tabla-clientes">
            <thead>
              <tr>
                <th>ID</th>
                <th>Cliente</th>
                <th>RFC</th>
                <th>Teléfono</th>
                <th>Compras</th>
                <th>Deuda Actual</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($clientes)): ?>
              <tr>
                <td colspan="7" class="tabla-vacia">
                  <i class="fa-solid fa-users"></i>
                  <p>Sin clientes registrados</p>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($clientes as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c['id'] ?? '-') ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="background:var(--color-primary);width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:0.8rem;">
                            <?= strtoupper(substr(htmlspecialchars($c['nombre']), 0, 1)) ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                            <?php if(!empty($c['correo'])): ?>
                            <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($c['correo']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($c['rfc'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($c['telefono'] ?? '-') ?></td>
                  <td><strong><?= $c['total_compras_num'] ?></strong></td>
                  <td>
                    <?php $deuda = floatval($c['deuda_actual'] ?? 0); ?>
                    <span style="color:<?= $deuda > 0 ? '#ef4444' : '#10b981' ?>;font-weight:bold;">
                      $<?= number_format($deuda, 2) ?>
                    </span>
                  </td>
                  <td>
                      <div style="display:flex; gap:5px; flex-wrap: nowrap; min-width: 140px;">
                          <?php if (floatval($c['deuda_actual']) > 0): ?>
                          <a href="abonar_cliente.php?id=<?= $c['id'] ?>" class="btn-accion" title="Abonar / Liquidar" style="padding:6px 8px; background:#f59e0b; color:white; border-radius:6px; text-decoration:none; font-size:0.75rem; white-space:nowrap;"><i class="fa-solid fa-hand-holding-dollar"></i></a>
                          <?php endif; ?>
                          <a href="editar_cliente.php?id=<?= $c['id'] ?>" class="btn-accion" title="Editar" style="padding:6px 8px; background:var(--color-primary); color:white; border-radius:6px; text-decoration:none; font-size:0.75rem;"><i class="fa-solid fa-pen"></i></a>
                          <a href="eliminar_cliente.php?id=<?= $c['id'] ?>" class="btn-accion" title="Eliminar" style="padding:6px 8px; background:#ef4444; color:white; border-radius:6px; text-decoration:none; font-size:0.75rem;" onclick="return confirm('¿Eliminar cliente?')"><i class="fa-solid fa-trash"></i></a>
                      </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<div class="paginacion">
          <p class="paginacion-info">Mostrando <?= count($clientes) ?> clientes</p>
        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
