<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
requiereRol(['admin', 'encargado']);

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        $_SESSION['exito'] = 'Empleado eliminado correctamente.';
    } catch(PDOException $e) {
        if ($e->getCode() == '23000' || strpos($e->getMessage(), 'foreign key') !== false) {
            // Soft delete
            $pdo->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = ?")->execute([$id]);
            $_SESSION['exito'] = 'El empleado tiene registros en el sistema (ventas, auditorías), por lo que fue DESACTIVADO/OCULTADO exitosamente.';
        } else {
            $_SESSION['error'] = 'No se pudo eliminar: ' . $e->getMessage();
        }
    }
}
header('Location: empleados.php');
exit;
