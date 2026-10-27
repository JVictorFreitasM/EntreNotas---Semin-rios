# EntreNotas — avaliação anônima de seminários

Aplicação PHP 8.1+ / MySQL 5.7+ ou MariaDB 10.3+, com PDO MySQL e InnoDB. Não requer Composer, Node.js, Docker ou processos permanentes na hospedagem. **O arquivo de entrada é `index.php`, na raiz**, e o script SQL está em `schema.sql`.

## Publicação no InfinityFree

1. No painel InfinityFree, crie seu site e um banco MySQL. Anote o **hostname MySQL indicado no painel**, nome completo do banco, usuário e senha. Não use `localhost` para o banco hospedado.
2. Abra o phpMyAdmin do painel, selecione o banco criado e importe `schema.sql` na aba **Importar**. Execute apenas em banco vazio. Para o InfinityFree, remova do início do script o bloco `CREATE DATABASE ...;` e a linha `USE entrenotas;`, que são destinados ao ambiente local. Selecione no phpMyAdmin o banco criado pelo painel antes de importar as tabelas.
3. Copie `config.example.php` para `config.php`. Preencha os dados reais do banco. Gere **dois segredos diferentes**, com ao menos 32 bytes aleatórios, e use um em `app_key` e outro em `install_token`. Exemplo local: `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` (execute duas vezes). Não use a senha do banco como segredo.
4. Envie por FTP para a pasta **htdocs** do domínio: `index.php`, `config.php`, `.htaccess`, e as pastas `app/` e `assets/` completas. Inclua os arquivos ocultos `.htaccess` dentro de `app/`. O `index.php` deve estar **diretamente dentro de htdocs**, e não em uma subpasta do projeto. Remova/renomeie a página de exemplo `index.html` da hospedagem se ela existir. Não envie OS.md, testes, SQL, documentação ou arquivos .git: importe o SQL via phpMyAdmin.
5. Ative e aguarde a emissão do SSL pelo painel e entre pelo endereço **https://SEU-DOMINIO/**. Mantenha `require_https => true` em produção. O aplicativo recusa HTTP; acesse a URL HTTPS diretamente.
6. Acesse `https://SEU-DOMINIO/index.php?view=install`. Digite o token de instalação do config.php, escolha um usuário administrativo e uma senha forte (10 a 72 bytes). Não há senha padrão. Não coloque o token na URL ou compartilhe o config.php.
7. Após criar o administrador, a instalação fica travada no banco (`system_state.installed`) e a rota passa a mostrar o login administrativo. Remova o valor de `install_token` do config.php, deixando uma string vazia. Mesmo com o token original, nenhuma outra conta administrativa pode ser criada pela instalação.
8. Acesse a página inicial para criar as contas dos alunos. Ao apresentar, cada aluno fornece seu código privado ao administrador, que preenche nome real, tema e código. Inicie o seminário, abra os votos quando desejar, feche e encerre.
9. Confira em uma janela anônima que `/app/bootstrap.php` retorna **403**, que `/config.php` não exibe conteúdo e que a listagem de diretórios está desativada. Nunca entregue a senha do banco aos alunos. Não habilite exibição pública de erros PHP. Se o servidor reportar erro 500 relacionado a `Options -Indexes`, retire somente essa linha e desative a listagem pelo painel.

A hospedagem deve executar PHP 8.1 ou mais recente e ter PDO MySQL habilitado. Confirme a versão no painel. Não é necessário redirecionamento de rotas ou tarefas agendadas. O polling usa o próprio `index.php?api=1` e cookies de sessão da mesma origem. Restrições de acesso externo da hospedagem podem impedir testes automatizados remotos: faça também a conferência manual no domínio.

## Configuração local

1. Instale PHP 8.1+ com a extensão `pdo_mysql`, e MySQL/MariaDB com InnoDB.
2. Importe `schema.sql` pelo HeidiSQL/phpMyAdmin ou execute `mysql -u USUARIO -p < schema.sql`. O script cria e seleciona o banco local `entrenotas`; a conta MySQL usada precisa ter permissão para criá-lo. Se preferir outro nome, ajuste os comandos iniciais e o `db_name` no config.php.
3. Crie `config.php` a partir do exemplo; use as credenciais locais, dois segredos aleatórios e `require_https => false` **somente para desenvolvimento local**.
4. Na raiz do projeto, rode `php -S 127.0.0.1:8080` e abra `http://127.0.0.1:8080/index.php?view=install` para criar o administrador. O servidor embutido é apenas para desenvolvimento; ele não aplica `.htaccess`, portanto não o exponha à rede.
5. Para verificar sintaxe: `find . -name '*.php' -print0 | xargs -0 -n1 php -l`. Para teste integrado, consulte `tests/README.md`.

