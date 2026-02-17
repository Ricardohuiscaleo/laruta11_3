-- Tabla para almacenar el token de Gmail de forma persistente
CREATE TABLE IF NOT EXISTS gmail_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    expires_at INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar registro inicial (se actualizará con el token real)
INSERT INTO gmail_tokens (id, access_token, refresh_token, expires_at) 
VALUES (1, '', '', 0)
ON DUPLICATE KEY UPDATE id=1;
