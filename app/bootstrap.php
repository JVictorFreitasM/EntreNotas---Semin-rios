<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
header('Cache-Control: no-store');
ini_set('display_errors', '0');
$configPath = getenv('SEMINAR_CONFIG') ?: dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Configuração pendente. Consulte o README.md para criar config.php e importar schema.sql.');
}
$config = require $configPath;
if (strlen($config['app_key'] ?? '') < 32 || str_contains($config['app_key'], 'SUBSTITUA')) {
    http_response_code(503); exit('Configure uma app_key aleatória antes de continuar.');
}
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (($config['require_https'] ?? true) && !$https) {
    http_response_code(403); exit('Acesse este sistema por HTTPS.');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('seminar_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function db(): PDO {
    global $config;
    static $pdo;
    if (!$pdo) $pdo = new PDO('mysql:'.(isset($config['db_socket']) ? 'unix_socket='.$config['db_socket'] : 'host='.$config['db_host'].';port='.($config['db_port'] ?? 3306)).';dbname='.$config['db_name'].';charset=utf8mb4', $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function query(string $sql, array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fail(string $message): never { throw new DomainException($message); }
function input(string $key, int $max=200): string {
    $v=$_POST[$key] ?? '';
    if (!is_string($v) || strlen($v)>$max) fail('Campo inválido: '.$key.'.');
    return trim($v);
}
function csrf(): void { if (!hash_equals($_SESSION['csrf'], input('csrf',128))) fail('Sessão expirada. Atualize a página e tente novamente.'); }
function user(bool $lock=false): array {
    if (($_SESSION['role'] ?? '') !== 'student') fail('Entre como aluno para continuar.');
    $u=query('SELECT * FROM users WHERE id=?'.($lock?' FOR UPDATE':''), [$_SESSION['id']])->fetch();
    if (!$u || $u['blocked']) { unset($_SESSION['role'],$_SESSION['id']); fail('Conta bloqueada ou indisponível. Procure o administrador.'); }
    return $u;
}
function admin(): void {
    if (($_SESSION['role'] ?? '') !== 'admin' || !query('SELECT id FROM admins WHERE id=?',[$_SESSION['id']])->fetch()) fail('Acesso administrativo necessário.');
}
function rate(string $scope, string $subject, int $limit, int $seconds=900): void {
    global $config;
    $key=hash_hmac('sha256',$scope.'|'.$subject,$config['app_key']);
    query('INSERT INTO rate_limits (bucket,attempts,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=?,1,attempts+1), expires_at=IF(expires_at<=?,VALUES(expires_at),expires_at)',[$key,time()+$seconds,time(),time()]);
    if ((int)query('SELECT attempts FROM rate_limits WHERE bucket=?',[$key])->fetchColumn()>$limit) fail('Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.');
    // Limpeza limitada de registros expirados; não mantém histórico de atividade.
    query('DELETE FROM rate_limits WHERE expires_at < ? LIMIT 100',[time()]);
}
function credentials(): array {
    $name=input('username',50); $password=$_POST['password'] ?? '';
    if (!preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/D',$name)) fail('Use um usuário de 3 a 50 letras, números, pontos, traços ou sublinhados.');
    if (!is_string($password) || strlen($password)<10 || strlen($password)>72) fail('A senha deve ter entre 10 e 72 bytes.');
    return [$name,$password];
}
function signedIn(string $role,int $id): void { session_regenerate_id(true); $_SESSION=['role'=>$role,'id'=>$id,'csrf'=>bin2hex(random_bytes(32))]; }
function stateLock(): array { return query('SELECT * FROM system_state WHERE id=1 FOR UPDATE')->fetch(); }
function transaction(callable $fn): mixed {
    db()->beginTransaction();
    try { $result=$fn(); db()->commit(); return $result; }
    catch (Throwable $ex) { if(db()->inTransaction()) db()->rollBack(); throw $ex; }
}
function parseGrade(string $raw): string {
    $raw=str_replace(',','.',trim($raw));
    if (!preg_match('/\A(?:[0-4](?:\.[0-9])?|5(?:\.0)?)\z/D',$raw)) fail('Informe uma nota de 0 a 5, com no máximo uma casa decimal.');
    return number_format((float)$raw,1,'.','');
}
function stats(int $id): array {
    $r=query('SELECT COUNT(*) AS votes, AVG(grade) / 2 AS average FROM grades WHERE presentation_id=?',[$id])->fetch();
    return ['votes'=>(int)$r['votes'],'average'=>$r['average']===null?null:number_format((float)$r['average'],2,',','.')];
}
function statusLabel(string $s): string {
    return ['registered'=>'Apresentação cadastrada','running'=>'Em andamento · votação ainda fechada','open'=>'Votação aberta','closed'=>'Votação fechada','ended'=>'Apresentação encerrada'][$s] ?? $s;
}

// Deve ser chamado mantendo o bloqueio de system_state na transação.
function newPrivateCode(): string {
    $used=array_fill_keys(query('SELECT private_code FROM users')->fetchAll(PDO::FETCH_COLUMN),true);
    $start=random_int(0,9999);
    for ($i=0;$i<10000;$i++) {
        $code=str_pad((string)(($start+$i)%10000),4,'0',STR_PAD_LEFT);
        if (!isset($used[$code])) return $code;
    }
    fail('Todos os códigos de quatro dígitos estão em uso. Procure o administrador.');
}
