-- WARNING: This script deletes card catalog data and all user copies.
-- Use only in local development.

USE mtg_collection_manager;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM copias;
DELETE FROM impresiones;
DELETE FROM carta_color;
DELETE FROM cartas;
DELETE FROM ediciones;

ALTER TABLE copias AUTO_INCREMENT = 1;
ALTER TABLE impresiones AUTO_INCREMENT = 1;
ALTER TABLE cartas AUTO_INCREMENT = 1;
ALTER TABLE ediciones AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;