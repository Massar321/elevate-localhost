-- Table pour les encadrants
CREATE TABLE mentors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    years_experience INT NOT NULL,
    activity_domain VARCHAR(255) NOT NULL,
    company VARCHAR(255) NOT NULL,
    employment_status ENUM('Freelance', 'Salarié', 'Autre') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Mise à jour de ppe_registrations pour inclure le mentor
ALTER TABLE ppe_registrations ADD COLUMN mentor_id INT NULL,
ADD FOREIGN KEY (mentor_id) REFERENCES mentors(id);

-- Exemple d'encadrant
INSERT INTO mentors (firstname, lastname, email, password, years_experience, activity_domain, company, employment_status)
VALUES ('Jean', 'Dupont', 'jean.dupont@test.com', '$2y$10$' . password_hash('mentor1234', PASSWORD_DEFAULT), 10, 'Data Science', 'TechCorp', 'Salarié'); 


ALTER TABLE ppe_projects ADD COLUMN created_by INT NULL,
ADD FOREIGN KEY (created_by) REFERENCES mentors(id);

-- Mettre à jour les projets existants avec un created_by (exemple)
UPDATE ppe_projects SET created_by = 1 WHERE id IN (1, 2); -- Remplace 1 par l'ID du mentor existant


ALTER TABLE ppe_registrations ADD COLUMN project_id INT NULL,
ADD FOREIGN KEY (project_id) REFERENCES ppe_projects(id);

ALTER TABLE juniors ADD COLUMN profile_picture VARCHAR(255) NULL;
-- Exemple de mise à jour pour un utilisateur
UPDATE juniors SET profile_picture = 'uploads/default_profile.jpg' WHERE id = 3;

ALTER TABLE ppe_projects ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
-- Mettre à jour les projets existants (exemple)
UPDATE ppe_projects SET created_at = NOW() WHERE created_at IS NULL;


ALTER TABLE projects ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
-- Mettre à jour les projets existants (exemple)
UPDATE projects SET created_at = NOW() WHERE created_at IS NULL;