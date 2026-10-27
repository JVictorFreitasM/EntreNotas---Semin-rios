<?php
// Rodar apenas com banco vazio de testes e SEMINAR_CONFIG apontando para configuração própria.
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !getenv('SEMINAR_CONFIG')) exit("Use CLI e SEMINAR_CONFIG com um banco vazio de testes.\n");
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/actions.php';
if (($argv[1] ?? '') === 'worker') {
    $_SESSION=['role'=>'student','id'=>(int)$argv[2],'csrf'=>'test-csrf'];
    $_POST=['csrf'=>'test-csrf','action'=>'vote','presentation_id'=>$argv[3],'grade'=>'3,5'];
    try { action('vote'); echo 'accepted'; } catch (DomainException $ex) { echo 'rejected'; }
    exit;
}
if (query('SELECT COUNT(*) FROM users')->fetchColumn() || query('SELECT installed FROM system_state WHERE id=1')->fetchColumn()) exit("O banco deve estar vazio, recém-importado.\n");
$checks=0;
function check(bool $ok,string $message): void { global $checks; if(!$ok) throw new RuntimeException('FALHOU: '.$message); $checks++; }
function act(string $action,array $data=[]): string { $_POST=['csrf'=>$_SESSION['csrf'],'action'=>$action]+$data; return action($action); }
function denied(callable $fn,string $message): void { try {$fn();} catch (DomainException $ex) {check(true,$message);return;} check(false,$message); }
function asAdmin(int $id): void { $_SESSION=['role'=>'admin','id'=>$id,'csrf'=>'test-csrf']; }
function asStudent(int $id): void { $_SESSION=['role'=>'student','id'=>$id,'csrf'=>'test-csrf']; }
try {
    $password=' secure pass 123 ';
    act('install',['username'=>'admin_test','password'=>$password,'install_token'=>$config['install_token']]);
    $adminId=(int)query('SELECT id FROM admins')->fetchColumn();
    check((bool)query('SELECT installed FROM system_state')->fetchColumn(),'instalação');
    denied(fn()=>act('install',['username'=>'admin_two','password'=>$password,'install_token'=>$config['install_token']]),'instalador bloqueado');
    act('admin_login',['username'=>'admin_test','password'=>$password]);
    check($_SESSION['role']==='admin','login administrador com espaços na senha');
    $_POST=['csrf'=>'invalid']; denied(fn()=>action('logout'),'CSRF inválido');
    $ids=[];
    foreach(['presenter','reviewer_one','reviewer_two','reviewer_race','reviewer_duplicate'] as $name) {
        unset($_COOKIE['seminar_registered']);
        act('register',['username'=>$name,'password'=>$password]); $ids[]=(int)$_SESSION['id'];
    }
    [$presenter,$voter,$voter2,$racer,$dupe]=$ids;
    check(count(array_unique($ids))===5,'cadastro');
    $codes=query('SELECT private_code FROM users')->fetchAll(PDO::FETCH_COLUMN);
    check(count(array_unique($codes))===5 && count(array_filter($codes,fn($c)=>preg_match('/\A[0-9]{4}\z/D',$c)))===5,'códigos únicos de quatro dígitos');
    $_COOKIE['seminar_registered']='1'; denied(fn()=>act('register',['username'=>'duplicate','password'=>$password]),'cookie de cadastro'); unset($_COOKIE['seminar_registered']);
    act('logout'); act('login',['username'=>'reviewer_one','password'=>$password]); check((int)$_SESSION['id']===$voter,'login aluno');
    denied(fn()=>act('create',['real_name'=>'X','title'=>'X','private_code'=>'bad']),'autorização administrativa');
    $code=query('SELECT private_code FROM users WHERE id=?',[$presenter])->fetchColumn();
    asAdmin($adminId);
    denied(fn()=>act('create',['real_name'=>'X','title'=>'X','private_code'=>'bad']),'código inválido');
    act('create',['real_name'=>'Apresentador real','title'=>'Seminário A','private_code'=>$code]);
    $p=(int)db()->lastInsertId();
    act('create',['real_name'=>'Apresentador real','title'=>'Seminário B','private_code'=>$code]);
    $other=(int)db()->lastInsertId();
    $adminData=snapshot();
    check(!str_contains(json_encode($adminData),$code) && !isset($adminData['presentations'][0]['presenter_id']),'painel não retorna vínculos privados');
    act('control',['id'=>(string)$p,'command'=>'start']);
    check(query('SELECT status FROM presentations WHERE id=?',[$p])->fetchColumn()==='running','inicia fechado');
    denied(fn()=>act('control',['id'=>(string)$other,'command'=>'start']),'uma apresentação ativa');
    asStudent($voter);
    denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>'4,0']),'voto antes de abrir');
    asAdmin($adminId);act('control',['id'=>(string)$p,'command'=>'open']);
    asStudent($presenter); denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>'5']),'autoavaliação');
    asStudent($voter);
    foreach(['-1','5.1','6','10','10.1','3.25','abc','1e1',''] as $grade) denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>$grade]),'nota inválida');
    act('vote',['presentation_id'=>(string)$p,'grade'=>'4,2']);
    denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>'4']),'duplicidade');
    asStudent($voter2);act('vote',['presentation_id'=>(string)$p,'grade'=>'4.0']);
    check(stats($p)===['votes'=>2,'average'=>'4,10'],'média decimal na escala 0–5');
    check(parseGrade('0')==='0.0' && parseGrade('5,0')==='5.0','limites inclusivos 0 e 5');
    check((float)query('SELECT MIN(grade) FROM grades WHERE presentation_id=?',[$p])->fetchColumn()===8.0,'persistência compatível na escala interna');
    asAdmin($adminId);
    $visible=snapshot()['presentations'];
    $shown=array_values(array_filter($visible,fn($row)=>(int)$row['id']===$p))[0];
    check(array_map('floatval',$shown['grades'])===[4.0,4.2],'notas administrativas na escala 0–5');
    // Registro no formato anterior: 8/10 deve aparecer como 4/5, sem migração.
    query('INSERT INTO grades (id,presentation_id,grade) VALUES (?,?,?)',[bin2hex(random_bytes(16)),$other,'8.0']);
    check(stats($other)===['votes'=>1,'average'=>'4,00'],'compatibilidade com notas antigas');
    asAdmin($adminId); act('control',['id'=>(string)$p,'command'=>'close']);
    asStudent($racer); denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>'4']),'voto após fechar');
    asAdmin($adminId); act('control',['id'=>(string)$p,'command'=>'reopen']);
    asStudent($voter); denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>'4']),'reabrir preserva duplicidade');
    asAdmin($adminId);act('block',['id'=>(string)$racer,'blocked'=>'1']);
    asStudent($racer); denied(fn()=>act('vote',['presentation_id'=>(string)$p,'grade'=>'4']),'sessão bloqueada');
    denied(fn()=>act('login',['username'=>'reviewer_race','password'=>$password]),'login bloqueado');
    asAdmin($adminId);act('block',['id'=>(string)$racer,'blocked'=>'0']);
    // Concorrência real: a transação de fechamento mantém o bloqueio enquanto outro processo tenta votar.
    db()->beginTransaction(); stateLock();
    query("UPDATE presentations SET status='closed' WHERE id=?",[$p]);
    $pipes=[];
    $process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$racer,(string)$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process)) throw new RuntimeException('Não foi possível iniciar worker.');
    fclose($pipes[0]); usleep(400000); db()->commit();
    $result=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($process)===0 && $result==='rejected','fechamento vence corrida: '.$err);
    check(stats($p)['votes']===2,'voto concorrente recusado sem resíduo');
    asAdmin($adminId);act('control',['id'=>(string)$p,'command'=>'reopen']);
    // Dois processos distintos tentam votar simultaneamente com a mesma conta.
    $processes=[];
    for($i=0;$i<2;$i++) {
        $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',(string)$dupe,(string)$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$processes[]=[$proc,$pipes];
    }
    $outputs=[];
    foreach($processes as [$proc,$pipes]) { $outputs[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0,'worker duplicidade: '.$err); }
    sort($outputs);check($outputs===['accepted','rejected'],'somente um voto concorrente aceito');
    check(stats($p)['votes']===3,'participação e nota atômicas');
    act('control',['id'=>(string)$p,'command'=>'end']);
    asStudent($presenter);check(count(snapshot()['results'])===1,'resultado próprio encerrado');
    asStudent($voter);check(snapshot()['results']===[],'não consulta média de colegas');
    asAdmin($adminId);act('control',['id'=>(string)$other,'command'=>'start']);
    denied(fn()=>act('control',['id'=>(string)$p,'command'=>'reopen']),'reabrir exige encerrar ativa');
    act('control',['id'=>(string)$other,'command'=>'end']);act('control',['id'=>(string)$p,'command'=>'reopen']);
    check(stats($p)['votes']===3,'notas preservadas após encerramento e reabertura');
    check((int)query('SELECT COUNT(*) FROM participation WHERE presentation_id=?',[$p])->fetchColumn()===3,'participação consistente');
    echo "OK: $checks verificações, incluindo concorrência real no MySQL/MariaDB.\n";
} catch(Throwable $ex) {if(db()->inTransaction()) db()->rollBack();fwrite(STDERR,$ex->getMessage()."\n");exit(1);}
