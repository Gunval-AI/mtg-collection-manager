-- CREATE DATABASE IF NOT EXISTS mtg_collection_manager;
-- USE mtg_collection_manager;

-- ========================
-- USUARIOS
-- ========================
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ========================
-- COLECCIONES
-- ========================
CREATE TABLE colecciones (
    id_coleccion INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (usuario_id, nombre),

    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id_usuario)
        ON DELETE CASCADE,

    INDEX idx_colecciones_usuario_id (usuario_id)
) ENGINE=InnoDB;

-- ========================
-- CARTAS
-- ========================
CREATE TABLE cartas (
    id_carta INT AUTO_INCREMENT PRIMARY KEY,
    oracle_id CHAR(36) UNIQUE NOT NULL,
    nombre_en VARCHAR(255) NOT NULL,
    nombre_es VARCHAR(255) NULL,
    tipo VARCHAR(255),
    oracle_texto TEXT,
    mana VARCHAR(50),
    cmc SMALLINT,
    fuerza VARCHAR(10),
    resistencia VARCHAR(10),
    lealtad VARCHAR(10),

    INDEX idx_cartas_nombre_en (nombre_en),
    INDEX idx_cartas_nombre_es (nombre_es)
) ENGINE=InnoDB;

-- ========================
-- COLORES
-- ========================
-- Static catalog of MTG colors, prepared for future use.
CREATE TABLE colores (
    id_color INT AUTO_INCREMENT PRIMARY KEY,
    codigo CHAR(1) UNIQUE NOT NULL,
    nombre VARCHAR(20) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- ========================
-- CARTA_COLOR
-- ========================
-- This table is currently not used in the application.
-- It is kept for future features such as filtering or grouping cards by color.
CREATE TABLE carta_color (
    carta_id INT NOT NULL,
    color_id INT NOT NULL,

    PRIMARY KEY (carta_id, color_id),

    FOREIGN KEY (carta_id)
        REFERENCES cartas(id_carta)
        ON DELETE CASCADE,

    FOREIGN KEY (color_id)
        REFERENCES colores(id_color)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ========================
-- EDICIONES
-- ========================
CREATE TABLE ediciones (
    id_edicion INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    codigo VARCHAR(20) UNIQUE NOT NULL,
    fecha_lanzamiento DATE
) ENGINE=InnoDB;

-- ========================
-- RAREZAS
-- ========================
CREATE TABLE rarezas (
    id_rareza INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- ========================
-- CONDICIONES
-- ========================
CREATE TABLE condiciones (
    id_condicion INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) UNIQUE NOT NULL,
    descripcion VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- ========================
-- IMPRESIONES
-- ========================
CREATE TABLE impresiones (
    id_impresion INT AUTO_INCREMENT PRIMARY KEY,
    scryfall_id CHAR(36) UNIQUE NOT NULL,
    carta_id INT NOT NULL,
    edicion_id INT NOT NULL,
    rareza_id INT NOT NULL,
    numero_coleccion VARCHAR(20),

    imagen_small VARCHAR(512) NULL,
    imagen_normal VARCHAR(512) NULL,
    scryfall_uri VARCHAR(512) NULL,

    FOREIGN KEY (carta_id)
        REFERENCES cartas(id_carta)
        ON DELETE RESTRICT,

    FOREIGN KEY (edicion_id)
        REFERENCES ediciones(id_edicion)
        ON DELETE RESTRICT,

    FOREIGN KEY (rareza_id)
        REFERENCES rarezas(id_rareza)
        ON DELETE RESTRICT,

    INDEX idx_impresiones_carta_id (carta_id),
    INDEX idx_impresiones_edicion_id (edicion_id),
    INDEX idx_impresiones_rareza_id (rareza_id),
    INDEX idx_impresiones_edicion_numero (edicion_id, numero_coleccion)
) ENGINE=InnoDB;

-- ========================
-- COPIAS
-- ========================
CREATE TABLE copias (
    id_copia INT AUTO_INCREMENT PRIMARY KEY,
    coleccion_id INT NOT NULL,
    impresion_id INT NOT NULL,
    condicion_id INT NOT NULL,
    idioma ENUM('EN', 'ES') NOT NULL DEFAULT 'EN',
    es_foil BOOLEAN DEFAULT FALSE,
    precio_compra DECIMAL(10,2),
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (coleccion_id)
        REFERENCES colecciones(id_coleccion)
        ON DELETE CASCADE,

    FOREIGN KEY (impresion_id)
        REFERENCES impresiones(id_impresion)
        ON DELETE RESTRICT,

    FOREIGN KEY (condicion_id)
        REFERENCES condiciones(id_condicion)
        ON DELETE RESTRICT,

    INDEX idx_copias_coleccion_id (coleccion_id),
    INDEX idx_copias_impresion_id (impresion_id),
    INDEX idx_copias_condicion_id (condicion_id),
    INDEX idx_copias_idioma (idioma)
) ENGINE=InnoDB;