É possível colocar a configuração fora da pasta pública usando a variável de ambiente `SEMINAR_CONFIG` com caminho absoluto. Se o plano de hospedagem não permitir isso, mantenha o config.php em htdocs, com a proteção `.htaccess` fornecida e sem credenciais em arquivos de texto, cópias `.bak` ou repositórios. config.example.php é apenas um modelo sem segredos. `.gitignore` exclui config.php; o SQL e os diretórios internos possuem proteção adicional quando enviados acidentalmente.

## Operação e regras

- Os alunos recebem um nome fixo `Aluno 001`, etc., exibido somente ao dono da conta, e um código aleatório de quatro dígitos (incluindo zeros à esquerda), único no banco. O usuário de login não deve conter dados pessoais.
- A lista administrativa mostra credenciais de login e status de bloqueio; não retorna nome anônimo, código privado, ID do apresentador ou associação com nomes reais. Vínculos só são feitos com o código fornecido pelo próprio aluno. IDs internos não são mostrados nessa lista.
- Uma apresentação inicia com votação fechada. Abra, feche, reabra ou encerre pelos botões. Para reabrir uma apresentação encerrada, encerre primeiro qualquer outra ativa. Reabrir não apaga votos e não permite votar de novo.
- Notas aceitam vírgula ou ponto e no máximo uma casa decimal. Não há edição ou exclusão. Médias são calculadas com `AVG`, com duas casas; ausência de votos não vira zero.
- Alunos consultam somente suas próprias apresentações encerradas. Administradores veem notas ordenadas numericamente, sem sequência de envio ou identificação. Não há endpoint público de resultados.
- Atualização via fetch a cada 10 segundos, ao abrir a tela ou retornar à aba. O campo de nota fica fora da região reconstruída e só é limpo quando muda a apresentação. Os campos de cadastro administrativo também são preservados.
- O bloqueio por cookie persistente dificulta cadastros repetidos; limpar dados ou usar outro dispositivo/navegador contorna a medida. Não garante uma conta por celular. Administradores podem bloquear/desbloquear contas. Sessões existentes de contas bloqueadas deixam de ter acesso ao consultar ou agir.
- Bloqueios temporários de tentativas duram 15 minutos: login por conta (10), login por IP (40), cadastro por IP (10), instalação por IP (10), tentativas de código por administrador (15). Os identificadores dos limites são HMACs e não acompanham notas. A turma em uma única rede pode atingir o limite de cadastro: aguarde a janela ou escalone os cadastros. Configure o endereço remoto real no servidor; não confiamos em cabeçalhos encaminhados fornecidos pelo cliente.
- Não há recuperação automática de senha na primeira versão. Guarde as credenciais. Um operador autorizado com acesso ao banco pode redefinir a senha usando um novo `password_hash`, preservando o ID e os vínculos. Não apague contas com apresentações/votos. Faça backup seguro antes de intervenções.

## Segurança e limite do anonimato

Senhas usam password_hash/password_verify; consultas são preparadas; alterações são POST com CSRF; HTML e JS usam escape/textContent. Sessões são HttpOnly/SameSite, Secure em HTTPS, com regeneração ao autenticar e sair. Configuração e erros não são devolvidos ao navegador. O instalador bloqueia a linha singleton do banco, tornando a criação inicial atômica e de uso único.

`participation` registra apenas apresentação e conta; `grades` registra apenas apresentação, ID aleatório e nota. Nenhuma delas armazena horário, IP ou ordem sequencial de envio. A mesma transação insere ambos os registros, com chave primária composta contra duplicidade. Não existe relação por ID entre os dois registros. Todas as operações de controle e voto travam primeiro a mesma linha `system_state FOR UPDATE`, depois conferem novamente o estado e a conta. Se fechar obtiver o bloqueio primeiro, o voto é recusado. Se o voto obtiver primeiro, ele conclui antes do fechamento. Não há voto aceito no estado fechado. Bloqueio de conta também usa esse protocolo.

