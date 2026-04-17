CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user'
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

CREATE TABLE IF NOT EXISTS pazienti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_cognome TEXT NOT NULL,
    sesso VARCHAR(1) CHECK (sesso IN ('M', 'F')),
    eta INT,
    altezza REAL,
    peso REAL,
    bmi REAL
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
    FOREIGN KEY (paziente_id) REFERENCES pazienti(id) ON DELETE CASCADE
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
    FOREIGN KEY (intervento_id) REFERENCES interventi(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS esito_weaning (
    id INT AUTO_INCREMENT PRIMARY KEY,
    intervento_id INT NOT NULL UNIQUE,
    successo BOOLEAN,
    tipo_post_estubazione TEXT,
    fallimento_iot BOOLEAN,
    ore_da_estubazione_a_failure REAL,
    FOREIGN KEY (intervento_id) REFERENCES interventi(id) ON DELETE CASCADE
);

-- Indici richiesti
CREATE INDEX idx_intervento_id_rilevazioni ON rilevazioni_cliniche(intervento_id);
CREATE INDEX idx_fase_rilevazioni ON rilevazioni_cliniche(fase);
CREATE INDEX idx_intervento_id_weaning ON esito_weaning(intervento_id);
