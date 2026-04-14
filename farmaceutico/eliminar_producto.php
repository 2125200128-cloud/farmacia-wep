<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['exito'] = "Producto eliminado permanentemente.";
    } catch(Exception $e) {
        // Si viola integridad referencial (ej., ventas), ocultarlo lógicamente
        if ($e->getCode() == '23000' || strpos($e->getMessage(), 'foreign key') !== false) {
            $pdo->prepare("UPDATE productos SET estado = 'inactivo' WHERE id = ?")->execute([$id]);
            $_SESSION['exito'] = "El producto no pudo borrarse porque tiene historial, pero ha sido DESACTIVADO/OCULTADO exitosamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar: " . $e->getMessage();
        }
    }
}

header("Location: productos.php");
exit;
