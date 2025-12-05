# Database Setup Guide

This guide explains how to set up the database for this Laravel application. The application supports both **SQLite** (simplest, no server required) and **MySQL/MariaDB** (for production or shared hosting).

## Overview

This application requires database migrations to be run to create the necessary tables. There are three main approaches to handle this:

1. **Web Endpoint Method** (Recommended for pure PHP servers) - Use the built-in `/initializedb` endpoint
2. **Local Development Method** - Run migrations locally pointing to remote database
3. **Manual SQL Method** - Execute SQL scripts directly on the database

## Choosing Your Database

### SQLite (Recommended for Simple Setups)

**Best for:**
- Development and testing
- Small to medium deployments
- Single-server installations
- When you want the simplest setup (no database server required)

**Requirements:**
- PHP 8.1 or higher
- PHP extension: `pdo_sqlite` (usually included with PHP)
- Writable directory for the database file

**Advantages:**
- No separate database server needed
- Zero configuration - just point to a file
- Perfect for development and small projects
- File-based, easy to backup (just copy the file)

### MySQL/MariaDB (Recommended for Production)

**Best for:**
- Production deployments
- High-traffic applications
- Shared hosting environments
- When you need concurrent access from multiple applications

**Requirements:**
- PHP 8.1 or higher
- PHP extension: `pdo_mysql`
- MySQL/MariaDB server (local or remote)
- Database credentials with CREATE TABLE permissions

**Advantages:**
- Better performance for high concurrency
- Industry standard for production
- Better suited for multiple concurrent users
- More features for complex queries

## Prerequisites

Before setting up the database, ensure you have:

- PHP 8.1 or higher
- Required PHP extensions:
  - For SQLite: `pdo` and `pdo_sqlite`
  - For MySQL: `pdo` and `pdo_mysql`
  - For PostgreSQL: `pdo` and `pdo_pgsql`
- Database server (only if using MySQL/PostgreSQL)
- Database credentials with CREATE TABLE permissions (only if using MySQL/PostgreSQL)
- `.env` file configured with database connection details

## Method 1: Web Endpoint Method (Recommended)

This is the easiest method for pure PHP servers without command-line access.

### Step 1: Configure Database Connection

Edit your `.env` file and set your database connection based on your choice:

#### Option A: SQLite (Simplest - No Database Server Required)

Edit your `.env` file:

```env
DB_CONNECTION=sqlite
DB_DATABASE=C:\tools\xampp\htdocs\npm-unity-server\database\database.sqlite
```

**Or use a relative path** (Laravel will resolve it automatically):
```env
DB_CONNECTION=sqlite
# Leave DB_DATABASE empty or omit it - Laravel defaults to database/database.sqlite
```

**Important for SQLite:**
- The `database` directory must exist and be writable by the web server
- The database file will be created automatically if it doesn't exist
- You can use an absolute path or let Laravel use the default `database/database.sqlite` location
- No other database configuration is needed (no host, username, password, etc.)

**Note:** If you're using SQLite, you can comment out or remove the MySQL-related settings (`DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`) from your `.env` file.

#### Option B: MySQL/MariaDB

Edit your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

