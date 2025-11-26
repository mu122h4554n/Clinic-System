-- Migration script to add doctor approval system
-- This adds approval status tracking for doctor registrations

USE clinic_system;

-- Add approval_status column to doctors table
ALTER TABLE doctors 
ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER user_id,
ADD COLUMN approval_notes TEXT AFTER approval_status,
ADD COLUMN approved_by INT AFTER approval_notes,
ADD COLUMN approved_at DATETIME AFTER approved_by,
ADD CONSTRAINT fk_doctors_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update existing doctors to be approved (they were added by admin)
UPDATE doctors SET approval_status = 'approved', approved_at = NOW() WHERE approval_status = 'pending';

-- Add index for faster queries on approval status
CREATE INDEX idx_approval_status ON doctors(approval_status);

-- Verification query
SELECT 'Migration completed successfully!' as status;
SELECT COUNT(*) as pending_doctors FROM doctors WHERE approval_status = 'pending';
SELECT COUNT(*) as approved_doctors FROM doctors WHERE approval_status = 'approved';

