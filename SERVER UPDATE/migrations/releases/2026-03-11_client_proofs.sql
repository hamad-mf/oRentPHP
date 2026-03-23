-- Release: 2026-03-11_client_proofs
-- Author: Codex
-- Safe: idempotent
-- Notes: Adds client_proofs table to store multiple proof documents per client (max 5 handled in app).

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS client_proofs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    type VARCHAR(50) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client_proofs_client (client_id),
    CONSTRAINT fk_client_proofs_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
