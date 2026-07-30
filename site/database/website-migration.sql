-- ===================================================================
-- BMC Public Website — Database Migration
-- Run: mysql -u root bmc_portal < website-migration.sql
-- ===================================================================
USE bmc_portal;

-- ── Site Settings ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_settings (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    `key`      VARCHAR(100) NOT NULL UNIQUE,
    `value`    TEXT,
    label      VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Sliders ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_sliders (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    title       VARCHAR(255),
    subtitle    TEXT,
    image       VARCHAR(255) NOT NULL,
    button_text VARCHAR(100),
    button_url  VARCHAR(255),
    sort_order  INT DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── News ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_news (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    title        VARCHAR(500) NOT NULL,
    slug         VARCHAR(500) NOT NULL UNIQUE,
    excerpt      TEXT,
    content      LONGTEXT,
    image        VARCHAR(255),
    category     VARCHAR(100) DEFAULT 'General',
    tags         VARCHAR(500),
    is_published TINYINT(1) DEFAULT 1,
    is_featured  TINYINT(1) DEFAULT 0,
    published_at DATETIME,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Events ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_events (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    title        VARCHAR(500) NOT NULL,
    slug         VARCHAR(500) NOT NULL UNIQUE,
    description  LONGTEXT,
    image        VARCHAR(255),
    event_date   DATE NOT NULL,
    event_time   TIME,
    end_date     DATE,
    venue        VARCHAR(500),
    is_published TINYINT(1) DEFAULT 1,
    is_featured  TINYINT(1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Notices ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_notices (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    title        VARCHAR(500) NOT NULL,
    content      TEXT,
    category     VARCHAR(100) DEFAULT 'General',
    priority     ENUM('normal','important','urgent') DEFAULT 'normal',
    attachment   VARCHAR(255),
    expires_at   DATE,
    is_published TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Departments ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_departments (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    image       VARCHAR(255),
    icon        VARCHAR(100) DEFAULT 'fa-university',
    sort_order  INT DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ── Programs ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_programs (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    department_id  INT,
    name           VARCHAR(255) NOT NULL,
    description    TEXT,
    duration       VARCHAR(100),
    eligibility    TEXT,
    is_active      TINYINT(1) DEFAULT 1,
    FOREIGN KEY (department_id) REFERENCES site_departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Faculty ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_faculty (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) NOT NULL UNIQUE,
    designation   VARCHAR(255),
    department_id INT,
    qualification VARCHAR(500),
    email         VARCHAR(255),
    phone         VARCHAR(50),
    image         VARCHAR(255),
    bio           TEXT,
    research      TEXT,
    publications  TEXT,
    sort_order    INT DEFAULT 0,
    is_active     TINYINT(1) DEFAULT 1,
    FOREIGN KEY (department_id) REFERENCES site_departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Albums ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_albums (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Gallery ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_gallery (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    album_id   INT,
    title      VARCHAR(255),
    filename   VARCHAR(255) NOT NULL,
    caption    TEXT,
    sort_order INT DEFAULT 0,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES site_albums(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Videos ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_videos (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    title      VARCHAR(255) NOT NULL,
    url        VARCHAR(500) NOT NULL,
    thumbnail  VARCHAR(255),
    category   VARCHAR(100) DEFAULT 'General',
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Downloads ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_downloads (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    title      VARCHAR(500) NOT NULL,
    category   VARCHAR(100) DEFAULT 'General',
    filename   VARCHAR(255) NOT NULL,
    file_size  VARCHAR(50),
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Admission Forms ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_admission_forms (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    title      VARCHAR(255) NOT NULL,
    year       VARCHAR(20),
    filename   VARCHAR(255) NOT NULL,
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Testimonials ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_testimonials (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    designation VARCHAR(255),
    content     TEXT NOT NULL,
    image       VARCHAR(255),
    rating      TINYINT DEFAULT 5,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Stats ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_stats (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    label      VARCHAR(255) NOT NULL,
    value      INT NOT NULL DEFAULT 0,
    icon       VARCHAR(100) DEFAULT 'fa-star',
    suffix     VARCHAR(20) DEFAULT '+',
    sort_order INT DEFAULT 0
) ENGINE=InnoDB;

-- ── Partners ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_partners (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    logo       VARCHAR(255),
    website    VARCHAR(500),
    sort_order INT DEFAULT 0,
    is_active  TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- ── Contact Messages ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_contact_messages (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    phone      VARCHAR(50),
    subject    VARCHAR(500),
    message    TEXT NOT NULL,
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Site Admins ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_admins (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('super_admin','admin','content_manager') DEFAULT 'admin',
    is_active  TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Dynamic Pages ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_pages (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    title            VARCHAR(500) NOT NULL,
    slug             VARCHAR(500) NOT NULL UNIQUE,
    content          LONGTEXT,
    meta_title       VARCHAR(500),
    meta_description TEXT,
    is_published     TINYINT(1) DEFAULT 1,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Careers ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS site_careers (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    title        VARCHAR(500) NOT NULL,
    department   VARCHAR(255),
    description  TEXT,
    requirements TEXT,
    deadline     DATE,
    is_published TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Default admin (password: Admin@2025)
INSERT IGNORE INTO site_admins (name, email, password, role) VALUES
('Super Admin', 'admin@bmc.edu.pk', '$2y$12$8Kxz9QmN4pRvT6wL2jY3uOyW5nS7hD1eA0cF9gB4kM3iP6oU8vX2', 'super_admin');

-- Default settings
INSERT IGNORE INTO site_settings (`key`, `value`, `label`) VALUES
('site_name',           'Bahria Model College Bin Qasim', 'College Name'),
('site_tagline',        'Shaping Leaders of Tomorrow',    'Tagline'),
('site_address',        'Bin Qasim, Karachi, Pakistan',   'Address'),
('site_phone',          '+92 21 XXXX XXXX',               'Phone'),
('site_email',          'info@bmc.edu.pk',                'Email'),
('site_facebook',       '#',                              'Facebook URL'),
('site_twitter',        '#',                              'Twitter URL'),
('site_instagram',      '#',                              'Instagram URL'),
('site_youtube',        '#',                              'YouTube URL'),
('site_map_embed',      '',                               'Google Maps Embed URL'),
('principal_name',      'Prof. Dr. Muhammad Irfan',       'Principal Name'),
('principal_message',   'Welcome to Bahria Model College Bin Qasim — an institution dedicated to academic excellence, moral character, and the holistic development of every student. Our commitment is to nurture curious minds, build strong leaders, and shape responsible citizens who contribute meaningfully to society and the nation.',
                                                          'Principal Message'),
('principal_image',     '',                               'Principal Image'),
('about_short',         'Established under the Bahria Foundation, Bahria Model College Bin Qasim is a premier educational institution committed to delivering world-class education. We combine rigorous academics with character development to produce well-rounded graduates.', 'About (Short)'),
('vision',              'To be a center of academic excellence that cultivates innovative thinkers, compassionate leaders, and responsible global citizens.',
                                                          'Vision'),
('mission',             'To provide quality education through dedicated faculty, modern facilities, and a nurturing environment that fosters intellectual growth, ethical values, and lifelong learning.',
                                                          'Mission'),
('footer_text',         'Bahria Model College Bin Qasim is committed to academic excellence and the holistic development of students.', 'Footer Text'),
('whatsapp',            '',                               'WhatsApp Number'),
('admission_open',      '1',                              'Admissions Open (1=Yes, 0=No)'),
('admission_year',      '2025-26',                        'Admission Year');

-- Default stats
INSERT IGNORE INTO site_stats (label, value, icon, suffix, sort_order) VALUES
('Students Enrolled',   4500, 'fa-user-graduate', '+', 1),
('Expert Faculty',       185, 'fa-chalkboard-teacher', '+', 2),
('Years of Excellence',   30, 'fa-award', '+', 3),
('Programs Offered',      24, 'fa-book-open', '+', 4);

-- Default departments
INSERT IGNORE INTO site_departments (name, slug, description, icon, sort_order) VALUES
('Science & Technology',     'science-technology',     'Cutting-edge science programs including Physics, Chemistry, Biology, and Computer Science.', 'fa-flask', 1),
('Arts & Humanities',        'arts-humanities',        'A diverse range of arts and humanities programs fostering creativity and critical thinking.', 'fa-palette', 2),
('Commerce & Management',    'commerce-management',    'Business and commerce programs designed to build the next generation of entrepreneurs.', 'fa-chart-line', 3),
('Medical Sciences',         'medical-sciences',       'Pre-medical programs preparing students for careers in healthcare and medicine.', 'fa-heartbeat', 4),
('Engineering Preparatory',  'engineering-preparatory','Foundational engineering programs bridging school and university-level study.', 'fa-cogs', 5),
('ILC (Inclusive Learning)', 'ilc',                   'Inclusive Learning Center supporting students with diverse learning needs.', 'fa-hands-helping', 6);

-- Default testimonials
INSERT IGNORE INTO site_testimonials (name, designation, content, rating) VALUES
('Ahmed Khan',       'Alumni, Class of 2022', 'BMC gave me the foundation to excel at university. The faculty are dedicated and the environment is truly motivating.', 5),
('Sara Iqbal',       'Current Student, FSc Pre-Medical', 'The labs, library, and support from teachers here are exceptional. I am proud to be a BMC student.', 5),
('Muhammad Ali',     'Parent', 'My daughter has grown tremendously as a student and as a person since joining BMC. The discipline and care shown by staff is remarkable.', 5),
('Fatima Siddiqui',  'Alumni, Class of 2020', 'The values and work ethic I developed at BMC have shaped my professional life. I owe a great deal to this institution.', 5);
