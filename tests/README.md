# Validação

O teste integrado usa o backend real e duas conexões concorrentes em processos PHP, sem frameworks. **Use somente um banco de testes vazio**, criado separadamente, e importe `schema.sql`. O teste cria contas, instalação e apresentações e deixa esses registros no banco para inspeção; não o execute em produção.

Crie um arquivo de configuração fora da pasta pública, como `/tmp/entrenotas-test-config.php`, usando o modelo config.example.php e as credenciais do banco de testes. Para teste local, `require_https` deve ser false e ambos os segredos devem ser aleatórios (mínimo 32 caracteres). Opcionalmente use `db_socket` para um banco local com socket Unix.

Execute:

```sh
SEMINAR_CONFIG=/tmp/entrenotas-test-config.php php tests/integration.php
```

O PHP CLI precisa das extensões PDO/MySQL e acesso a `proc_open`. A suíte verifica cadastro, autenticação, CSRF, bloqueio por navegador, instalador de uso único, vínculo por código, autorização, uma apresentação ativa, notas inválidas, autoavaliação, duplicidade, médias, fechamento, reabertura e bloqueio de contas. Uma transação mantém o bloqueio de fechamento enquanto outro processo tenta votar. Dois processos também tentam votar pela mesma conta.

Para validação visual/manual no domínio publicado:

1. Crie duas contas em perfis de navegador diferentes. Confira nome anônimo, código privado e cópia. Saia e entre de novo.
2. Em outro perfil, entre como administrador, cadastre uma apresentação com o código da primeira conta. Confira que nenhuma confirmação, resultado ou lista revela a associação com o usuário ou nome anônimo.
3. Inicie com votação fechada; abra. A segunda conta deve acompanhar a alteração em até 10 segundos e conseguir votar 4,2. A primeira não pode avaliar a própria apresentação.
4. Tente duplicar, enviar 5,1, 6, 10, -1 ou 3,25, e enviar via POST sem CSRF. As tentativas devem ser recusadas.
5. Feche e reabra. A quantidade de votos não muda e quem já votou continua impedido. Encerre: só o apresentador vê sua média; os outros não têm acesso a ela.
6. Tente iniciar duas apresentações e reabrir a anterior com outra ativa. O servidor deve impedir.
7. Bloqueie uma conta já autenticada: ela deve perder o acesso e não poder votar. Desbloqueie e entre novamente.
8. Preencha metade da nota e espere a atualização automática, ou retorne à aba. O conteúdo deve permanecer. Faça o mesmo no cadastro administrativo. Desconecte a rede: uma mensagem discreta deve aparecer e a consulta deve retornar após a conexão voltar.
9. Confira em celular (largura de 360px) e computador, navegação por teclado, foco e contraste. Teste a cópia do código em HTTPS e o fallback de seleção manual.
10. Após instalar, tente novamente `?view=install`: deve mostrar login e nunca criar outro administrador. Confira os bloqueios HTTP dos arquivos sensíveis.
