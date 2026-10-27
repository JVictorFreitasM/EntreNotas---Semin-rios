<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
try {
    $count=transaction(function() {
        stateLock();
        $rows=query('SELECT id,private_code FROM users FOR UPDATE')->fetchAll();
        if (count($rows)>10000) fail('Há mais contas do que códigos disponíveis. Nenhuma alteração realizada.');
        $count=0;
        foreach ($rows as $row) {
            if (preg_match('/\A[0-9]{4}\z/D',$row['private_code'])) continue;
            query('UPDATE users SET private_code=? WHERE id=?',[newPrivateCode(),$row['id']]);
            $count++;
        }
        return $count;
    });
    echo "Migração concluída: $count código(s) atualizado(s). Contas e votos preservados.\n";
} catch (Throwable $ex) { fwrite(STDERR,"Não foi possível migrar. Verifique a conexão e a configuração do banco.\n"); exit(1); }
