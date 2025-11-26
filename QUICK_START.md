# Quick Start Guide - Doctor Registration System

## Step-by-Step Setup

### Step 1: Run the Database Migration

**Option A: Using Web Browser (Recommended)**
1. Open your web browser
2. Navigate to: `http://localhost/Clinic-System-2/database/run_doctor_approval_migration.php`
3. You should see a success message with green checkmarks
4. Verify the migration completed successfully

**Option B: Using MySQL Command Line**
1. Open MySQL command line or phpMyAdmin
2. Select your `clinic_system` database
3. Run the SQL file: `database/add_doctor_approval.sql`

### Step 2: Verify Installation

1. Log in as administrator (username: `admin`, password: `password`)
2. Check that "Doctor Approvals" appears in the navigation menu
3. Click on it to verify the page loads correctly

### Step 3: Test Doctor Registration

1. Log out from admin account
2. Go to the login page
3. Click "Register as Doctor" button
4. Fill out the registration form with test data:
   - Username: `dr.test`
   - Email: `dr.test@clinic.com`
   - Password: `password`
   - First Name: `Test`
   - Last Name: `Doctor`
   - Phone: `1234567890`
   - Specialization: `General Medicine`
   - License Number: `TEST12345`
   - Years of Experience: `5`
   - Consultation Fee: `100`
   - Select some available days
5. Submit the form
6. You should see a success message about pending approval

### Step 4: Test Approval Process

1. Try to log in as the test doctor (should be blocked with pending message)
2. Log in as admin again
3. You should see a notification about the new doctor registration
4. Click "Doctor Approvals" in the navigation
5. You should see the test doctor in the pending list
6. Click "View" to see full details
7. Click "Approve" and confirm
8. Log out from admin

### Step 5: Test Approved Doctor Login

1. Log in as the test doctor (`dr.test` / `password`)
2. You should now be able to log in successfully
3. You should see the doctor dashboard

## Quick Reference

### URLs
- **Doctor Registration**: `/doctor_signup.php`
- **Admin Approvals**: `/admin/doctor_approvals.php`
- **Migration Script**: `/database/run_doctor_approval_migration.php`

### Default Admin Credentials
- Username: `admin`
- Password: `password`

### Test Doctor Data
```
Username: dr.test
Email: dr.test@clinic.com
Password: password
Specialization: General Medicine
License: TEST12345
```

## Troubleshooting

### Migration Fails
- **Error**: "Table 'doctors' doesn't exist"
  - **Solution**: Run the main database setup first: `database/clinic_system.sql`

- **Error**: "Column 'approval_status' already exists"
  - **Solution**: Migration already completed, no action needed

### Cannot Access Admin Page
- **Error**: "Unauthorized" or redirect to login
  - **Solution**: Make sure you're logged in as admin role

### Doctor Cannot Register
- **Error**: "License number already exists"
  - **Solution**: Use a different license number

- **Error**: "Username or email already exists"
  - **Solution**: Use different credentials

### Pending Badge Not Showing
- **Solution**: Clear browser cache and refresh the page

## Next Steps

After successful setup:

1. **For Production Use**:
   - Change default admin password
   - Configure email notifications (optional)
   - Review and customize approval workflow
   - Add more specializations if needed

2. **For Testing**:
   - Register multiple test doctors
   - Test rejection workflow
   - Test approval with notes
   - Verify notifications work correctly

3. **For Customization**:
   - Modify specialization list in `doctor_signup.php`
   - Adjust consultation fee ranges
   - Customize approval notes/reasons
   - Add additional fields if needed

## Support

- **Documentation**: See `DOCTOR_REGISTRATION_GUIDE.md` for detailed information
- **Implementation Details**: See `IMPLEMENTATION_SUMMARY.md`
- **Database Schema**: See `database/add_doctor_approval.sql`

## Success Indicators

✅ Migration completed without errors
✅ "Doctor Approvals" menu appears for admin
✅ Doctor registration page loads correctly
✅ Pending doctors cannot log in
✅ Admin can approve/reject doctors
✅ Approved doctors can log in
✅ Notifications are sent correctly

## Common Questions

**Q: Can existing doctors still log in?**
A: Yes, the migration automatically marks existing doctors as "approved"

**Q: What happens to rejected doctors?**
A: They cannot log in and receive a message to contact administration

**Q: Can I re-approve a rejected doctor?**
A: Yes, you can manually update their status in the database or through the admin panel

**Q: How do I delete a doctor registration?**
A: Use the "Manage Users" page in the admin panel

**Q: Can I customize the approval workflow?**
A: Yes, modify `admin/doctor_approvals.php` to add custom logic

## Congratulations!

Your doctor registration and approval system is now set up and ready to use! 🎉

For any issues or questions, refer to the detailed documentation in `DOCTOR_REGISTRATION_GUIDE.md`.

