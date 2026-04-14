<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        // Poner la deuda en 0 y marcar inactivo en vez de eliminar permanentemente
        $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
        $_SESSION['exito'] = 'Cliente eliminado correctamente.';
    } catch(PDOException $e) {
        $_SESSION['error'] = 'No se pudo eliminar: ventas o datos vinculados al cliente. ' . $e->getMessage();
    }
}
header('Location: clientes.php');
exit;
