-- Datenbank + Tabellen für das Antragsprogramm Unterrichtsorganisation
CREATE DATABASE IF NOT EXISTS antragsprogramm
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE antragsprogramm;

-- Abteilungen (zur Routung an den zuständigen Abteilungsleiter)
CREATE TABLE IF NOT EXISTS abteilung (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

-- Benutzer inkl. Rolle
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(120) NOT NULL,
  role          ENUM('LK','AL','StSL','SL') NOT NULL,
  abteilung_id  INT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_abteilung FOREIGN KEY (abteilung_id)
    REFERENCES abteilung(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Anträge
CREATE TABLE IF NOT EXISTS antraege (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  antragsnummer   VARCHAR(20)  NOT NULL UNIQUE,
  antragsteller_id INT         NOT NULL,
  abteilung_id    INT NULL,
  anlass          ENUM('vertretung','tausch','klasseAbwesend') NOT NULL,
  daten           LONGTEXT NOT NULL,           -- JSON mit anlass-spezifischen Feldern
  status          ENUM('gestellt','al_abgezeichnet','stsl_abgezeichnet','genehmigt','abgelehnt')
                    NOT NULL DEFAULT 'gestellt',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_antrag_user FOREIGN KEY (antragsteller_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_antrag_abteilung FOREIGN KEY (abteilung_id)
    REFERENCES abteilung(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bearbeitungs-/Log-Einträge (jede relevante Aktion)
CREATE TABLE IF NOT EXISTS antrag_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  antrag_id  INT NOT NULL,
  user_id    INT NULL,
  aktion     ENUM('erstellt','al_abgezeichnet','stsl_abgezeichnet','genehmigt','abgelehnt') NOT NULL,
  kommentar  VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_antrag FOREIGN KEY (antrag_id)
    REFERENCES antraege(id) ON DELETE CASCADE,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
