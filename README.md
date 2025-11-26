# Clinic Management System

A simple and minimalist clinic management system with role-based access control for 5 different user types.

## Features

### User Roles
1. **Admin** - Manages users, system settings, and reports
2. **Doctor** - Views appointments, manages medical records, diagnoses
3. **Doctor** - Manages patients, creates medical records, conducts preliminary checkups
4. **Receptionist** - Registers patients, manages appointments
5. **Patient** - Books appointments, views medical history

### Core Functionality
- User authentication and role-based dashboards
- Patient self-registration system
- Appointment booking and management
- Medical records management
- Preliminary checkup recording
- Pharmacy & medicine workflows with doctor approvals
- Patient registration and management
- System reports and analytics
- **NEW:** Real-time notification system
- **NEW:** Complete activity tracking and history

## Installation

### Prerequisites
- XAMPP (or similar LAMP/WAMP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher

### Setup Instructions

1. **Clone/Download the project**
   ```
   Place the project files in your XAMPP htdocs directory
   (e.g., C:\xampp\htdocs\Clinic-System-2\)
   ```

2. **Start XAMPP Services**
   - Start Apache
   - Start MySQL

3. **Create Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database file: `database/clinic_system.sql`
   - This will create the database and insert sample users

4. **Configure Database Connection**
   - Open `config/database.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'clinic_system');
     define('DB_USER', 'root');
     define('DB_PASS', ''); // Your MySQL password
     ```

5. **Access the System**
   - Open your browser and go to: `http://localhost/Clinic-System-2/`
   - You'll be redirected to the login page

## Default Login Credentials

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | password |
| Doctor | dr.smith | password |
| Doctor | dr.jane | password |
| Receptionist | receptionist | password |
| Patient | patient1 | password |

## File Structure

```
Clinic-System-2/
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── functions.php         # Common functions
│   ├── header.php           # Common header
│   ├── footer.php           # Common footer
│   └── appointments-table.php # Appointments table component
├── admin/
│   ├── users.php            # User management
│   └── reports.php          # System reports
├── database/
│   └── clinic_system.sql    # Database schema and sample data
├── index.php                # Entry point (redirects to login)
├── login.php                # Login page
├── signup.php               # Patient registration page
├── logout.php               # Logout handler
├── dashboard.php            # Role-based dashboard
├── appointments.php         # Appointment management
├── book-appointment.php     # Patient appointment booking
├── my-appointments.php      # Patient's appointments
├── medicines.php            # Patient medicine center
├── patients.php             # Patient management
├── medical-records.php      # Medical records (Doctor)
├── my-records.php           # Patient's medical history
├── checkups.php             # Preliminary checkups (Doctor)
├── medicine-requests.php    # Doctor medicine approvals
├── profile.php              # User profile management
├── notifications.php        # Notification management
├── activity-log.php         # Activity history tracking
├── unauthorized.php         # Access denied page
└── README.md               # This file
```

## Database Schema

### Main Tables
- `users` - All system users with role-based access
- `patients` - Extended patient information
- `doctors` - Doctor-specific information
- `appointments` - Appointment scheduling
- `medical_records` - Doctor's medical records
- `preliminary_checkups` - Doctor's preliminary checkups
- `medicines` - Medicine catalog, inventory, and severity rules
- `medicine_orders` - Patient orders plus doctor approval workflow
- **NEW:** `activity_log` - Complete user activity tracking
- **NEW:** `notifications` - Real-time notification system

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention using prepared statements
- Role-based access control
- Session management
- Input sanitization

## Usage Guide

### For Patients
1. Register for a new account using the signup page
2. Login with patient credentials
3. Book appointments with available doctors
4. View appointment history
5. Access medical records and checkup history
6. Purchase minor-condition medicines instantly
7. Submit doctor approval requests for major-condition medicines and track order history
8. Update personal profile information

### For Doctors
1. View assigned appointments
2. Update appointment status
3. Create and manage medical records
4. View patient information during consultations
5. Review and approve medicine requests, add notes, and mark orders fulfilled

### For Doctors (Additional Features)
1. View daily appointments
2. Record preliminary checkups (vital signs)
3. Prepare patients for doctor consultations
4. Access patient basic information
5. Manage prescription approvals and ensure inventory availability

### For Receptionists
1. Register new patients
2. Schedule appointments for patients
3. Manage appointment bookings
4. Update patient contact information

### For Admins
1. Manage all system users
2. View system reports and statistics
3. Monitor system activity
4. Manage user accounts and permissions

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check if MySQL service is running
   - Verify database credentials in `config/database.php`
   - Ensure database exists and is properly imported

2. **Login Issues**
   - Use the default credentials provided above
   - Check if the users table has data
   - Clear browser cache and cookies

3. **Permission Errors**
   - Ensure proper file permissions
   - Check if all required files are present

4. **Styling Issues**
   - Check internet connection (uses CDN for Bootstrap and FontAwesome)
   - Clear browser cache

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify all installation steps were followed correctly
3. Check browser console for JavaScript errors
4. Ensure all required PHP extensions are installed

## License

This project is created for educational purposes. Feel free to modify and use as needed.
