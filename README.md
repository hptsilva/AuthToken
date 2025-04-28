# AuthToken

AuthToken é um projeto PHP que implementa um sistema de autenticação baseado em tokens. Ele utiliza técnicas de codificação Base64URL e HMAC para gerar, validar e gerenciar tokens de forma segura. O projeto também inclui integração com um banco de dados MariaDB para armazenar tokens e gerenciar listas de tokens inválidos (blacklist).

## Funcionalidades

- **Geração de Tokens**: Criação de tokens únicos com informações do usuário e assinatura segura.
- **Validação de Tokens**: Verificação da integridade e validade dos tokens gerados.
- **Renovação de Tokens**: Atualização de tokens expirados para prolongar sua validade.
- **Exclusão de Tokens**: Exclusão de tokens do registro.
- **Gerenciamento de Blacklist**: Controle de tokens inválidos para evitar reutilização maliciosa.
- **Integração com Banco de Dados**: Armazenamento e consulta de tokens no banco de dados MariaDB.

## Requisitos

- PHP 8.0 ou superior
- Composer para gerenciar dependências
- Banco de dados MariaDB/MySQL

## Instalação

- Clone o repositório na raíz do seu projeto:
```php
git clone https://github.com/hptsilva/AuthToken.git
```
- Instale as dependências necessárias do projeto:
```php
composer install
```
- Crie o arquivo .env utilizando o arquivo .env.example como modelo.
```.env
DB_HOSTNAME='''Nome do host'''
DB_DATABASE='''Nome da base de dados'''
DB_USER='''Nome do usuário que se conectará na base de dados'''
DB_PASSWORD='''Senha do usuário'''
TIMEOUT='''Tempo de duração do token. Tempo em segundos.'''
```
- Execute o comando na raiz do projeto para gerar uma chave secreta. 
```php
php auth-token secret
```
- Execute o comando na raiz do projeto para realizar as migrações das tabelas.
```php
php auth-token migrate
```
- Instancie as classes:
```php
use AuthToken\Token

$token = new Token();
$response = $token->generateToken($user, $password, $user_id); // Gerar Token
```
```php
use AuthToken\Auth

$auth = new Auth();
$response = $auth->authenticateToken($token); // Autenticar token
$response = $auth->resetToken($token); // Resetar Token
$response = $auth->deleteToken($token) // Deletar Token
```

## Observações
No arquivo composer.json do seu projeto mapeie os namespaces do AuthToken para utilizar as classes de autenticação e geração de token:
```php
"autoload": {
  "psr-4": {
      "AuthToken\\": "AuthToken/src/"
  }
},
```
Feito isso, utilize o comando abaixo para regenerar o autoload do Composer do seu projeto.
```php
composer dump-autoload
```
