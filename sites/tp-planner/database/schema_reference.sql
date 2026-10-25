-- TP Planner - Schéma des tables

-- users(id, name, email, password, role ENUM('admin'), created_at)  — administrateurs uniquement
-- students(id, name, email, password, class_id → classes.id, created_at)  — professeurs stagiaires
-- classes(id, name, teacher_id → users.id, created_at)
-- tp_sessions, tp_steps, tp_materials, tp_checklists, tp_quizzes, quiz_answers
-- site_settings(setting_key, setting_value) — optionnel (page d’accueil)
