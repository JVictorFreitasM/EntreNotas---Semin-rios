<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/actions.php';
$isApi=isset($_GET['api']);
try {
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
        echo json_encode(snapshot(),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
    }
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        try { $_SESSION['flash']=['ok',action(input('action',30))]; }
        catch (DomainException $ex) { $_SESSION['flash']=['error',$ex->getMessage()]; }
        $target=($_SESSION['role'] ?? '')==='admin'?'admin':((($_SESSION['role'] ?? '')==='student')?'student':input('return_view',30));
        if (!in_array($target,['home','login','register','admin_login','install','student','admin'],true)) $target='home';
        header('Location: index.php?view='.$target, true,303); exit;
    }
    $installed=(bool)query('SELECT installed FROM system_state WHERE id=1')->fetchColumn();
    $view=$_GET['view'] ?? 'home';
    if (($_SESSION['role'] ?? '')==='student') { $me=user(); $view='student'; }
    elseif (($_SESSION['role'] ?? '')==='admin') { admin(); $view='admin'; }
    elseif (in_array($view,['student','admin'],true)) $view='login';
    if ($view==='install' && $installed) $view='admin_login';
    $flash=$_SESSION['flash'] ?? null; unset($_SESSION['flash']);
} catch (DomainException $ex) {
    if ($isApi) { http_response_code(401); echo json_encode(['error'=>$ex->getMessage()]); exit; }
    $_SESSION['flash']=['error',$ex->getMessage()]; header('Location: index.php?view=login',true,303); exit;
} catch (Throwable $ex) {
    // Não registra SQL, credenciais, nota nem identidade em logs da aplicação.
    http_response_code(503);
    if ($isApi) { header('Content-Type: application/json'); echo json_encode(['error'=>'Servidor temporariamente indisponível.']); }
    else echo 'Servidor temporariamente indisponível. Verifique a configuração e a importação do banco de dados.';
    exit;
}
function hidden(string $action): void { echo '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'"><input type="hidden" name="action" value="'.e($action).'">'; }
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#1e3059"><title>EntreNotas · Seminários</title><link rel="stylesheet" href="assets/style.css?v=2"><script src="assets/app.js" defer></script></head>
<body data-role="<?= e($_SESSION['role'] ?? '') ?>"><header class="topbar"><a class="brand" href="index.php"><span class="brand-icon">e.</span> EntreNotas</a><span class="top-tag">AVALIAÇÃO DE SEMINÁRIOS</span><?php if(isset($_SESSION['role'])): ?><form method="post"><?php hidden('logout'); ?><button class="subtle">Sair da conta ↗</button></form><?php endif; ?></header>
<main><?php if($flash): ?><div class="notice <?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></div><?php endif; ?>
<?php if($view==='student'): ?>
<div class="page-heading"><div><p class="eyebrow">SEU ESPAÇO</p><h1>Olá, Aluno <?= e(str_pad((string)$me['id'],3,'0',STR_PAD_LEFT)) ?>.</h1><p>Acompanhe, avalie e contribua com a turma.</p></div><span class="pill">● Área do aluno</span></div>
<div class="student-grid"><aside><section class="card identity"><span class="eyebrow">SUA IDENTIDADE</span><h2>Aluno <?= e(str_pad((string)$me['id'],3,'0',STR_PAD_LEFT)) ?></h2><p>Este é seu nome anônimo fixo.</p><hr><label for="private-code">Seu código de apresentação</label><div class="code-row"><input id="private-code" readonly value="<?= e($me['private_code']) ?>"><button type="button" id="copy-code" class="subtle">Copiar</button></div><p class="small">Compartilhe apenas com o administrador, quando for apresentar. Não publique este código.</p><span id="copy-status" role="status"></span></section><div class="privacy-note"><strong>Uma avaliação, com cuidado.</strong><p>Suas notas são exibidas sem identificação. Avalie o trabalho com atenção e respeito.</p></div></aside>
<div><section class="card live-card"><div class="section-top"><span class="eyebrow">AGORA NA TURMA</span><span class="live-dot">Atualização automática</span></div><div id="active-presentation"><p>Consultando apresentação…</p></div><form method="post" id="vote-form" hidden><?php hidden('vote'); ?><input type="hidden" name="presentation_id" id="vote-presentation"><label for="grade">Qual nota você dá para esta apresentação?</label><div class="grade-row"><input id="grade" name="grade" inputmode="decimal" placeholder="0,0" required maxlength="4" autocomplete="off" aria-describedby="grade-hint"><span>/ 10</span><button type="submit">Confirmar avaliação →</button></div><p class="small" id="grade-hint">De 0 a 10, com uma casa decimal. Após enviar, a nota não poderá ser alterada.</p></form><p id="vote-message" class="muted"></p></section>
<section class="card results"><div class="section-top"><h2>Meus resultados</h2><span class="muted small">Apresentações encerradas</span></div><div id="student-results">Consultando resultados…</div></section></div></div>
<?php elseif($view==='admin'): ?>
<div class="page-heading"><div><p class="eyebrow">PAINEL ADMINISTRATIVO</p><h1>A turma em um só lugar.</h1><p>Organize as apresentações e acompanhe as avaliações.</p></div><span class="pill">● Administração</span></div>
<div class="admin-grid"><section class="card"><p class="eyebrow">NOVA APRESENTAÇÃO</p><h2>Prepare o próximo seminário</h2><form method="post"><?php hidden('create'); ?><label for="real_name">Nome real do apresentador</label><input id="real_name" name="real_name" maxlength="120" required><label for="title">Título ou tema</label><input id="title" name="title" maxlength="200" required><label for="private_code">Código privado fornecido pelo aluno</label><input id="private_code" name="private_code" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" placeholder="Ex.: 0427" required autocomplete="off"><p class="small muted">O vínculo é validado sem revelar o usuário ou o nome anônimo da conta.</p><button>Vincular apresentação →</button></form></section><section class="card"><p class="eyebrow">COMO CONDUZIR</p><h2>No ritmo da sua turma.</h2><ol class="steps"><li>Cadastre o tema e vincule o código.</li><li>Inicie a apresentação com votação fechada.</li><li>Abra a votação quando estiver pronto.</li><li>Feche os votos e encerre a apresentação.</li></ol><p class="small muted">Apenas uma apresentação pode ficar ativa. Reabrir preserva todas as notas e os controles de voto único.</p></section></div>
<section class="card results"><div class="section-top"><h2>Apresentações e resultados</h2><span class="live-dot">Atualização automática</span></div><div id="admin-presentations">Consultando apresentações…</div></section>
<section class="card results"><h2>Contas da turma</h2><p class="small muted">Os usuários abaixo são credenciais de acesso. Esta lista não exibe códigos privados, nomes anônimos nem vínculos com apresentadores.</p><div class="notice">O bloqueio de cadastro por navegador é básico. Limpar dados, trocar de navegador ou dispositivo permite contorná-lo; não garante uma conta por celular.</div><div id="admin-users">Consultando contas…</div></section>
<?php elseif(in_array($view,['login','register','admin_login','install'],true)): $register=$view==='register'; $install=$view==='install'; ?>
<div class="auth-wrap"><a class="back" href="index.php">← Voltar ao início</a><section class="card auth"><p class="eyebrow"><?= $install?'CONFIGURAÇÃO INICIAL':($view==='admin_login'?'ADMINISTRAÇÃO':'ÁREA DO ALUNO') ?></p><h1><?= $install?'Vamos começar.':($register?'Sua voz faz parte.':'Bom ter você aqui.') ?></h1><p class="muted"><?= $register?'Crie suas credenciais sem informar seu nome real.':'Entre com suas credenciais para continuar.' ?></p><form method="post" id="auth-form"><?php hidden($install?'install':($register?'register':($view==='admin_login'?'admin_login':'login'))); ?><input type="hidden" name="return_view" value="<?= e($view) ?>"><?php if($install): ?><label for="install_token">Token secreto configurado em config.php</label><input type="password" name="install_token" id="install_token" required autocomplete="off"><?php endif; ?><label for="username">Nome de usuário</label><input id="username" name="username" minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.-]{3,50}" autocomplete="username" required><?php if($register): ?><p class="small">Escolha algo que não identifique você. Não use nome real, matrícula ou e-mail.</p><?php endif; ?><label for="password">Senha</label><input type="password" id="password" name="password" <?= $register||$install?'minlength="10"':'' ?> maxlength="72" autocomplete="<?= $register||$install?'new-password':'current-password' ?>" required><p class="small muted">Ao criar uma conta, use de 10 a 72 bytes na senha.</p><button><?= $install?'Criar administrador':($register?'Criar minha conta →':'Entrar →') ?></button></form><?php if($register): ?><p class="small muted">Este navegador receberá uma marca local para dificultar cadastros repetidos. Ela não impede cadastros em outros dispositivos ou após limpar os dados.</p><?php elseif($view==='login'): ?><p class="small">Primeira vez aqui? <a href="?view=register">Crie sua conta</a>.</p><?php endif; ?></section></div>
<?php else: ?>
<section class="hero"><div class="hero-copy"><p class="eyebrow">APRENDER TAMBÉM É DAR FEEDBACK</p><h1>Uma turma.<br>Muitas perspectivas<span>.</span></h1><p class="lead">Um espaço simples para avaliar seminários com cuidado, acompanhar suas apresentações e aprender juntos.</p><div class="hero-actions"><a class="button" href="?view=login">Entrar como aluno →</a><a class="button secondary" href="?view=register">Criar minha conta</a></div><a class="admin-link" href="?view=admin_login">Sou administrador ↗</a></div><div class="hero-art" aria-hidden="true"><div class="orbit"></div><div class="art-label">CADA PERSPECTIVA CONTA</div><div class="score-card"><span>SEMINÁRIOS · SUA TURMA</span><div class="score-bars"><i></i><i></i><i></i><i></i><i></i></div><strong>Aprender.<br>Compartilhar.<br>Evoluir.</strong><div class="art-foot">FEEDBACK QUE CONSTRÓI <span>↗</span></div></div><div class="floating">✦ Juntos, a gente vai além.</div></div></section>
<div class="features"><article><span>01 /</span><h2>Entre sem se expor</h2><p>Crie um usuário sem seu nome real. Você recebe uma identidade anônima e um código privado.</p></article><article><span>02 /</span><h2>Avalie no momento certo</h2><p>Quando a votação abrir, dê sua nota de 0 a 10. Uma contribuição por apresentação.</p></article><article><span>03 /</span><h2>Acompanhe sua evolução</h2><p>Consulte suas médias após o encerramento e leve o aprendizado para a próxima apresentação.</p></article></div>
<?php endif; ?>
<p id="connection-status" class="connection" role="status"></p>
</main><footer><span class="brand-text">EntreNotas</span><span>Feedback com respeito. Aprendizado em conjunto.</span><p class="small">Notas sem identificação na aplicação. O anonimato não é absoluto: acesso privilegiado e circunstâncias de uma turma pequena podem permitir inferências.</p></footer>
<template id="csrf-template"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"></template>
</body></html>
