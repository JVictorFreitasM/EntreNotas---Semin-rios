<?php
declare(strict_types=1);
function action(string $action): string {
    global $config, $https;
    csrf();
    switch ($action) {
    case 'install':
        rate('install',$_SERVER['REMOTE_ADDR'] ?? 'local',10);
        if (strlen($config['install_token'] ?? '')<32 || str_contains($config['install_token'],'SUBSTITUA') || !hash_equals($config['install_token'],input('install_token',128))) fail('Token de instalação inválido.');
        [$name,$password]=credentials();
        transaction(function() use ($name,$password) {
            $state=stateLock();
            if ($state['installed'] || query('SELECT id FROM admins LIMIT 1')->fetch()) fail('A instalação já foi concluída.');
            query('INSERT INTO admins (username,password_hash) VALUES (?,?)',[$name,password_hash($password,PASSWORD_DEFAULT)]);
            query('UPDATE system_state SET installed=1 WHERE id=1');
        });
        return 'Administrador criado. Entre com suas credenciais.';
    case 'register':
        if (isset($_COOKIE['seminar_registered'])) fail('Este navegador já foi usado para criar uma conta. Entre com sua conta existente.');
        rate('register',$_SERVER['REMOTE_ADDR'] ?? 'local',10);
        [$name,$password]=credentials();
        try {
            $id=transaction(function() use ($name,$password) {
                stateLock();
                query('INSERT INTO users (username,password_hash,private_code) VALUES (?,?,?)',[$name,password_hash($password,PASSWORD_DEFAULT),newPrivateCode()]);
                return (int)db()->lastInsertId();
            });
        } catch (PDOException $ex) { if ($ex->getCode()==='23000') fail('Esse nome de usuário já está em uso.'); throw $ex; }
        setcookie('seminar_registered','1',['expires'=>time()+315360000,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
        signedIn('student',$id);
        return 'Conta criada. Guarde suas credenciais e seu código privado.';
    case 'login':
    case 'admin_login':
        $name=input('username',50); $password=$_POST['password'] ?? '';
        if (!is_string($password) || strlen($password)>72) fail('Credenciais inválidas.');
        rate('login-ip',$_SERVER['REMOTE_ADDR'] ?? 'local',40);
        rate('login-account',$action.'|'.strtolower($name),10);
        $table=$action==='admin_login'?'admins':'users';
        $row=query("SELECT * FROM $table WHERE username=?",[$name])->fetch();
        $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        $valid=password_verify($password,$row['password_hash'] ?? $dummy);
        if (!$row || !$valid || !empty($row['blocked'])) fail('Credenciais inválidas ou conta indisponível.');
        signedIn($table==='admins'?'admin':'student',(int)$row['id']);
        return 'Você entrou com segurança.';
    case 'logout':
        $_SESSION=[]; session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
        return 'Você saiu da conta.';
    case 'create':
        admin(); rate('private-code',(string)$_SESSION['id'],15);
        $name=input('real_name',120); $title=input('title',200); $code=input('private_code',4);
        if (!preg_match('/\A[0-9]{4}\z/D',$code)) fail('Informe o código de quatro dígitos do aluno.');
        if ($name==='' || $title==='') fail('Informe nome e tema.');
        $presenter=query('SELECT id FROM users WHERE private_code=? AND blocked=0',[$code])->fetchColumn();
        if (!$presenter) fail('Código inválido ou conta indisponível.');
        query('INSERT INTO presentations (presenter_id,real_name,title) VALUES (?,?,?)',[$presenter,$name,$title]);
        return 'Código válido e apresentação vinculada.';
    case 'block':
        admin();
        $id=input('id',20); $blocked=input('blocked',1);
        if (!ctype_digit($id) || !in_array($blocked,['0','1'],true)) fail('Conta inválida.');
        transaction(function() use ($id,$blocked) {
            stateLock();
            query('UPDATE users SET blocked=? WHERE id=?',[$blocked,$id]);
        });
        return $blocked==='1'?'Conta bloqueada.':'Conta desbloqueada.';
    case 'control':
        admin(); $id=input('id',20); $command=input('command',20);
        if (!ctype_digit($id)) fail('Apresentação inválida.');
        transaction(function() use ($id,$command) {
            $state=stateLock();
            $p=query('SELECT * FROM presentations WHERE id=? FOR UPDATE',[$id])->fetch();
            if (!$p) fail('Apresentação não encontrada.');
            $active=$state['active_presentation_id'];
            if ($command==='start' || $command==='reopen') {
                if ($active!==null && (int)$active!==(int)$id) fail('Encerre a apresentação ativa antes de ativar outra.');
                if ($command==='start' && $p['status']!=='registered') fail('Esta apresentação já foi iniciada.');
                if ($command==='reopen' && !in_array($p['status'],['closed','ended'],true)) fail('Somente votações fechadas ou encerradas podem ser reabertas.');
                query('UPDATE system_state SET active_presentation_id=? WHERE id=1',[$id]);
                query('UPDATE presentations SET status=? WHERE id=?',[$command==='start'?'running':'open',$id]);
            } else {
                if ((int)$active!==(int)$id) fail('Esta apresentação não está ativa.');
                if ($command==='open' && in_array($p['status'],['running','closed'],true)) $next='open';
                elseif ($command==='close' && $p['status']==='open') $next='closed';
                elseif ($command==='end') $next='ended';
                else fail('Transição de estado inválida.');
                query('UPDATE presentations SET status=? WHERE id=?',[$next,$id]);
                if ($next==='ended') query('UPDATE system_state SET active_presentation_id=NULL WHERE id=1');
            }
        });
        return 'Estado da apresentação atualizado. Os votos foram preservados.';
    case 'vote':
        $grade=parseGrade(input('grade',10)); $id=input('presentation_id',20);
        if (!ctype_digit($id)) fail('Apresentação inválida.');
        transaction(function() use ($grade,$id) {
            $state=stateLock(); // O mesmo bloqueio usado por abrir, fechar, encerrar e bloquear conta.
            $u=user(true);
            $p=query('SELECT * FROM presentations WHERE id=? FOR UPDATE',[$id])->fetch();
            if (!$p || (int)$state['active_presentation_id']!==(int)$id || $p['status']!=='open') fail('A votação não está aberta. Sua nota não foi registrada.');
            if ((int)$p['presenter_id']===(int)$u['id']) fail('Você não pode avaliar sua própria apresentação.');
            if (query('SELECT user_id FROM participation WHERE presentation_id=? AND user_id=?',[$id,$u['id']])->fetch()) fail('Você já avaliou esta apresentação.');
            query('INSERT INTO participation (presentation_id,user_id) VALUES (?,?)',[$id,$u['id']]);
            query('INSERT INTO grades (id,presentation_id,grade) VALUES (?,?,?)',[bin2hex(random_bytes(16)),$id,$grade]);
        });
        return 'Avaliação registrada com sucesso. A nota não pode ser alterada.';
    default: fail('Operação desconhecida.');
    }
}
function snapshot(): array {
    return transaction(function() {
        // Snapshot coerente entre estado, participação e resultados.
        $role=$_SESSION['role'] ?? '';
        if ($role==='admin') {
            admin();
            $presentations=query('SELECT id,real_name,title,status FROM presentations ORDER BY id DESC')->fetchAll();
            foreach ($presentations as &$p) {
                $p['stats']=stats((int)$p['id']);
                $p['grades']=query('SELECT grade FROM grades WHERE presentation_id=? ORDER BY grade',[$p['id']])->fetchAll(PDO::FETCH_COLUMN);
            } unset($p);
            $users=query('SELECT id,username,blocked FROM users ORDER BY id')->fetchAll();
            return ['role'=>'admin','presentations'=>$presentations,'users'=>$users];
        }
        $u=user();
        $active=query('SELECT p.id,p.real_name,p.title,p.status,p.presenter_id FROM presentations p JOIN system_state s ON s.active_presentation_id=p.id WHERE s.id=1')->fetch();
        if ($active) {
            $active['own']=(int)$active['presenter_id']===(int)$u['id'];
            unset($active['presenter_id']);
            $active['voted']=(bool)query('SELECT user_id FROM participation WHERE presentation_id=? AND user_id=?',[$active['id'],$u['id']])->fetch();
        }
        $results=query("SELECT id,title FROM presentations WHERE presenter_id=? AND status='ended' ORDER BY id DESC",[$u['id']])->fetchAll();
        foreach ($results as &$p) $p['stats']=stats((int)$p['id']); unset($p);
        return ['role'=>'student','active'=>$active ?: null,'results'=>$results];
    });
}
