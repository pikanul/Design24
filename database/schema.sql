-- Design24 Studio: initial admin foundation

CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Security audit trail for the admin sign-in form. Never store passwords here.
CREATE TABLE IF NOT EXISTS admin_login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    outcome VARCHAR(20) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_admin_login_attempts_email_time
    ON admin_login_attempts (email, occurred_at);
CREATE INDEX IF NOT EXISTS idx_admin_login_attempts_ip_time
    ON admin_login_attempts (ip_address, occurred_at);

-- Public-form abuse audit trail. Form values and uploaded filenames are not logged.
CREATE TABLE IF NOT EXISTS public_form_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    form_name VARCHAR(80) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    session_hash CHAR(64) NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_public_form_events_ip_time
    ON public_form_events (form_name, ip_address, created_at);
CREATE INDEX IF NOT EXISTS idx_public_form_events_session_time
    ON public_form_events (form_name, session_hash, created_at);

CREATE TABLE IF NOT EXISTS site_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_group VARCHAR(100) NOT NULL,
    setting_key VARCHAR(190) NOT NULL,
    setting_value TEXT NULL,
    setting_type VARCHAR(50) NOT NULL DEFAULT 'text',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (setting_group, setting_key)
);

CREATE TABLE IF NOT EXISTS hero_media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_type VARCHAR(10) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS team_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    short_name VARCHAR(80) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(50) NULL,
    cover_image VARCHAR(500) NULL,
    show_in_filters INTEGER NOT NULL DEFAULT 1,
    show_on_team_page INTEGER NOT NULL DEFAULT 1,
    display_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS team_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    designation VARCHAR(220) NOT NULL,
    department VARCHAR(160) NULL,
    team_group_id INTEGER NOT NULL,
    short_bio TEXT NULL,
    full_bio TEXT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(50) NULL,
    image VARCHAR(500) NULL,
    image_alt VARCHAR(220) NULL,
    employee_code VARCHAR(80) NULL,
    joining_year INTEGER NULL,
    specialization VARCHAR(250) NULL,
    location VARCHAR(200) NULL,
    linkedin_url VARCHAR(500) NULL,
    facebook_url VARCHAR(500) NULL,
    instagram_url VARCHAR(500) NULL,
    featured_member INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    display_order INTEGER NOT NULL DEFAULT 0,
    internal_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (team_group_id) REFERENCES team_groups(id) ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_team_groups_display ON team_groups (status, show_on_team_page, display_order);
CREATE INDEX IF NOT EXISTS idx_team_members_group_display ON team_members (team_group_id, status, display_order);

