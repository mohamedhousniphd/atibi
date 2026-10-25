-- TP Planner - Données d'exemple (adapté à votre schéma)

-- Utilisateurs (mot de passe: password123)
INSERT IGNORE INTO users (name, email, password, role) VALUES
('Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Professeur Dupont', 'teacher@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

-- Classes (teacher_id = id d'un user)
INSERT IGNORE INTO classes (name, teacher_id) VALUES
('Biologie 1A', 1),
('Chimie Lab 2', 1),
('Physique TP', 2);

-- Sessions TP
INSERT IGNORE INTO tp_sessions (title, class_id, objectives, skills, duration) VALUES
('Utilisation du microscope', 1, 'Apprendre à utiliser le microscope optique et préparer des lames.', 'observation, préparation de lames', 90),
('Extraction d''ADN', 1, 'Extraire l''ADN de tissu végétal.', 'pipetage, centrifugation', 120),
('Titrage acide-base', 2, 'Titrage avec phénolphtaléine.', 'titrage, burette', 60);

-- Étapes (tp_id = id de tp_sessions)
INSERT IGNORE INTO tp_steps (tp_id, step_number, description) VALUES
(1, 1, 'Nettoyer les lentilles avec papier à lentilles'),
(1, 2, 'Placer la lame sur la platine et fixer'),
(1, 3, 'Commencer par le plus faible grossissement');

-- Matériel
INSERT IGNORE INTO tp_materials (tp_id, name, type) VALUES
(1, 'Microscope', 'equipment'),
(1, 'Lame', 'reagent'),
(1, 'Lamelle', 'reagent'),
(2, 'Solution tampon', 'reagent'),
(2, 'Centrifugeuse', 'equipment');

-- Checklist (phase: before, during, after)
INSERT IGNORE INTO tp_checklists (tp_id, phase, item, is_done) VALUES
(1, 'before', 'Porter la blouse', 0),
(1, 'before', 'Vérifier l\'alimentation du microscope', 0),
(1, 'during', 'Noter les observations', 0),
(1, 'after', 'Ranger et nettoyer le microscope', 0);

-- Quiz (correct_option: A, B, C ou D)
INSERT IGNORE INTO tp_quizzes (tp_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES
(1, 'Par quel grossissement commence-t-on ?', '4x', '10x', '40x', '100x', 'A'),
(1, 'Quelle molette utilise-t-on en premier pour la mise au point ?', 'Grossière', 'Fine', 'Les deux', 'Platine', 'A');

-- Réponses quiz (quiz_id, selected_option)
INSERT IGNORE INTO quiz_answers (quiz_id, student_name, selected_option, score) VALUES
(1, 'Alice Martin', 'A', 1),
(2, 'Alice Martin', 'A', 1),
(1, 'Bob Dupont', 'B', 0),
(2, 'Bob Dupont', 'A', 1);
