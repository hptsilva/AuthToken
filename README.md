# AuthToken

AuthToken is a modern PHP project that implements a secure token-based authentication system. Ideal for modern APIs and web applications.

The library uses JSON Web Tokens (JWT) for short-lived, verifiable access tokens and cryptographically secure random strings for long-lived refresh tokens, which are stored in a database.

## Features

- **Secure Authentication Flow**: Generates a pair of tokens: a short-lived JWT Access Token and a long-lived, database-backed Refresh Token.
- **Stateless JWT Access Tokens**: Fast, stateless validation of access tokens using cryptographic signatures (HMAC SHA-256), eliminating the need for a database lookup on every request.
- **Stateful Refresh Tokens**: Secure, long-lived tokens stored in the database, used to obtain new access tokens when they expire.
- **Token Rotation**: For enhanced security, using a refresh token automatically invalidates it and issues a new one, preventing token reuse.
- **Secure Logout**: A clear logout mechanism that revokes the user's session by deleting the corresponding refresh token from the database.
- **Database Integration**: Storage and management of refresh tokens in a MariaDB/MySQL or SQLite database.

## Requirements

- PHP 8.2 or higher
- Composer for dependency management
- MariaDB/MySQL or SQLite database

## Installation

1.  Clone the repository into your project's root directory:
    ```bash
    git clone https://github.com/hptsilva/AuthToken.git
    ```
2.  Install the project's required dependencies:
    ```bash
    composer install
    ```
3.  Create the **.env** file in your project root using **.env.example** as a template and fill in your details:
    ```.env
    AUTHTOKEN_DB_CONNECTION=mysql # Type of database connection (e.g., mysql, mariadb, sqlite).
    AUTHTOKEN_DB_HOSTNAME=localhost # Host name
    AUTHTOKEN_DB_DATABASE=auth # Database name
    AUTHTOKEN_DB_USER=root # Database username
    AUTHTOKEN_DB_PASSWORD=secret # User password
    AUTHTOKEN_USER_TYPE=INT # Type of the user ID value (e.g., INT or VARCHAR(255))
    AUTHTOKEN_APP_SECRET=secretkey # Secret key used for signing JWTs
    
    # Access Token lifetime (PHP DateInterval format).
    AUTHTOKEN_ACCESS_TOKEN_TIMEOUT='+15 minutes'
    
    # Refresh Token lifetime (PHP DateInterval format).
    AUTHTOKEN_REFRESH_TOKEN_INTERVAL='P7D'
    ```
4.  Run the following command in the project root to generate a secret key (used for signing JWTs):
    ```bash
    php auth-token secret
    ```
5.  Run the following command in the project root to execute table migrations:
    ```bash
    php auth-token migrate
    ```

## Usage

The new workflow separates login, authentication, session refreshing, and logout into distinct methods. Here is a complete usage example:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use AuthToken\Auth;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(realpath(__DIR__ . '/'));
$dotenv->load();

// It's recommended to instantiate the Auth class once and reuse it.
$auth = new Auth();

// --- 1. User Login ---
// After you verify the user's password, call login() with their user ID.
$userId = 123;
$loginResponse = $auth->login($userId);

if (!$loginResponse['status']) {
    die("Login failed!");
}

// Store both tokens securely on the client-side.
$accessToken = $loginResponse['access_token'];
$refreshToken = $loginResponse['refresh_token'];

echo "Login successful!\n";


// --- 2. Authenticating an API Request ---
// For each request to a protected endpoint, validate the Access Token.
$authResponse = $auth->authenticate($accessToken);

if ($authResponse['status']) {
    echo "Access granted for user ID: " . $authResponse['user_id'] . "\n";
} else {
    // This block is executed if the Access Token is expired or invalid.
    echo "Access token is invalid or has expired. Attempting to refresh...\n";

    // --- 3. Refreshing the Session ---
    // Use the Refresh Token to get a new pair of tokens.
    $refreshResponse = $auth->refresh($refreshToken);

    if ($refreshResponse['status']) {
        // Update the tokens on the client-side with the new ones.
        $accessToken = $refreshResponse['access_token'];
        $refreshToken = $refreshResponse['refresh_token'];
        echo "Tokens refreshed successfully. You can now retry the original request.\n";
    } else {
        // If the refresh token is also invalid or expired, the user must log in again.
        echo "Refresh token is invalid. Please log in again.\n";
        // Redirect to login page.
    }
}


// --- 4. User Logout ---
// To log out, invalidate the session by deleting the Refresh Token.
$logoutResponse = $auth->logout($refreshToken);

if ($logoutResponse['status']) {
    echo "Logout successful.\n";
}

```

## Notes
In your project's **composer.json** file, map the AuthToken namespaces to use the authentication and token generation classes:
```php
"autoload": {
  "psr-4": {
      "AuthToken\\": "AuthToken/src/"
  }
},
```
After that, run the following command to regenerate your project's Composer autoload:
```php
composer dump-autoload
```
If you need to rollback table migrations, run the following command in the project root:
```php
php auth-token rollback
```
