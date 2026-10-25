-- TP Planner - Création des tables (si vous préférez les recréer)
-- Vous avez déjà créé les tables manuellement ; ce fichier sert de référence.

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150),
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('teacher','admin') NOT NULL DEFAULT 'teacher',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    teacher_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tp_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    class_id INT NULL,
    objectives TEXT,
    skills VARCHAR(255),
    duration INT DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tp_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tp_id INT NOT NULL,
    step_number INT NOT NULL,
    description TEXT,
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tp_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tp_id INT NOT NULL,
    name VARCHAR(255),
    type VARCHAR(50) DEFAULT 'reagent',
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tp_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tp_id INT NOT NULL,
    phase VARCHAR(20) NOT NULL,
    item VARCHAR(500),
    is_done TINYINT DEFAULT 0,
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tp_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tp_id INT NOT NULL,
    question TEXT,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_option CHAR(1) DEFAULT 'A',
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_name VARCHAR(150),
    selected_option CHAR(1),
    score INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES tp_quizzes(id) ON DELETE CASCADE
);

-- Utilisateur par défaut (mot de passe: password123)
INSERT INTO users (name, email, password, role) VALUES ('Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE email=email;
