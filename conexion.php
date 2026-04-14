
<?php
// CONFIGURACIÓN PARA CONEXIÓN INTERNA (Web en Railway -> BD en Railway)
$host = "mysql.railway.internal"; // CAMBIA ESTO
$port = 3306;                     // EL PUERTO INTERNO ES SIEMPRE 3306
$user = "root";
$password = "rclNByooYtLqkTnHFgfGwITfXXvsLovN"; // Tu password es correcta
$database = "railway";





mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Para que lance excepciones manejables
try {
    $conexion = new mysqli($host, $user, $password, $database, $port);
    $conexion->set_charset("utf8mb4");
    
    echo "<h3>✅ Conexión exitosa a la base de datos MySQL en Railway</h3>";

    // 4. Generar un ejemplo de consulta SELECT a la tabla productos
    $query = "SELECT * FROM productos LIMIT 5";
    $resultado = $conexion->query($query);

    if ($resultado->num_rows > 0) {
        echo "<h4>Datos de la tabla 'productos':</h4>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Nombre / Descripción</th><th>Precio</th></tr>";
        
        while ($fila = $resultado->fetch_assoc()) {
            // Ajusta los nombres de las columnas a como los tengas en tu base de datos (id, nombre, descripcion, precio, etc.)
            $id = $fila['id'] ?? $fila['producto_id'] ?? 'N/A';
            $nombre = $fila['nombre'] ?? $fila['descripcion'] ?? 'N/A';
            $precio = $fila['precio'] ?? $fila['precio_venta'] ?? 'N/A';
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($id) . "</td>";
            echo "<td>" . htmlspecialchars($nombre) . "</td>";
            echo "<td>" . htmlspecialchars($precio) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>La tabla 'productos' existe, pero no tiene registros o no encontró ninguna fila.</p>";
    }

} catch (mysqli_sql_exception $e) {
    // 3. Implementar manejo de errores claro
    die("<h3>❌ Error de Conexión:</h3><p>No se pudo conectar a Railway. Detalles del error: " . $e->getMessage() . "</p>");
}
?>
