<?php
// Copie para config.php. Nunca publique credenciais no Git.
return [
    'db_host' => 'sqlXXX.infinityfree.com',
    'db_port' => 3306,
    'db_name' => 'if0_XXXXXXX_seminarios',
    'db_user' => 'if0_XXXXXXX',
    'db_password' => 'SENHA_DO_BANCO',
    // Gere dois valores DIFERENTES com: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
    'app_key' => 'SUBSTITUA_POR_64_CARACTERES_HEXADECIMAIS',
    'install_token' => 'SUBSTITUA_POR_OUTRO_SEGREDO_ALEATORIO',
    'require_https' => true,
];