CREATE TABLE IF NOT EXISTS about_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subtitle VARCHAR(120) NOT NULL,
    heading VARCHAR(220) NOT NULL,
    description TEXT NOT NULL,
    button_one_text VARCHAR(100) NULL,
    button_one_link VARCHAR(500) NULL,
    button_two_text VARCHAR(100) NULL,
    button_two_link VARCHAR(500) NULL,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS about_slider_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    image VARCHAR(500) NOT NULL,
    title VARCHAR(180) NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS about_features (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    icon VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS about_counters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    icon VARCHAR(80) NOT NULL,
    number INTEGER NOT NULL DEFAULT 0,
    suffix VARCHAR(20) NULL,
    title VARCHAR(180) NOT NULL,
    description VARCHAR(300) NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_about_slider_display ON about_slider_images (status, sort_order, id);
CREATE INDEX IF NOT EXISTS idx_about_features_display ON about_features (status, sort_order, id);
CREATE INDEX IF NOT EXISTS idx_about_counters_display ON about_counters (status, sort_order, id);

CREATE TABLE IF NOT EXISTS visitor_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    visitor_hash CHAR(64) NOT NULL,
    page_path VARCHAR(255) NOT NULL,
    device_type VARCHAR(20) NOT NULL,
    browser VARCHAR(80) NOT NULL,
    operating_system VARCHAR(80) NOT NULL,
    referrer_domain VARCHAR(190) NULL,
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_visitor_analytics_visitor ON visitor_analytics (visitor_hash);
CREATE INDEX IF NOT EXISTS idx_visitor_analytics_date ON visitor_analytics (visited_at);
CREATE INDEX IF NOT EXISTS idx_visitor_analytics_device ON visitor_analytics (device_type, visited_at);
CREATE INDEX IF NOT EXISTS idx_visitor_analytics_page ON visitor_analytics (page_path, visited_at);

CREATE TABLE IF NOT EXISTS testimonials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_logo VARCHAR(500) NULL,
    client_image VARCHAR(500) NULL,
    company_name VARCHAR(180) NOT NULL,
    person_name VARCHAR(180) NULL,
    designation VARCHAR(180) NULL,
    location VARCHAR(180) NULL,
    short_feedback VARCHAR(500) NOT NULL,
    full_feedback TEXT NOT NULL,
    rating INTEGER NOT NULL DEFAULT 5,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_testimonials_display ON testimonials (status, sort_order, id);

CREATE TABLE IF NOT EXISTS site_videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(220) NOT NULL,
    description TEXT NULL,
    video_type VARCHAR(20) NOT NULL DEFAULT 'url',
    video_url VARCHAR(500) NULL,
    video_file VARCHAR(500) NULL,
    thumbnail VARCHAR(500) NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_site_videos_display ON site_videos (status, display_order, id);

CREATE TABLE IF NOT EXISTS office_media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_type VARCHAR(10) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    title VARCHAR(220) NULL,
    description TEXT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    status INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_office_media_display ON office_media (status, display_order, id);

CREATE TABLE IF NOT EXISTS portfolio_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    description TEXT NULL,
    parent_id INTEGER NULL,
    menu_order INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES portfolio_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS portfolio_projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    short_description VARCHAR(600) NULL,
    full_description TEXT NULL,
    featured_image VARCHAR(500) NULL,
    project_status VARCHAR(40) NOT NULL DEFAULT 'Completed',
    project_location VARCHAR(180) NULL,
    client_name VARCHAR(180) NULL,
    completion_year VARCHAR(20) NULL,
    project_area VARCHAR(80) NULL,
    youtube_url VARCHAR(500) NULL,
    is_featured INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    display_order INTEGER NOT NULL DEFAULT 0,
    seo_title VARCHAR(220) NULL,
    seo_description VARCHAR(320) NULL,
    meta_keywords VARCHAR(320) NULL,
    og_image VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES portfolio_categories(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS portfolio_gallery (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    caption VARCHAR(220) NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS portfolio_videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    youtube_url VARCHAR(500) NOT NULL,
    video_title VARCHAR(220) NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES portfolio_projects(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_portfolio_categories_menu ON portfolio_categories (is_active, parent_id, menu_order, name);
CREATE INDEX IF NOT EXISTS idx_portfolio_projects_display ON portfolio_projects (is_active, is_featured, display_order, id);
CREATE INDEX IF NOT EXISTS idx_portfolio_projects_category ON portfolio_projects (category_id, is_active, display_order);
CREATE INDEX IF NOT EXISTS idx_portfolio_gallery_project ON portfolio_gallery (project_id, display_order, id);
CREATE INDEX IF NOT EXISTS idx_portfolio_videos_project ON portfolio_videos (project_id, display_order, id);

-- Consultation booking system
CREATE TABLE IF NOT EXISTS consultation_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    business_days VARCHAR(60) NOT NULL DEFAULT '1,2,3,4,5,6',
    admin_email VARCHAR(190) NULL,
    smtp_host VARCHAR(190) NULL,
    smtp_port INTEGER NULL,
    smtp_username VARCHAR(190) NULL,
    smtp_password_encrypted TEXT NULL,
    smtp_encryption VARCHAR(20) NULL,
    sender_name VARCHAR(190) NULL,
    whatsapp_number VARCHAR(40) NULL,
    whatsapp_template TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS consultation_bookings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference_number VARCHAR(40) NOT NULL UNIQUE,
    full_name VARCHAR(180) NOT NULL,
    mobile_number VARCHAR(40) NOT NULL,
    email VARCHAR(190) NULL,
    project_type VARCHAR(80) NOT NULL,
    consultation_type VARCHAR(60) NOT NULL,
    consultation_date DATE NOT NULL,
    time_slot VARCHAR(20) NOT NULL,
    project_location VARCHAR(300) NULL,
    project_size VARCHAR(80) NULL,
    budget_range VARCHAR(120) NULL,
    project_details TEXT NULL,
    terms_accepted INTEGER NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    admin_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (consultation_date, time_slot)
);

CREATE TABLE IF NOT EXISTS consultation_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    booking_id INTEGER NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INTEGER NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES consultation_bookings(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_consultation_status_date ON consultation_bookings (status, consultation_date);
