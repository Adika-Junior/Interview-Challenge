# Task Management System

A simple, object-oriented PHP and vanilla JavaScript application for managing users and tasks. Built for interview demonstration purposes.

## Features
- **Admin:**
  - Add, edit, delete users
  - Assign, edit, delete tasks
  - Set task deadlines
  - View dashboard statistics
- **User:**
  - View assigned tasks
  - Update task status (Pending, In Progress, Completed)
  - View personal dashboard statistics
- **Authentication:** Secure login/logout
- **Email Notification:** (Logged for dev) when a task is assigned
- **OOP PHP Backend**
- **Vanilla JS Frontend**

## Folder Structure
```
task-management-system/
├── api/                # PHP backend (OOP classes, endpoints)
│   ├── admin/          # Admin endpoints (users.php, tasks.php, dashboard.php)
│   ├── user/           # User endpoints (tasks.php, dashboard.php)
│   ├── auth/           # Authentication (login.php, logout.php)
│   ├── classes/        # OOP PHP classes (User.php, Task.php, EmailService.php, Database.php)
│   └── config/         # Config files
├── database/
│   └── schema.sql      # MySQL schema and sample data
├── public/
│   ├── index.html      # Main frontend
│   ├── js/app.js       # Frontend logic (vanilla JS)
│   └── css/style.css   # Styles
└── README.md           # This file
```

## Requirements
- PHP 7.4+
- MySQL (or MariaDB)
- Web server (Apache, Nginx, or PHP built-in server)

## Setup Instructions

### 1. Clone the Repository
```
git clone <repo-url>
cd task-management-system
```

### 2. Database Setup
- Create a MySQL database (default: `${DB_NAME}`).
- If you do **not** see `${DB_NAME}` when running `SHOW DATABASES;` in MySQL, create it manually:
  1. Log in to MySQL:
     ```bash
     mysql -u ${DB_USERNAME} -p
     ```
  2. At the MySQL prompt, run:
     ```sql
     CREATE DATABASE ${DB_NAME};
     EXIT;
     ```
- Import the schema and sample data:
  ```bash
  mysql -u ${DB_USERNAME} -p ${DB_NAME} < database/schema.sql
  ```
- To verify, log in to MySQL and run:
  ```sql
  SHOW DATABASES;
  USE ${DB_NAME};
  SHOW TABLES;
  SELECT * FROM users;
  ```

### 3. Configure Environment Variables
- Set your database credentials as environment variables in `.env`:
  - `DB_HOST` (default: `localhost`)
  - `DB_USERNAME` (default: `root`)
  - `DB_PASSWORD` (default: empty)
  - `DB_NAME` (default: `task_management`)
- You can set these in your web server config, `.env` file (with a loader), or directly in your environment.

### 4. Run the App Locally
- **Option 1: PHP Built-in Server (Recommended for Development)**
  - From the `task-management-system/public` directory:
    1. Make sure `router.php` exists in the `public/` directory (it is included in this repo).
    2. Start the server with:
       ```
       php -S ${APP_URL_HOST} router.php
       ```
    3. Visit `${APP_URL}` in your browser.

  - This router script allows API requests (e.g., `/api/auth/login.php`) to work even though the API files are outside the `public/` directory.

- **Option 2: Apache/Nginx**
  - Point your web root to the `public/` directory.
  - Make sure PHP is enabled and can access the `api/` directory.

### 5. Login Credentials (for testing only)
- **Admin:**
  - Username: `${ADMIN_USERNAME}` or `${ADMIN_EMAIL}`
  - Password: `${ADMIN_PASSWORD}`
- **User:**
  - Username: `${USER_USERNAME}` or `${USER_EMAIL}`
  - Password: `${USER_PASSWORD}`

## Automated Secure Admin User Setup

This project uses a secure, .env-driven approach for all credentials, including the admin user. No credentials are hardcoded in the codebase or SQL files.

### How it Works
- The `.env` file contains all sensitive configuration, including admin credentials and password hash.
- The SQL schema does **not** insert an admin user directly. Instead, after running the schema, a script reads the `.env` file and inserts or updates the admin user securely.
- The `setup_database.sh` script automates the entire process: it creates the schema and inserts/updates the admin user from `.env`.
- The setup script now automatically replaces placeholders (like `${DB_NAME}`) in `schema.sql` with values from your `.env` file before running the SQL, so you never need to edit the SQL file directly.

