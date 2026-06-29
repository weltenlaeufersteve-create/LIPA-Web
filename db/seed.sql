-- Fallback seed. Prefer: php bin/create-admin.php "Admin" admin@pepea-africa.org <password>
-- This inserts an admin whose password is 'changeme'. CHANGE IT after first login.
INSERT INTO users (name, email, password_hash, role, active)
VALUES ('Administrator', 'admin@pepea-africa.org',
        '$2y$10$pgr/7VEB1cOPlU5Pzf3UMOwPvRVA3k7rTeKIlJAp7l68rYfur.UNy', 'admin', 1)
ON DUPLICATE KEY UPDATE email = email;

-- Starter categories (income then expense). Safe to run once on an empty categories table.
INSERT INTO categories (type, name, sort_order, active) VALUES
('income','Grants (Restricted)',1,1),
('income','Grants (Unrestricted)',2,1),
('income','Individual Donations',3,1),
('income','Corporate Donations',4,1),
('income','Membership & Contributions',5,1),
('income','Bank/Interest Income',6,1),
('income','Other Income',7,1),
('expense','Salaries & Wages',1,1),
('expense','Staff Benefits',2,1),
('expense','Office Rent',3,1),
('expense','Utilities',4,1),
('expense','Travel & Transport',5,1),
('expense','Programme/Project Costs',6,1),
('expense','Training & Workshops',7,1),
('expense','Office Supplies',8,1),
('expense','Equipment',9,1),
('expense','Professional Fees (Audit/Legal)',10,1),
('expense','Bank Charges',11,1),
('expense','Communication',12,1),
('expense','Repairs & Maintenance',13,1),
('expense','Fundraising Costs',14,1),
('expense','Miscellaneous',15,1);
