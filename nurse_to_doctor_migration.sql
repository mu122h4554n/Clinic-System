-- COMPREHENSIVE MIGRATION SCRIPT: MERGE NURSE FUNCTIONALITY INTO DOCTOR ROLE
-- This script will:
-- 1. Create doctor records for existing nurses
-- 2. Migrate preliminary_checkups data
-- 3. Update database schema
-- 4. Remove nurse role entirely

USE clinic_system;

-- Step 1: Create doctor records for existing nurse users
-- This ensures all nurses become doctors with appropriate default settings
INSERT INTO doctors (user_id, specialization, license_number, years_experience, consultation_fee, available_days, available_hours)
SELECT 
    u.id,
    'General Practice' as specialization,
    CONCAT('GP', LPAD(u.id, 4, '0')) as license_number,
    2 as years_experience,
    75.00 as consultation_fee,
    '["monday", "tuesday", "wednesday", "thursday", "friday"]' as available_days,
    '{"start": "08:00", "end": "16:00"}' as available_hours
FROM users u 
WHERE u.role = 'nurse' 
AND u.id NOT IN (SELECT user_id FROM doctors);

-- Step 2: Add temporary column to map nurse_id to doctor_id
ALTER TABLE preliminary_checkups ADD COLUMN temp_doctor_id INT;

-- Step 3: Update the temporary column with correct doctor IDs
UPDATE preliminary_checkups pc 
JOIN users u ON pc.nurse_id = u.id 
JOIN doctors d ON u.id = d.user_id 
SET pc.temp_doctor_id = d.id;

-- Step 4: Verify all records have been mapped (this should return 0 if successful)
SELECT COUNT(*) as unmapped_records FROM preliminary_checkups WHERE temp_doctor_id IS NULL;

-- Step 5: Drop the old foreign key constraint
ALTER TABLE preliminary_checkups DROP FOREIGN KEY preliminary_checkups_ibfk_2;

-- Step 6: Drop the old nurse_id column
ALTER TABLE preliminary_checkups DROP COLUMN nurse_id;

-- Step 7: Rename temp_doctor_id to doctor_id
ALTER TABLE preliminary_checkups CHANGE COLUMN temp_doctor_id doctor_id INT NOT NULL;

-- Step 8: Add new foreign key constraint referencing doctors table
ALTER TABLE preliminary_checkups ADD CONSTRAINT preliminary_checkups_ibfk_2 
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE;

-- Step 9: Update nurse users to doctor role
UPDATE users SET role = 'doctor' WHERE role = 'nurse';

-- Step 10: Remove 'nurse' from the role enum
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'doctor', 'receptionist', 'patient') NOT NULL;

-- Step 11: Update the table comment
ALTER TABLE preliminary_checkups COMMENT = 'Preliminary checkups performed by doctors (formerly nurses)';

-- Verification queries (run these to check migration success):
-- SELECT COUNT(*) as total_doctors FROM users WHERE role = 'doctor';
-- SELECT COUNT(*) as total_nurses FROM users WHERE role = 'nurse';  -- Should be 0
-- SELECT COUNT(*) as checkups_with_doctor_id FROM preliminary_checkups WHERE doctor_id IS NOT NULL;
-- SELECT COUNT(*) as doctors_with_records FROM doctors;

-- Success message
SELECT 'Migration completed successfully! All nurse functionality has been merged into doctor role.' as status;
