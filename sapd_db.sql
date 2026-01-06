CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'user', -- 'admin' or 'user'
    status VARCHAR(20) DEFAULT 'pending', -- 'active', 'pending', 'unverified'
    verification_code INT(6) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Create Events Table (For Calendar & Notifications)
CREATE TABLE IF NOT EXISTS events (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    start_event DATETIME NOT NULL,
    end_event DATETIME NOT NULL,
    color VARCHAR(20) DEFAULT '#4318ff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);