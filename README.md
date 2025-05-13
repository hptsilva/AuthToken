# AuthToken

AuthToken is a PHP project that implements a token-based authentication system. It uses Base64URL encoding and HMAC techniques to securely generate, validate, and manage tokens. The project also includes integration with a MariaDB|MySQL or SQLite database to store tokens and manage invalid token lists (blacklist).

## Features

- **Token Generation**: Creation of unique tokens with user information and secure signatures.
- **Token Validation**: Verification of token integrity and validity.
- **Token Renewal**: Updating expired tokens to extend their validity.
- **Token Deletion**: Removal of tokens from the registry.
- **Blacklist Management**: Control of invalid tokens to prevent malicious reuse.
- **Database Integration**: Storage and querying of tokens in a MariaDB/MySQL or SQLite database.

## Requirements

- PHP 8.0 or higher
- Composer for dependency management
- MariaDB/MySQL or SQLite database

## Installation

- Clone the repository into your project's root directory:
```php
git clone https://github.com/hptsilva/AuthToken.git
```
- Install the project's required dependencies:
```php
composer install
```
- Create the **.env** file using **.env.example** as a template:
```.env
DB_HOSTNAME='''Host name'''
DB_DATABASE='''Database name'''
DB_USER='''Database username'''
DB_PASSWORD='''User password'''
TIMEOUT='''Token duration in seconds'''
```
- Run the following command in the project root to generate a secret key:
```php
php auth-token secret
```
- Run the following command in the project root to execute table migrations:
```php
php auth-token migrate
```
- Instantiate the class:
```php
use AuthToken\Auth

$auth = new Auth();
$response = $token->generateToken($user, $password, $user_id); // Generate Token 
$response = $auth->authenticateToken($token); // Authenticate token  
$response = $auth->resetToken($token); // Reset Token 
$response = $auth->deleteToken($token) // Delete Token
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
