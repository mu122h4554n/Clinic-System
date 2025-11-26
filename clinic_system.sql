-- Clinic Management System Database
-- Create database
CREATE DATABASE IF NOT EXISTS clinic_system;
USE clinic_system;

-- Users table (for all system users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'receptionist', 'patient') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Patients table (extended info for patients)
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    blood_type VARCHAR(5),
    allergies TEXT,
    medical_history TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Doctors table (extended info for doctors)
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(100),
    license_number VARCHAR(50),
    years_experience INT,
    consultation_fee DECIMAL(10,2),
    available_days VARCHAR(50), -- JSON format: ["monday", "tuesday", ...]
    available_hours VARCHAR(50), -- JSON format: {"start": "09:00", "end": "17:00"}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    reason TEXT,
    notes TEXT,
    created_by INT NOT NULL, -- user_id who created the appointment
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Medical records table
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    diagnosis TEXT,
    symptoms TEXT,
    treatment TEXT,
    prescriptions TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Preliminary checkups table (performed by doctors)
CREATE TABLE preliminary_checkups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    blood_pressure VARCHAR(20),
    temperature DECIMAL(4,2),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    pulse_rate INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Medicines catalog table
CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category ENUM('minor', 'major') DEFAULT 'minor',
    requires_approval BOOLEAN DEFAULT FALSE,
    stock_quantity INT DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 0,
    usage_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Medicine orders / pharmacy workflow
CREATE TABLE medicine_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'auto_approved', 'fulfilled') DEFAULT 'pending',
    patient_notes TEXT,
    review_notes TEXT,
    doctor_id INT,
    approved_by INT,
    requires_appointment BOOLEAN DEFAULT FALSE,
    approved_at DATETIME,
    fulfilled_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default users (password for all users is 'password')
INSERT INTO users (username, email, password, role, first_name, last_name, phone) VALUES 
('admin', 'admin@clinic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', '1234567890'),
('dr.smith', 'dr.smith@clinic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'John', 'Smith', '1234567891'),
('dr.jane', 'dr.jane@clinic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Jane', 'Doe', '1234567892'),
('receptionist', 'receptionist@clinic.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'receptionist', 'Mary', 'Johnson', '1234567893'),
('patient1', 'patient1@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Alice', 'Brown', '1234567894');

-- Insert doctor information
INSERT INTO doctors (user_id, specialization, license_number, years_experience, consultation_fee, available_days, available_hours) VALUES 
(2, 'General Medicine', 'MD12345', 10, 100.00, '["monday", "tuesday", "wednesday", "thursday", "friday"]', '{"start": "09:00", "end": "17:00"}'),
(3, 'General Practice', 'GP0003', 5, 75.00, '["monday", "tuesday", "wednesday", "thursday", "friday"]', '{"start": "08:00", "end": "16:00"}');

-- Insert patient information
INSERT INTO patients (user_id, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, blood_type) VALUES 
(5, '1990-05-15', 'female', 'Bob Brown', '1234567895', 'O+');

-- Seed medicines
INSERT INTO medicines (name, description, category, requires_approval, stock_quantity, price, usage_instructions) VALUES
('Paracetamol 500mg', 'Pain reliever and fever reducer suitable for mild symptoms.', 'minor', FALSE, 200, 4.50, 'Take 1 tablet every 6 hours as needed. Do not exceed 4 tablets per day.'),
('Cough Syrup', 'Non-drowsy cough suppressant for dry cough.', 'minor', FALSE, 120, 6.00, 'Take 10ml every 8 hours. Shake well before use.'),
('Antibiotic A20', 'Broad spectrum antibiotic requiring physician oversight.', 'major', TRUE, 40, 18.75, 'As directed by physician. Complete the full course even if symptoms improve.'),
('Hypertension Control Pack', 'Prescription medication pack for hypertension patients.', 'major', TRUE, 25, 32.00, 'Take dosage as instructed by your doctor. Monitor blood pressure daily.');

-- Activity Log table (History Tracking)
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('appointment', 'reminder', 'system', 'success', 'warning') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add some sample notifications for testing
INSERT INTO notifications (user_id, title, message, type) VALUES
(1, 'Welcome to Clinic System', 'Your admin account has been set up successfully.', 'system'),
(2, 'New Appointment', 'You have a new appointment scheduled for tomorrow.', 'appointment'),
(5, 'Appointment Reminder', 'Don\'t forget your appointment tomorrow at 10:00 AM.', 'reminder');
