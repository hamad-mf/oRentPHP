    -- CRM Lead & Pipeline — Migration Script
    -- Run in phpMyAdmin on the live server

    CREATE TABLE IF NOT EXISTS leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(50) NOT NULL,
        email VARCHAR(255) DEFAULT NULL,
        inquiry_type ENUM('daily','weekly','monthly','other') DEFAULT 'daily',
        vehicle_interest VARCHAR(255) DEFAULT NULL,
        source ENUM('walk_in','phone','whatsapp','instagram','referral','website','other') DEFAULT 'phone',
        status ENUM('new','contacted','interested','negotiation','closed_won','closed_lost') DEFAULT 'new',
        lost_reason TEXT DEFAULT NULL,
        assigned_to VARCHAR(100) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        converted_client_id INT DEFAULT NULL,
        converted_reservation_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;

    CREATE TABLE IF NOT EXISTS lead_followups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        type ENUM('call','meeting','email','whatsapp') NOT NULL DEFAULT 'call',
        scheduled_at DATETIME NOT NULL,
        notes TEXT DEFAULT NULL,
        is_done TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;

    CREATE TABLE IF NOT EXISTS lead_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        note TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;
