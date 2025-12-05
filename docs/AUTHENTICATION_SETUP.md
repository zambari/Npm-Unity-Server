# Authentication Setup

This server uses a dual authentication system with super-user privileges for user management.

## Initial Setup

### 1. Environment Configuration

Copy the `.env.example` file to `.env` (if it doesn't exist):

```bash
cp .env.example .env
```

### 2. Database Configuration

Configure your database connection in the `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

For SQLite (default), you can use:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite
```

### 3. Super-User Credentials

Set up your super-user credentials in `.env`. These credentials grant full access to user management:

```env
ADMIN_USERNAME=your_super_user_username
ADMIN_PASSWORD=your_super_user_password
ADMIN_EMAIL=admin@example.com
```

**Important:** The super-user credentials are stored in `.env` and provide full administrative access, including the ability to create, disable, and reset passwords for all users.

### 4. Password Salt

Set a secure password salt for hashing user passwords:

```env
PASSWORD_SALT=your_secure_random_salt_string
```

**Security Note:** Use a strong, random string for `PASSWORD_SALT`. This salt is used to hash all user passwords stored in the database.

### 5. Run Migrations

After configuring the database, you need to run migrations to create the necessary tables. See `DATABASE_SETUP.md` for detailed instructions.

**Quick options:**
- **Web endpoint (recommended for pure PHP servers):** Visit `/initializedb` in your browser
- **Command line:** Run `php artisan migrate` if you have command-line access

## Authentication Flow

### Super-User Login

When logging in with the credentials from `.env` (`ADMIN_USERNAME` and `ADMIN_PASSWORD`):
- You are granted **super-user** privileges
- You are redirected to `/admin/users` (User Management page)
- You can create, disable, and reset passwords for all users

### Regular User Login

When logging in with credentials from the database (users created via the User Management page):
- You are granted **regular user** privileges
- You are redirected to the welcome page
- You can edit packages but **cannot** access user management
- You **cannot** create, disable, or manage other users

## Adding New Users

1. Log in with your super-user credentials (from `.env`)
2. Navigate to `/admin/users` (User Management page)
3. Click **"+ Add New User"** to expand the user creation form
4. Fill in:
   - **Name**: User's display name
   - **Email**: User's email address (used for login)
   - **Password**: Minimum 5 characters (will be hashed with salt)
5. Click **"Create User"**

The new user can now log in using their email (or name) and password. Their password is automatically hashed using the `PASSWORD_SALT` from your `.env` file.

## User Management Features

Super-users can:

- **Create Users**: Add new users via the collapsed form
- **Disable/Enable Users**: Toggle user accounts on or off
- **Reset Passwords**: Generate new passwords for any user

Regular users (database credentials) can:
- Edit packages
- Access package management features
- **Cannot** access `/admin/users` or manage other users

## Security Best Practices

1. **Never commit `.env` to version control** - It contains sensitive credentials
2. **Use strong passwords** for `ADMIN_PASSWORD` and `PASSWORD_SALT`
3. **Change default values** - Don't use example values in production
4. **Regularly rotate** the `PASSWORD_SALT` if compromised (requires resetting all user passwords)