**Important for MySQL:**
- Ensure the MySQL server is running
- The database must exist (create it first if it doesn't)
- The user must have CREATE TABLE permissions
- For remote databases, ensure firewall allows connections

#### Option C: PostgreSQL

Edit your `.env` file:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

### Step 2: Initialize Database via Web Endpoint

Once your `.env` file is configured, visit this URL in your browser:

```
http://your-domain.com/initializedb
```

Or if your application is in a subdirectory:

```
http://your-domain.com/your-app-path/public/initializedb
```

### What Happens

- The endpoint checks if the `migrations` table exists
- If the database is not initialized, it runs all migrations automatically
- If the database is already initialized, it returns a message indicating so
- The endpoint is **safe to call multiple times** - it won't re-run migrations if they've already been executed

### Expected Response

**Success (first time):**
```json
{
  "success": true,
  "message": "Database initialized successfully. All migrations have been run.",
  "output": "Migration output..."
}
```

**Already Initialized:**
```json
{
  "success": false,
  "message": "Database is already initialized. Migrations have already been run.",
  "migrations_run": 8
}
```

**Error:**
```json
{
  "success": false,
  "message": "Failed to initialize database: [error details]",
  "error": "[error message]"
}
```

### Troubleshooting

**For SQLite:**
- **Permission Denied:** Ensure the `database` directory exists and is writable by the web server
- **Unable to open database file:** Check the file path in `DB_DATABASE` is correct and the directory exists
- **500 Error:** Verify PHP has the `pdo_sqlite` extension enabled

**For MySQL:**
- **500 Error:** Check your database credentials in `.env` and ensure the database user has CREATE TABLE permissions
- **Connection Refused:** Verify `DB_HOST` and `DB_PORT` are correct, and that MySQL server is running
- **Access Denied:** Verify `DB_USERNAME` and `DB_PASSWORD` are correct

**General:**
- **Table Already Exists:** This is normal if migrations were partially run. The endpoint will handle this gracefully

## Method 2: Local Development Method

If you have a local development environment with Node.js and Composer, you can run migrations locally while pointing to your remote database.

### Step 1: Set Up Local Environment

1. Clone/download the application to your local machine
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
4. Generate application key:
   ```bash
   php artisan key:generate
   ```

### Step 2: Configure Database

Edit your local `.env` file based on your database choice:

**For SQLite:**
```env
DB_CONNECTION=sqlite
DB_DATABASE=C:\tools\xampp\htdocs\npm-unity-server\database\database.sqlite
```

**For MySQL (local or remote):**
```env
DB_CONNECTION=mysql
DB_HOST=your-database-host.com
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

**Important for remote MySQL:** Ensure your remote database allows connections from your IP address. You may need to whitelist your IP in your database server's firewall settings.

### Step 3: Run Migrations Locally

Run the migrations command, which will execute on your remote database:

```bash
php artisan migrate --force
```

The `--force` flag is required when running in production-like environments.

### Step 4: Verify

After migrations complete, verify the tables were created by checking your remote database or visiting the `/initializedb` endpoint on your server.

## Method 3: Manual SQL Method

If you prefer to run SQL directly, you can extract the SQL from migrations and execute it manually.

### Step 1: Generate SQL from Migrations

On a local machine with Laravel installed, you can generate SQL:

```bash
php artisan migrate --pretend
```

This will show the SQL that would be executed without actually running it.

### Step 2: Execute SQL

Copy the generated SQL and execute it directly on your database server using:
- phpMyAdmin (for MySQL/MariaDB)
- pgAdmin (for PostgreSQL)
- Database command-line tools
- Your hosting provider's database management interface

**Note:** This method is more error-prone and doesn't track migration state in the `migrations` table. Use with caution.

## Migration Files

The following migrations are included in this application:

1. `0001_01_01_000000_create_users_table.php` - Users, password reset tokens, and sessions
2. `0001_01_01_000001_create_cache_table.php` - Cache table
3. `0001_01_01_000002_create_jobs_table.php` - Job queue table
4. `0001_01_01_000003_create_packages_table.php` - Package registry table
5. `0001_01_01_000004_create_releases_table.php` - Package releases table
6. `0001_01_01_000005_create_release_artifacts_table.php` - Release artifacts table
7. `0001_01_01_000006_create_download_history_table.php` - Download tracking table
8. `0001_01_01_000007_create_package_dependencies_table.php` - Package dependencies table

## Security Considerations

### For Production Servers

1. **Remove or Protect the Endpoint:** After initial setup, consider removing or protecting the `/initializedb` endpoint:
   - Add IP whitelist middleware
   - Add authentication requirement
   - Remove the route entirely after setup

2. **Database Permissions:** Use a database user with only the necessary permissions (CREATE, INSERT, UPDATE, DELETE, SELECT). Avoid using the root database user.

3. **Environment File:** Ensure `.env` is not publicly accessible. It should be outside the `public` directory or protected by server configuration.

## Post-Setup

After the database is initialized:

1. **Set Up Authentication:** Follow the instructions in `AUTHENTICATION_SETUP.md` to configure admin credentials
2. **Verify Installation:** Visit your application's homepage to ensure everything is working
3. **Test Database Connection:** Try logging in or accessing database-dependent features

## Troubleshooting Common Issues

### SQLite Issues

**"unable to open database file"**
- Check file path in `DB_DATABASE` is correct (absolute or relative)
- Ensure the `database` directory exists and is writable by the web server
- Check file permissions (should be readable/writable by web server user)
- On Windows, use forward slashes or double backslashes in paths: `C:/path/to/database.sqlite` or `C:\\path\\to\\database.sqlite`

**"SQLSTATE[HY000]: General error: 1 no such table"**
- This is expected on first run - run migrations to create tables
- Visit `/initializedb` endpoint or run `php artisan migrate`

### MySQL Issues

**"SQLSTATE[HY000] [2002] Connection refused"**
- Check `DB_HOST` and `DB_PORT` in `.env`
- Verify MySQL server is running
- Check firewall rules
- For remote databases, ensure your IP is whitelisted

**"SQLSTATE[HY000] [1045] Access denied"**
- Verify `DB_USERNAME` and `DB_PASSWORD` in `.env`
- Ensure database user exists and has proper permissions
- Check if the user has access to the specified database

**"SQLSTATE[HY000] [1049] Unknown database"**
- The database specified in `DB_DATABASE` doesn't exist
- Create the database first using phpMyAdmin or MySQL command line

### General Issues

**"SQLSTATE[42S02] Base table or view not found: migrations"**
- This is expected on first run - the endpoint will create the migrations table
- Visit `/initializedb` endpoint or run `php artisan migrate`

**"SQLSTATE[42S01] Base table or view already exists"**
- Some tables may have been created manually - this is usually fine, migrations will skip existing tables
- If you want a fresh start, drop the tables and re-run migrations

## Additional Resources

- [Laravel Database Documentation](https://laravel.com/docs/database)
- [Laravel Migrations Documentation](https://laravel.com/docs/migrations)
- See `AUTHENTICATION_SETUP.md` for authentication configuration

