-- Table pour les utilisateurs Juniors
CREATE TABLE juniors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    birthdate DATE NOT NULL,
    school VARCHAR(255),
    status ENUM('student', 'graduate', 'junior') NOT NULL,
    country VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    experience INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table pour les entreprises
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    siret VARCHAR(14) NOT NULL,
    company_type ENUM('PME', 'PMI', 'STARTUP') NOT NULL,
    domain VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insertion de données de test
INSERT INTO juniors (email, password, firstname, lastname, birthdate, school, status, country, city, experience) 
VALUES ('junior@test.com', '$2y$10$YOUR_HASHED_PASSWORD', 'Jean', 'Dupont', '2000-01-01', 'Université XYZ', 'student', 'France', 'Paris', 0);

INSERT INTO companies (email, password, name, city, country, siret, company_type, domain) 
VALUES ('entreprise@test.com', '$2y$10$YOUR_HASHED_PASSWORD', 'TechCorp', 'Lyon', 'France', '12345678901234', 'STARTUP', 'Technologie');