<?php
// Copy to config.php in the PRIVATE directory outside public_html. Never commit real secrets.
return [
 'dsn'=>'mysql:host=localhost;dbname=REPLACE_DATABASE;charset=utf8mb4',
 'db_user'=>'REPLACE_DATABASE_USER',
 'db_password'=>'REPLACE_DATABASE_PASSWORD',
 'admin_username'=>'owner',
 'admin_password_hash'=>'REPLACE_WITH_PASSWORD_HASH',
 'secret'=>'REPLACE_WITH_AT_LEAST_32_RANDOM_CHARACTERS',
 'allowed_origins'=>['https://uniqueuniformsme.com','https://www.uniqueuniformsme.com'],
 'secure_cookies'=>true,
];
