-- Release: 2026-03-08_client_reviews_table
-- Author: Antigravity
-- Safe: idempotent
-- Notes: Creates a client_reviews table to store per-reservation review history.
--        The rating_review column on clients remains for the latest quick read.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS client_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL, 
    reservation_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_reservation_review (reservation_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
