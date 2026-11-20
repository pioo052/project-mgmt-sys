-- URIMS database schema
-- University Research & Innovation Management System (KIUT)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS expenditure;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS funding_applications;
DROP TABLE IF EXISTS evaluation_results;
DROP TABLE IF EXISTS judges;
DROP TABLE IF EXISTS competitions;
DROP TABLE IF EXISTS innovation_teams;
DROP TABLE IF EXISTS innovation_projects;
DROP TABLE IF EXISTS publications;
DROP TABLE IF EXISTS research_teams;
DROP TABLE IF EXISTS lecturer_research;
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS milestones;
DROP TABLE IF EXISTS proposals;
DROP TABLE IF EXISTS research_projects;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS semesters;
DROP TABLE IF EXISTS academic_years;
DROP TABLE IF EXISTS programmes;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS faculties;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE faculties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(12) NOT NULL,
  name VARCHAR(150) NOT NULL,
  dean VARCHAR(120) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(40) NULL,
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE departments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT NULL,
  code VARCHAR(12) NOT NULL,
  name VARCHAR(150) NOT NULL,
  hod VARCHAR(120) NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(40) NULL,
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_department_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE programmes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(180) NOT NULL,
  level VARCHAR(40) NOT NULL DEFAULT 'Undergraduate',
  duration_years TINYINT UNSIGNED NOT NULL DEFAULT 3,
  description TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_programme_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE academic_years (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE semesters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT NULL,
  name VARCHAR(60) NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_semester_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'student',
  faculty_id INT NULL,
  department_id INT NULL,
  phone VARCHAR(40) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  CONSTRAINT fk_user_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL,
  CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  selector CHAR(18) NOT NULL UNIQUE,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  reg_no VARCHAR(40) NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(150) NULL,
  phone VARCHAR(40) NULL,
  programme_id INT NULL,
  department_id INT NULL,
  faculty_id INT NULL,
  level VARCHAR(30) NOT NULL DEFAULT 'Undergraduate',
  enrollment_year INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_students_dept (department_id),
  CONSTRAINT fk_student_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_programme FOREIGN KEY (programme_id) REFERENCES programmes (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_student_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE research_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NULL,
  title VARCHAR(220) NOT NULL,
  abstract TEXT NULL,
  faculty_id INT NULL,
  department_id INT NULL,
  student_id INT NULL,
  supervisor_id INT NULL,
  academic_year_id INT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  start_date DATE NULL,
  end_date DATE NULL,
  budget DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_projects_status (status),
  INDEX idx_projects_dept (department_id),
  CONSTRAINT fk_project_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL,
  CONSTRAINT fk_project_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_project_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE SET NULL,
  CONSTRAINT fk_project_supervisor FOREIGN KEY (supervisor_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_project_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE proposals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  title VARCHAR(220) NOT NULL,
  abstract TEXT NULL,
  submitted_by INT NULL,
  supervisor_id INT NULL,
  department_id INT NULL,
  budget DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  submitted_at DATETIME NULL,
  decision_at DATETIME NULL,
  comments TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_proposals_status (status),
  CONSTRAINT fk_proposal_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE SET NULL,
  CONSTRAINT fk_proposal_submitter FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_proposal_supervisor FOREIGN KEY (supervisor_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_proposal_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE milestones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  due_date DATE NULL,
  completed_date DATE NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_milestone_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  title VARCHAR(180) NOT NULL,
  document_type VARCHAR(60) NOT NULL DEFAULT 'Report',
  file_path VARCHAR(255) NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT NULL,
  version VARCHAR(20) NOT NULL DEFAULT '1.0',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_document_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evaluations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  evaluator_id INT NULL,
  criteria_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  methodology_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  presentation_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  total_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  recommendation VARCHAR(40) NOT NULL DEFAULT 'approved',
  comments TEXT NULL,
  evaluated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evaluation_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_evaluation_user FOREIGN KEY (evaluator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lecturer_research (
  id INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id INT NULL,
  title VARCHAR(220) NOT NULL,
  field VARCHAR(120) NULL,
  department_id INT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  budget DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(30) NOT NULL DEFAULT 'ongoing',
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lecturer_user FOREIGN KEY (lecturer_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_lecturer_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE research_teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  leader_id INT NULL,
  department_id INT NULL,
  focus_area VARCHAR(150) NULL,
  members_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_team_leader FOREIGN KEY (leader_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_team_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE publications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  department_id INT NULL,
  author_id INT NULL,
  title VARCHAR(220) NOT NULL,
  authors VARCHAR(255) NULL,
  journal VARCHAR(180) NULL,
  publication_type VARCHAR(60) NOT NULL DEFAULT 'Journal Article',
  publication_year INT NULL,
  doi VARCHAR(120) NULL,
  indexation VARCHAR(60) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_publication_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE SET NULL,
  CONSTRAINT fk_publication_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
  CONSTRAINT fk_publication_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE innovation_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  description TEXT NULL,
  leader_id INT NULL,
  department_id INT NULL,
  stage VARCHAR(40) NOT NULL DEFAULT 'idea',
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_innovation_leader FOREIGN KEY (leader_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_innovation_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE innovation_teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  innovation_project_id INT NULL,
  name VARCHAR(150) NOT NULL,
  leader_id INT NULL,
  members_count TINYINT UNSIGNED NOT NULL DEFAULT 2,
  role_focus VARCHAR(150) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_iteam_project FOREIGN KEY (innovation_project_id) REFERENCES innovation_projects (id) ON DELETE CASCADE,
  CONSTRAINT fk_iteam_leader FOREIGN KEY (leader_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE competitions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  theme VARCHAR(180) NULL,
  venue VARCHAR(150) NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE judges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  competition_id INT NULL,
  expertise VARCHAR(150) NULL,
  organization VARCHAR(150) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_judge_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_judge_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE evaluation_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  competition_id INT NULL,
  innovation_project_id INT NULL,
  judge_id INT NULL,
  innovation_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  feasibility_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  impact_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  total_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  rank_position TINYINT UNSIGNED NULL,
  comments TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'submitted',
  awarded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_result_competition FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE SET NULL,
  CONSTRAINT fk_result_project FOREIGN KEY (innovation_project_id) REFERENCES innovation_projects (id) ON DELETE SET NULL,
  CONSTRAINT fk_result_judge FOREIGN KEY (judge_id) REFERENCES judges (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE funding_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(30) NULL,
  project_id INT NULL,
  applicant_id INT NULL,
  amount_requested DECIMAL(14,2) NOT NULL DEFAULT 0,
  funding_source VARCHAR(150) NULL,
  purpose TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  submitted_at DATETIME NULL,
  decision_at DATETIME NULL,
  remarks TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_funding_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE SET NULL,
  CONSTRAINT fk_funding_applicant FOREIGN KEY (applicant_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  project_id INT NULL,
  category VARCHAR(120) NOT NULL,
  allocated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  fiscal_year VARCHAR(20) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'approved',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_budget_project FOREIGN KEY (project_id) REFERENCES research_projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expenditure (
  id INT AUTO_INCREMENT PRIMARY KEY,
  budget_id INT NULL,
  description VARCHAR(220) NOT NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  spent_on DATE NULL,
  recorded_by INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'approved',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expenditure_budget FOREIGN KEY (budget_id) REFERENCES budgets (id) ON DELETE CASCADE,
  CONSTRAINT fk_expenditure_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'info',
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(60) NOT NULL,
  module VARCHAR(60) NOT NULL,
  description VARCHAR(255) NOT NULL,
  ip_address VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
