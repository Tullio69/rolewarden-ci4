-- Eseguito una sola volta, alla prima creazione del volume dati.
-- Il database `rolewarden` lo crea gia' l'immagine via MARIADB_DATABASE:
-- qui si aggiunge quello dedicato alla suite di test.

CREATE DATABASE IF NOT EXISTS `rolewarden_test`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

GRANT ALL PRIVILEGES ON `rolewarden_test`.* TO 'root'@'%';
FLUSH PRIVILEGES;
