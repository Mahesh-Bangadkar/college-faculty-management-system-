# College Faculty Management System

A professional, minimal college faculty management portal built with HTML5, CSS3, JavaScript, Bootstrap 5, core PHP, and MySQL.

## Features

- Public homepage with only Admin and Faculty login entry points
- Secure role-based authentication with PHP sessions
- Admin dashboard for faculty and department management
- Faculty dashboard for personal profile updates and document uploads
- Search, filter, export PDF, and export CSV for faculty records
- Responsive Bootstrap 5 interface with a government/university portal style
- MySQL-backed storage with sample data

## Folder Structure

- `assets/css` - Custom styles
- `assets/js` - Client-side scripts
- `assets/images` - Logo and image assets
- `uploads` - Uploaded photos and documents
- `admin` - Admin dashboard
- `faculty` - Faculty dashboard
- `includes` - Shared helpers
- `database` - SQL schema and seed file

## Default Credentials

Admin:

- Username: `admin`
- Password: `Admin@123`

Faculty:

- Employee ID: `FAC001`
- Password: `Faculty@123`

You can also log in using the seeded faculty email address, such as `asha.menon@college.edu`.

## Setup

1. Copy the project folder into your PHP server root, such as `htdocs` or `www`.
2. Create a MySQL database named `college_faculty_management`.
3. Import `database/college_faculty_management.sql` into MySQL.
4. Update database credentials in `config.php` if needed.
5. Make sure the `uploads` directory is writable by the web server.
6. Open `index.php` in your browser.

## Notes

- Passwords are stored as salted SHA-256 hashes using the shared secret defined in `config.php`.
- The project includes a lightweight PDF export generator for faculty reports.
- Faculty can manage only their own profile and uploaded files.
