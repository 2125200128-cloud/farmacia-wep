-- ============================================================
-- Motor de Promociones — FarmaControl
-- Ejecutar en la base de datos: controlventafarmacia
-- ============================================================

CREATE TABLE IF NOT EXISTS `promociones` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`            VARCHAR(120)  NOT NULL,

    -- Tipo de descuento
    `tipo`              ENUM('porcentaje','monto_fijo','2x1','producto_gratis') NOT NULL DEFAULT 'porcentaje',
    `valor`             DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT 'Porcentaje o monto según tipo. Ignorado en 2x1.',

    -- Vigencia
    `fecha_inicio`      DATE NOT NULL,
    `fecha_fin`         DATE NOT NULL,

    -- Estado
    `activa`            TINYINT(1) NOT NULL DEFAULT 1,

    -- Condición de aplicación
    `condicion_tipo`    ENUM('producto_especifico','categoria','monto_minimo','') NOT NULL DEFAULT ''
        COMMENT 'Sin condición (vacío) = aplica siempre',
    `condicion_valor`   VARCHAR(50) DEFAULT NULL
        COMMENT 'ID de producto/categoría o monto mínimo según condicion_tipo',

    -- Producto de regalo (para tipo producto_gratis)
    `producto_regalo_id` INT UNSIGNED DEFAULT NULL
        COMMENT 'ID del producto que se regala. NULL si no aplica.',

    -- Auditoría
    `creado_en`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_promo_fechas`  (`fecha_inicio`, `fecha_fin`),
    INDEX `idx_promo_activa`  (`activa`),
    INDEX `idx_promo_regalo`  (`producto_regalo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tabla de promociones del motor de descuentos';

-- Datos de ejemplo (opcional, comentar si no se desean)
-- INSERT INTO `promociones` (nombre, tipo, valor, fecha_inicio, fecha_fin, activa, condicion_tipo, condicion_valor)
-- VALUES ('Descuento Mayo 10%', 'porcentaje', 10, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1, 'monto_minimo', '150');
