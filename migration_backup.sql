-- BACKUP OF ORIGINAL DATABASE STRUCTURE BEFORE NURSE-TO-DOCTOR MIGRATION
-- Created: <?php echo date('Y-m-d H:i:s'); ?>

-- Original users table role enum
-- role ENUM('admin', 'doctor', 'nurse', 'receptionist', 'patient') NOT NULL,

-- Original preliminary_checkups table structure
-- CREATE TABLE preliminary_checkups (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     patient_id INT NOT NULL,
--     nurse_id INT NOT NULL,  -- This will be changed to doctor_id
--     appointment_id INT,
--     blood_pressure VARCHAR(20),
--     temperature DECIMAL(4,2),
--     weight DECIMAL(5,2),
--     height DECIMAL(5,2),
--     pulse_rate INT,
--     notes TEXT,
--     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
--     FOREIGN KEY (nurse_id) REFERENCES users(id) ON DELETE CASCADE,  -- This will be changed
--     FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
-- );

-- Original sample nurse user
-- ('nurse.jane', 'nurse.jane@clinic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nurse', 'Jane', 'Doe', '1234567892'),

-- To restore original structure if needed:
-- 1. Restore role enum: ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'doctor', 'nurse', 'receptionist', 'patient') NOT NULL;
-- 2. Restore nurse_id column: ALTER TABLE preliminary_checkups CHANGE COLUMN doctor_id nurse_id INT NOT NULL;
-- 3. Restore foreign key: ALTER TABLE preliminary_checkups ADD CONSTRAINT preliminary_checkups_ibfk_2 FOREIGN KEY (nurse_id) REFERENCES users(id) ON DELETE CASCADE;