### Example .env File
```env
# Database Configuration
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=Adika123
DB_NAME=task_management

# Email Configuration (Gmail SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=pleasantview076@gmail.com
SMTP_PASSWORD="fxud nkim kvnk raqa"
SMTP_ENCRYPTION=tls

# Application Configuration
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Security Configuration
SESSION_SECRET=0c2a317c1ffa082ad292f7f5f9736a265e26d7203d3f0b4dbc98c6a346d67d16
JWT_SECRET=fe91e46f769cd291653f48b7e95aa58150f2a4c0094801cdc4f954ca670d3d47

# Email Templates
FROM_EMAIL=pleasantview076@gmail.com
FROM_NAME="Task Management System"

# Admin User Configuration
ADMIN_USERNAME=JayAdmin
ADMIN_EMAIL=pleasantview076@gmail.com
ADMIN_PASSWORD=1qazxsw2
ADMIN_PASSWORD_HASH=$2y$10$edBlgvLttKO27FIi8umQBuzFWGffe3uZXOtLxaBoLMhvtXhKRLZjC
```

### How to Set Up or Reset the Database and Admin User

1. **Edit your `.env` file** with your desired admin credentials and password hash (generate with `php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT) . PHP_EOL;"`).
2. **To reset the database (start fresh):**
   ```bash
   mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS $DB_NAME;"
   ./setup_database.sh
   ```
   This will:
   - Drop the old database (if it exists)
   - Create the database and tables
   - Insert or update the admin user from your `.env` file

3. **To set up without resetting (if database is new):**
   ```bash
   ./setup_database.sh
   ```

4. **Start the PHP server:**
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
5. **Login with your admin credentials from `.env`**

### Troubleshooting
- **Table 'users' already exists:**
  - This means the database was not dropped before running the setup. To fix, drop the database as shown above, then rerun the setup script.
- **Admin user not inserted:**
  - Make sure your `.env` file contains `ADMIN_PASSWORD_HASH` (not just `ADMIN_PASSWORD`).
  - You can manually run the admin user insertion script:
    ```bash
    php database/insert_admin_user.php
    ```
- **Placeholders not replaced in schema.sql:**
  - The setup script now automatically replaces placeholders like `${DB_NAME}` with your actual database name from `.env`.

### Security
- No credentials are ever hardcoded in code or SQL.
- All sensitive values are loaded from `.env`.
- The admin user is always created/updated securely from `.env`.

## Usage Notes
- Only admins can manage users and assign tasks.
- Users can only see and update their own tasks.
- Email notifications are logged (not sent) for development.
- All API endpoints are under `/api/` and use JSON.

## Customization
- To enable real email sending, update `.env` and `EmailService.php` to use your SMTP credentials.
- To add more features, extend the OOP classes in `api/classes/`.

## License
MIT or as specified by your organization.

## Troubleshooting: Login Always Fails or "Invalid Credentials"

If you always get "invalid credentials" even with the correct password, your PHP may be missing bcrypt support (required for password hashing).

### 1. Install PHP and Required Extensions
On Ubuntu/WSL:
```bash
sudo apt-get update
sudo apt-get install php php-cli php-common php-mysql php-mbstring php-xml
```
For PHP 8.1 (your version):
```bash
sudo apt-get install php8.1 php8.1-cli php8.1-common php8.1-mysql php8.1-mbstring php8.1-xml
```

### 2. Verify Bcrypt Support
Run:
```bash
php -r "var_dump(CRYPT_BLOWFISH);"
```
You should see:
```
bool(true)
```
If you see `bool(false)`, bcrypt is not enabled.

### 3. Test Password Hashing
A script `test_password.php` is included. Run:
```bash
php test_password.php
```
You should see:
```
Password is valid!
```
If you see `Password is INVALID!`, bcrypt is not working.

### 4. Restart Your PHP Server
After installing extensions, restart your PHP server:
```bash
php -S ${APP_URL_HOST} router.php
```

### 5. Try Logging In Again
If you still have issues, double-check your PHP version with `php -v` and ensure you are not mixing Windows and Linux PHP on WSL.

### 6. If Login Still Fails: Update the Password Hash
If you still cannot log in as admin, generate a new bcrypt hash for your password:
```bash
php -r "echo password_hash('${ADMIN_PASSWORD}', PASSWORD_DEFAULT) . PHP_EOL;"
```
Test the hash in `test_password.php` as described above. If it works, update your database:
```sql
UPDATE users SET password = 'PASTE_YOUR_NEW_HASH_HERE' WHERE username = '${ADMIN_USERNAME}';
```
The schema and default admin password hash have been updated to reflect this process.

## Real Email Sending Setup (Gmail SMTP with PHPMailer)

This project supports real email notifications using Gmail SMTP and PHPMailer.

### Requirements
- PHP 8.1+
- Composer (for dependency management)
- PHPMailer (installed via Composer)

