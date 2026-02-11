CREATE TABLE job_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position ENUM('cajero', 'maestro_sanguchero') NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    pregunta1 TEXT NOT NULL,
    pregunta2 TEXT NOT NULL,
    pregunta3 TEXT NOT NULL,
    score INT DEFAULT 0,
    attempts INT DEFAULT 1,
    keyword_analysis JSON,
    status ENUM('pending', 'reviewed', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);