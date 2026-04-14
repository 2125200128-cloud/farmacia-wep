<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';

$categorias = [];
try {
    $categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) { }

$paginaTitulo = 'Nuevo Producto';
$menuActivo = 'productos';
include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

    <div class="pagina-header">
      <div>
        <h2 class="pagina-titulo-inner">Nuevo producto</h2>
        <p class="pagina-subtitulo">Agrega un medicamento o producto al catálogo</p>
      </div>
      <a href="productos.php" class="btn-cancelar">
        <i class="fa-solid fa-arrow-left"></i> Volver a productos
      </a>
    </div>

    <div class="layout-cliente">
      <div class="panel-form-cliente">
        <form class="card-form" action="guardar_producto.php" method="POST" enctype="multipart/form-data" id="form-nuevo-producto" novalidate>

          <div class="seccion-form">
            <h3 class="seccion-titulo">
              <i class="fa-solid fa-pills"></i> Información del producto
            </h3>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="imagen">Imagen del producto</label>
                <input type="file" class="campo-input" id="imagen" name="imagen" accept="image/png, image/jpeg, image/jpg, image/webp" />
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="nombre">Nombre <span class="requerido">*</span></label>
                <input type="text" class="campo-input" id="nombre" name="nombre" placeholder="Aspirina 500mg" required maxlength="100"/>
                <span class="campo-error" id="error-nombre"></span>
              </div>
            </div>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="categoria">Categoría <span class="requerido">*</span></label>
                <select class="campo-input" id="categoria" name="categoria" required>
                  <option value="">Seleccionar...</option>
                  <?php if(empty($categorias)): ?>
                      <option value="2">Analgésico</option>
                      <option value="1">Antibiótico</option>
                      <option value="4">Antiinflamatorio</option>
                      <option value="3">Vitamina</option>
                      <option value="6">Otro</option>
                  <?php else: ?>
                      <?php foreach($categorias as $cat): ?>
                          <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                      <?php endforeach; ?>
                  <?php endif; ?>
                </select>
                <span class="campo-error" id="error-categoria"></span>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="presentacion">Presentación</label>
                <input type="text" class="campo-input" id="presentacion" name="presentacion" placeholder="Tabletas, jarabe, cápsulas..." maxlength="60"/>
              </div>
            </div>
            <div class="campo-grupo full">
              <label class="campo-label" for="descripcion">Descripción</label>
              <textarea class="campo-input campo-textarea" id="descripcion" name="descripcion" placeholder="Descripción opcional del producto..." rows="2" maxlength="300"></textarea>
            </div>
          </div>

          <div class="seccion-form">
            <h3 class="seccion-titulo">
              <i class="fa-solid fa-tag"></i> Precios e inventario
            </h3>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="precio">Precio de venta <span class="requerido">*</span></label>
                <div class="input-prefijo">
                  <span class="prefijo">$</span>
                  <input type="number" class="campo-input" id="precio" name="precio" placeholder="0.00" min="0" step="0.01" required/>
                </div>
                <span class="campo-error" id="error-precio"></span>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="costo">Costo de compra</label>
                <div class="input-prefijo">
                  <span class="prefijo">$</span>
                  <input type="number" class="campo-input" id="costo" name="costo" placeholder="0.00" min="0" step="0.01"/>
                </div>
              </div>
            </div>
            <div class="form-fila">
              <div class="campo-grupo">
                <label class="campo-label" for="stock_inicial">Stock inicial</label>
                <input type="number" class="campo-input" id="stock_inicial" name="stock_inicial" placeholder="0" min="0"/>
              </div>
              <div class="campo-grupo">
                <label class="campo-label" for="stock_minimo">Stock mínimo (alerta)</label>
                <input type="number" class="campo-input" id="stock_minimo" name="stock_minimo" placeholder="5" value="5" min="1"/>
              </div>
            </div>
            <div class="form-fila">
              <div class="campo-grupo">
                  <label class="campo-label" for="lote">Número de lote</label>
                  <input type="text" class="campo-input" id="lote" name="lote" 
                         placeholder="Ej: LOT-2025-001" maxlength="50"/>
              </div>

              <div class="campo-grupo">
                  <label class="campo-label" for="fecha_vencimiento">Fecha de vencimiento</label>
                  <input type="date" class="campo-input" id="fecha_vencimiento" 
                         name="fecha_vencimiento"/>
              </div>
            </div>
          </div>

          <?php if(isset($_SESSION['error'])): ?>
          <div class="form-error" style="display:flex; color:red; margin-bottom: 20px;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i>
            <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
          </div>
          <?php endif; ?>

          <div class="form-btns">
            <a href="productos.php" class="btn-cancelar-form">Cancelar</a>
            <button type="submit" class="btn-guardar">
              <i class="fa-solid fa-floppy-disk"></i>
              Guardar producto
            </button>
          </div>

        </form>
      </div>
    </div>
  
</main>

<?php include '../includes/footer.php'; ?>