### Step-by-Step Setup
1. **Install Composer dependencies:**
   ```bash
   composer require phpmailer/phpmailer
   ```
2. **Set up a Gmail App Password:**
   - Go to your Google Account > Security > App passwords.
   - Generate an app password for "Mail" and "Other (Custom name)".
   - Copy the generated password (e.g., `${SMTP_PASSWORD}`).
3. **Configure `.env` and `EmailService.php`:**
   - The file is already set up for Gmail SMTP:
     - Email: `${SMTP_USERNAME}`
     - App password: `${SMTP_PASSWORD}`
   - If you want to use a different email, update the username and password in `.env`.
4. **Test email sending:**
   - Assign a task to a user and check the recipient's inbox.
   - If you get errors, check your Gmail security settings and make sure "Allow less secure apps" is enabled (if needed).

### Example Admin User for Testing
- **Username:** ${ADMIN_USERNAME}
- **Email:** ${ADMIN_EMAIL}
- **Password:** ${ADMIN_PASSWORD}
- **Role:** admin

### PHP Version Used
- PHP 8.1.2 (recommended: PHP 8.1+)

### Project Setup Requirements
- PHP 8.1+
- MySQL
- Composer
- PHPMailer (installed via Composer)

### Troubleshooting: Gmail SMTP Authentication Errors
If you see an error like `SMTP Error: Could not authenticate` or `Username and Password not accepted`:

