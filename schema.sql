CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100),
    sex ENUM('M', 'F') DEFAULT 'M',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS operative_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    leader_id INT,
    encrypted_team_key TEXT,
    team_key_hash VARCHAR(255),
    FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS team_operative_units (
    team_id INT NOT NULL,
    operative_unit_id INT NOT NULL,
    PRIMARY KEY (team_id, operative_unit_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (operative_unit_id) REFERENCES operative_units(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_teams (
    user_id INT NOT NULL,
    team_id INT NOT NULL,
    can_edit_all BOOLEAN DEFAULT 0,
    PRIMARY KEY (user_id, team_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pazienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_cognome TEXT NOT NULL,
    codice_fiscale TEXT,
    sesso VARCHAR(1) CHECK (sesso IN ('M', 'F')),
    eta INT,
    altezza REAL,
    peso REAL,
    bmi REAL,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS patient_teams (
    paziente_id INT NOT NULL,
    team_id INT NOT NULL,
    PRIMARY KEY (paziente_id, team_id),
    FOREIGN KEY (paziente_id) REFERENCES pazienti(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS interventi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paziente_id INT NOT NULL,
    comorbilita TEXT,
    asa_score INT,
    tipo_intervento TEXT,
    urgenza BOOLEAN,
    euroscore_ii REAL,
    durata_cec_ore REAL,
    timing_iot_h REAL,
    created_by INT,
    operative_unit_id INT,
    FOREIGN KEY (paziente_id) REFERENCES pazienti(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (operative_unit_id) REFERENCES operative_units(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS rilevazioni_cliniche (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intervento_id INT NOT NULL,
    fase VARCHAR(50),
    fr REAL,
    tv REAL,
    tobin_index REAL,
    spo2 REAL,
    fio2 REAL,
    rox_index REAL,
    peep REAL,
    pressure_support REAL,
    nrs_dolore INT,
    nas_score REAL,
    maschera_venturi TEXT,
    hfno TEXT,
    niv TEXT,
    data_ora DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (intervento_id) REFERENCES interventi(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS esito_weaning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intervento_id INT NOT NULL UNIQUE,
    successo BOOLEAN,
    tipo_post_estubazione TEXT,
    fallimento_iot BOOLEAN,
    ore_da_estubazione_a_failure REAL,
    created_by INT,
    FOREIGN KEY (intervento_id) REFERENCES interventi(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(50),
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
);

CREATE TABLE IF NOT EXISTS clinical_ranges (
    parameter VARCHAR(50) PRIMARY KEY,
    category VARCHAR(50) DEFAULT 'rilevazioni',
    min_normal REAL,
    max_normal REAL,
    min_critical REAL,
    max_critical REAL,
    step REAL DEFAULT 0.1,
    unit VARCHAR(20)
);

CREATE TABLE IF NOT EXISTS tag_library (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50),
    name VARCHAR(100) NOT NULL,
    UNIQUE(category, name)
);

CREATE INDEX idx_intervento_id_rilevazioni ON rilevazioni_cliniche(intervento_id);
CREATE INDEX idx_fase_rilevazioni ON rilevazioni_cliniche(fase);
CREATE INDEX idx_intervento_id_weaning ON esito_weaning(intervento_id);