A separação impede identificação direta pelos painéis e relatórios da aplicação, **não garante anonimato absoluto** contra acesso privilegiado ao servidor, banco, logs transacionais/backups ou tráfego de rede. Em grupos pequenos, observar o aumento da contagem e as notas parciais pode permitir inferências. O administrador conhece o nome real do apresentador e pode já conhecer seu usuário por circunstâncias externas. Não se deve registrar corpos de POST, notas ou códigos em ferramentas de logs/analytics. Use HTTPS e restrinja o acesso ao painel da hospedagem e ao phpMyAdmin.

## Validação desta entrega

Validado localmente com PHP 8.2.4 e MariaDB 10.4.28:

- Sintaxe de todos os arquivos PHP e `node --check assets/app.js`: aprovada.
- Suíte integrada: **38 verificações aprovadas**, incluindo fechamento concorrente com envio de voto e voto duplicado simultâneo.
- Testes HTTP: página inicial, formulários, cabeçalhos de segurança, API sem autenticação recusada, login/logout administrativo e login/sessão de aluno aprovados.

A suíte não substitui a conferência visual em celular e os testes na hospedagem de destino. O código versionado não contém credenciais reais nem uma conta administrativa pré-criada.

Referências da hospedagem: [migração e importação de banco](https://forum.infinityfree.com/t/how-to-migrate-your-website-to-infinityfree/49315), [hostname MySQL](https://forum.infinityfree.com/t/how-to-connect-with-mysql/49340) e [pasta htdocs / arquivo index](https://forum.infinityfree.com/t/getting-403-even-after-uploading-my-project-files-in-htdocs/110314).

## Códigos de quatro dígitos

O código privado usa quatro dígitos, de `0000` a `9999`. Ele serve apenas para vincular apresentações e não substitui a senha. O espaço menor torna o código mais fácil de adivinhar; a consulta continua restrita ao administrador e limitada por tentativas. Não compartilhe publicamente.

Em bancos antigos, execute uma vez `php tools/migrate-codes.php` na raiz do projeto. A migração troca somente os códigos antigos, preservando contas, senhas, apresentações e votos. Peça aos alunos que consultem novamente seu código após a migração. Não é necessário reimportar o SQL ou apagar o banco. O script é exclusivo de linha de comando.

## Publicação automática pelo GitHub

O workflow `.github/workflows/deploy.yml` publica alterações da branch `main` por FTPS. Ele é executado quando mudam `index.php`, `.htaccess`, `app/`, `assets/` ou o próprio workflow. Alterações somente na documentação não disparam publicação. Também é possível executá-lo manualmente pela aba **Actions → Publicar no InfinityFree → Run workflow**.

### Configurar uma vez

1. Mantenha no servidor o `config.php` de produção, o banco importado e o administrador já instalado. O workflow não cria esses itens nem executa SQL.
2. No painel InfinityFree, abra os dados FTP da conta que hospeda o domínio. Use o servidor, usuário e senha **FTP**, e não o hostname/senha do banco MySQL.
3. No repositório GitHub, entre em **Settings → Secrets and variables → Actions → New repository secret** e cadastre:

   | Secret | Valor |
   | --- | --- |
   | `FTP_SERVER` | Hostname FTP informado pelo painel, sem `ftp://` |
   | `FTP_USERNAME` | Usuário FTP da hospedagem |
   | `FTP_PASSWORD` | Senha FTP da hospedagem |
   | `FTP_SERVER_DIR` | Caminho FTP da pasta pública do domínio, terminando em `/` |

4. Confirme o caminho no gerenciador de arquivos ou em um cliente FTP. Ele pode ser `/htdocs/` ou `/seminarios.freehosting.dev/htdocs/`, dependendo da organização da conta. Use **o caminho real do seu domínio**; não copie o exemplo sem verificar.
5. Execute o workflow manualmente em **Actions** e acompanhe os logs. Depois da configuração, pushes que alteram os arquivos da aplicação publicam automaticamente.

Somente `index.php`, `.htaccess`, `app/` e `assets/` entram no pacote de envio. `config.php`, SQL, testes, scripts de migração, documentação e prints ficam de fora. A limpeza completa do destino está desativada: arquivos existentes fora do controle dessa automação são preservados, incluindo a configuração de produção. Os arquivos que a automação acompanha podem ser atualizados ou removidos conforme mudanças no Git. Não exclua o arquivo de estado `.entrenotas-deploy-state.json` entre publicações.

A conexão está configurada para FTPS explícito na porta 21. Se houver erro de conexão, confira os dados FTP e a disponibilidade de FTPS no painel; não coloque a senha em arquivos do repositório ou no chat. A primeira execução só poderá ser validada após cadastrar os Secrets corretos.
