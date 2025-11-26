# Doctor Registration & Approval System

## Overview
This system allows doctors to register for an account and requires administrator approval before they can access the system. This ensures that only verified medical professionals can practice through the clinic management system.

## Features

### 1. Doctor Self-Registration
- Doctors can register themselves through a dedicated registration page
- Required information includes:
  - Account credentials (username, email, password)
  - Personal information (name, phone, address)
  - Professional details (specialization, license number, years of experience)
  - Consultation fee
  - Available days for appointments
  - Qualifications and education

### 2. Approval Workflow
- New doctor registrations are set to "pending" status
- Account is inactive until approved by administrator
- Administrators receive notifications for new registrations
- Doctors receive notifications when approved/rejected

### 3. Administrator Management
- Dedicated admin page to view all pending registrations
- View detailed information about each applicant
- Approve or reject registrations with notes
- Track approval history

## Installation

### Step 1: Run Database Migration
Navigate to: `http://your-domain/database/run_doctor_approval_migration.php`

This will:
- Add `approval_status` column to doctors table
- Add `approval_notes`, `approved_by`, and `approved_at` columns
- Create necessary indexes and foreign keys
- Set existing doctors to "approved" status

### Step 2: Verify Installation
After running the migration, verify:
- Doctor registration page is accessible at `/doctor_signup.php`
- Admin approval page is accessible at `/admin/doctor_approvals.php`
- Navigation menu shows "Doctor Approvals" link for admins

## Usage

### For Doctors

#### Registration Process
1. Go to the login page or patient signup page
2. Click "Register as Doctor" button
3. Fill out the registration form with all required information
4. Submit the registration
5. Wait for administrator approval (you'll receive a notification)
6. Once approved, log in with your credentials

#### Login Behavior
- **Pending approval**: Cannot log in, receives message about pending approval
- **Rejected**: Cannot log in, receives message to contact administrator
- **Approved**: Can log in normally and access doctor features

### For Administrators

#### Viewing Pending Registrations
1. Log in as administrator
2. Click "Doctor Approvals" in the navigation menu
3. Badge shows number of pending approvals
4. View list of all pending doctor registrations

#### Approving a Doctor
1. Click "View" to see detailed information
2. Click "Approve" button
3. Optionally add approval notes
4. Confirm approval
5. Doctor's account is activated and they receive a notification

#### Rejecting a Doctor
1. Click "Reject" button
2. Enter a reason for rejection (required)
3. Confirm rejection
4. Doctor receives notification with rejection reason

## Database Schema Changes

### New Columns in `doctors` Table
```sql
approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
approval_notes TEXT
approved_by INT (Foreign key to users.id)
approved_at DATETIME
```

### Indexes
- `idx_approval_status` on `approval_status` column for faster queries

## Files Added/Modified

### New Files
- `doctor_signup.php` - Doctor registration page
- `admin/doctor_approvals.php` - Admin approval management page
- `database/add_doctor_approval.sql` - SQL migration script
- `database/run_doctor_approval_migration.php` - PHP migration runner
- `DOCTOR_REGISTRATION_GUIDE.md` - This documentation

### Modified Files
- `login.php` - Added check for doctor approval status
- `signup.php` - Added link to doctor registration
- `includes/header.php` - Added "Doctor Approvals" menu item for admins

## Notifications

### Doctors Receive Notifications When:
- Registration is submitted (confirmation)
- Account is approved by administrator
- Account is rejected by administrator

### Administrators Receive Notifications When:
- New doctor registration is submitted

## Security Features

- Password hashing for all accounts
- Account remains inactive until approved
- License number uniqueness validation
- Email and username uniqueness validation
- Role-based access control
- Activity logging for all approval actions

## Workflow Diagram

```
Doctor Registration
        ↓
  Pending Status
  (Account Inactive)
        ↓
Admin Reviews Application
        ↓
    ┌───────┴───────┐
    ↓               ↓
Approve         Reject
    ↓               ↓
Account         Notification
Activated       Sent to Doctor
    ↓
Doctor Can
Log In
```

## Testing

### Test the Registration Flow
1. Register a new doctor account
2. Try to log in (should be blocked with pending message)
3. Log in as admin
4. Check "Doctor Approvals" page
5. Approve the doctor
6. Log in as the doctor (should work now)

### Test Rejection Flow
1. Register another doctor account
2. Log in as admin
3. Reject the registration with a reason
4. Try to log in as the rejected doctor (should show rejection message)

## Troubleshooting

### Migration Issues
- If migration fails, check database connection in `config/database.php`
- Ensure MySQL user has ALTER TABLE privileges
- Check error logs for specific SQL errors

### Login Issues
- Verify doctor's approval_status in database
- Check that user's is_active is set to 1 for approved doctors
- Clear browser cache and cookies

### Notification Issues
- Verify notifications table exists
- Check that createNotification function is working
- Ensure admin users have is_active = 1

## Future Enhancements

Potential improvements:
- Email notifications for approvals/rejections
- Document upload for license verification
- Bulk approval/rejection
- Approval expiration dates
- Re-application process for rejected doctors
- Approval workflow with multiple levels

## Support

For issues or questions:
1. Check this documentation
2. Review error logs
3. Contact system administrator