1. **Enable 2-Step Verification** on your Google account ([Google 2-Step Verification](https://myaccount.google.com/security)).
2. **Delete any old app passwords** in your Google Account.
3. **Generate a new app password** for "Mail" and "Other (Custom name)" at [Google App Passwords](https://myaccount.google.com/apppasswords).
4. **Copy the new app password** (it will be 16 characters, no spaces). Paste it exactly as given—do not add spaces or line breaks.
5. **Update your code:**
   - In `.env`, set:
     ```env
     SMTP_USERNAME=your_gmail_address@gmail.com
     SMTP_PASSWORD=your_app_password
     ```
   - If using `test_mail.php`, update the same way.
6. **Try sending a test email again.**
7. **Check your Gmail inbox and spam folder** for security alerts and approve the sign-in if prompted.

**Note:** The app password must be pasted as a single 16-character string, with no spaces before, after, or in the middle.

## Automated Task Deadline Reminders

To automatically send email reminders for tasks due within 24 hours, set up a cron job to run the reminder script every hour:

1. Open your crontab for editing:
   ```bash
   crontab -e
   ```
2. Add the following line (adjust the PHP path and project path as needed):
   ```bash
   0 * * * * /usr/bin/php /path/to/task-management-system/api/send_deadline_reminders.php
   ```
   - This runs the script at the start of every hour.
   - Make sure your PHP CLI and mail settings are configured correctly.

3. Save and exit. The system will now send deadline reminders automatically.

### How the Deadline Reminder System Works (Locally & In Deployment)

- The script `api/send_deadline_reminders.php` checks for tasks due within 24 hours, sends reminder emails, and marks them as reminded.
- It is triggered automatically by a scheduled system task (cron job on Linux, Task Scheduler on Windows, or hosting control panel cron).

### Local Development
- **Manual Run:**
  ```bash
  php task-management-system/api/send_deadline_reminders.php
  ```
- **Automated:**
  Set up a cron job:
  ```bash
  crontab -e
  ```
  Add:
  ```bash
  0 * * * * /usr/bin/php /absolute/path/to/task-management-system/api/send_deadline_reminders.php
  ```

### Deployment (Production Server)
- Set up a cron job on your server (same as above, adjust the path as needed).

## Session Management & Security Best Practices (2025)

### How Sessions Work
- This app uses PHP's default file-based session storage (session files in `/var/lib/php/sessions`).
- User authentication state is tracked via secure PHP sessions.

### Ending All Sessions (Force Logout for All Users)
To immediately log out all users (e.g., after a security incident or policy change):

1. **Delete all session files** (requires sudo/root):
   ```bash
   sudo rm -f /var/lib/php/sessions/*
   ```
   This will destroy all active sessions and force all users to log in again.

2. **Why?**
   - Useful for emergency logouts, maintenance, or after changing security policies.
   - Only affects sessions for this application/server.

### Session Security Best Practices
- **Use Secure, Random Session IDs:** PHP's default is secure, but never expose session IDs in URLs.
- **Set Cookie Flags:**
  - `HttpOnly` (prevents JS access)
  - `Secure` (HTTPS only)
  - `SameSite=Strict` (prevents CSRF)
- **Regenerate Session IDs:** On login, privilege change, and periodically during a session.
- **Short Expiry & Sliding Timeout:**
  - Idle timeout: 15–30 min for low-risk, 2–5 min for high-risk apps.
  - Absolute timeout: 4–8 hours for office apps, less for sensitive apps.
- **Destroy Session on Logout:** Always call `session_destroy()` and unset session cookies.
- **Minimal Data in Session:** Store only user ID, role, and essential state. Never store passwords or sensitive info.
- **Monitor & Audit:** Log session creation, login, logout, and suspicious activity (e.g., multiple failed logins, rapid session creation).
- **Warn Users Before Expiry:** For better UX, show a warning before session expiry and allow extension.
- **Always Use HTTPS:** Enforce HTTPS and HSTS headers in production.
- **Multi-Factor Authentication:** Strongly recommended for admin and sensitive user actions.

#### Example: Secure Session Cookie in PHP
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'yourdomain.com',
    'secure' => true,      // Only over HTTPS
    'httponly' => true,    // Not accessible via JS
    'samesite' => 'Strict' // Prevent CSRF
]);
session_start();
```

#### Troubleshooting
- If users are not being logged out as expected, check session file permissions and PHP's session save path.
- For distributed/multi-server setups, use a shared session store (e.g., Redis, database) and clear sessions there.

## Troubleshooting: MySQL Root Password Issues

If you see this error when trying to access MySQL:

```
ERROR 1045 (28000): Access denied for user 'root'@'localhost' (using password: YES)
```

You may have forgotten your MySQL root password. To reset it:

1. **Stop MySQL:**
   ```bash
   sudo service mysql stop
   ```
2. **Start MySQL in safe mode:**
   ```bash
   sudo mysqld_safe --skip-grant-tables &
   ```
3. **In a new terminal, connect without a password:**
   ```bash
   mysql -u root
   ```
4. **At the MySQL prompt, run:**
   ```sql
   FLUSH PRIVILEGES;
   ALTER USER 'root'@'localhost' IDENTIFIED BY 'yournewpassword';
   EXIT;
   ```
5. **Stop safe mode and restart MySQL normally:**
   ```bash
   sudo service mysql stop
   sudo service mysql start
   ```
6. **Now log in with your new password:**
   ```bash
   mysql -u root -p
   ```

If you see a directory error like:
```
mysqld_safe Directory '/var/run/mysqld' for UNIX socket file don't exists.
```
Create the directory and set permissions:
```bash
sudo mkdir -p /var/run/mysqld
sudo chown mysql:mysql /var/run/mysqld
```
Then try again.

## Troubleshooting: MySQL Fails to Start (Unable to lock ./ibdata1 error: 11)

If you see errors like this in your MySQL logs:

```
[InnoDB] Unable to lock ./ibdata1 error: 11
```

Or if MySQL fails to start and you see multiple mysqld processes running:

### Solution
1. **List all running MySQL processes:**
   ```bash
   ps aux | grep mysqld
   ```
2. **Kill all MySQL processes (replace PIDs with those you see):**
   ```bash
   sudo kill -9 PID1 PID2 PID3 ...
   ```
   Example:
   ```bash
   sudo kill -9 145066 145068 145069 145229 149442
   ```
3. **Verify all MySQL processes are stopped:**
   ```bash
   ps aux | grep mysqld
   ```
   Only the grep line should appear.
4. **Start MySQL normally:**
   ```bash
   sudo service mysql start
   ```
5. **Check status:**
   ```bash
   sudo systemctl status mysql.service
   ```

You should now be able to log in and use MySQL as normal.

---

## Deployment on Vercel

### 1. Environment Variables
- Set all required variables (DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, etc.) in the Vercel dashboard under Project Settings > Environment Variables. Copy from your local `.env` file.

### 2. Build Step
- Vercel will automatically detect PHP, but you must install Composer dependencies. Add this as the build command in Vercel:
  ```sh
  composer install --no-dev --optimize-autoloader --no-interaction
  ```

### 3. Start/Run Command
- **Vercel:** No explicit start command needed; Vercel serves PHP and static files automatically.
- **Local Development:**
  ```sh
  php -S 127.0.0.1:8000 router.php
  ```
  (Run this from the `public/` directory.)

### 4. Database
- You must provide an external MySQL database (e.g., PlanetScale, Neon, AWS RDS, etc.). Vercel does not host databases.

### 5. Notes
- All API endpoints are available under `/api/`.
- The frontend is served from `/public/index.html`.
- If you need to ignore files from deployment, add a `.vercelignore` file (see Vercel docs).
