-- Fallback seed. Prefer: php bin/create-admin.php "Admin" admin@pepea-africa.org <password>
-- This inserts an admin whose password is 'changeme'. CHANGE IT after first login.
INSERT INTO users (name, email, password_hash, role, active)
VALUES ('Administrator', 'admin@pepea-africa.org',
        '$2y$10$pgr/7VEB1cOPlU5Pzf3UMOwPvRVA3k7rTeKIlJAp7l68rYfur.UNy', 'admin', 1)
ON DUPLICATE KEY UPDATE email = email;
