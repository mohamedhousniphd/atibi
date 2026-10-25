-- TP Planner - New tables (SAFE: does not modify existing tables)
-- Run in database: tp_planner

-- 1) Students (ONLY created if missing)
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  class_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_students_class (class_id),
  CONSTRAINT fk_students_class
    FOREIGN KEY (class_id) REFERENCES classes(id)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Per-student TP progress (status)
CREATE TABLE IF NOT EXISTS student_tp_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  tp_id INT NOT NULL,
  status ENUM('not_started','in_progress','done') NOT NULL DEFAULT 'not_started',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_tp (student_id, tp_id),
  INDEX idx_progress_tp (tp_id),
  CONSTRAINT fk_progress_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_progress_tp
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Per-student TP score (0..100)
CREATE TABLE IF NOT EXISTS student_tp_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  tp_id INT NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_score_student_tp (student_id, tp_id),
  INDEX idx_scores_tp (tp_id),
  CONSTRAINT fk_scores_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_scores_tp
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Quiz per-question scores (4 points per question by default)
CREATE TABLE IF NOT EXISTS quiz_question_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  tp_id INT NOT NULL,
  question_id INT NOT NULL, -- references tp_quizzes.id
  selected_option CHAR(1) NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  score INT NOT NULL DEFAULT 0, -- 0 or 4 by default
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_question (student_id, question_id),
  INDEX idx_qqs_tp (tp_id),
  INDEX idx_qqs_student (student_id),
  CONSTRAINT fk_qqs_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_qqs_tp
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_qqs_question
    FOREIGN KEY (question_id) REFERENCES tp_quizzes(id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Quiz attempt summary per TP (total points and percentage)
CREATE TABLE IF NOT EXISTS quiz_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  tp_id INT NOT NULL,
  total_points INT NOT NULL,
  max_points INT NOT NULL,
  percentage DECIMAL(5,2) NOT NULL,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt_student_tp (student_id, tp_id),
  INDEX idx_attempt_tp (tp_id),
  INDEX idx_attempt_student (student_id),
  CONSTRAINT fk_attempt_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_attempt_tp
    FOREIGN KEY (tp_id) REFERENCES tp_sessions(id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

