# EntreNotas — Seminários

Sistema web responsivo para avaliação de seminários em sala de aula. Os alunos atribuem notas de **0 a 5** aos colegas, enquanto o administrador organiza as apresentações e controla quando a votação fica aberta.

A média dos alunos vale **até 5 pontos**. O professor registra sua nota, também de até 5 pontos, no painel administrativo. O sistema soma as duas partes e mostra a nota total de até 10 pontos ao administrador e ao aluno responsável, após o encerramento. Por exemplo: média da turma **4,20** + professor **4,50** = **8,70**.

As notas são exibidas sem identificar os avaliadores. Cada aluno acompanha apenas os resultados das suas próprias apresentações encerradas.

[**Acessar o sistema**](https://seminarios.freehosting.dev/)

## Print do sistema

![Painel administrativo do EntreNotas](./EntreNotas-·-Seminários-10-27-2026_11_12_AM.png)

## Funcionalidades

- **Área do aluno:** cadastro e login, nome anônimo fixo, código privado de quatro dígitos com opção de copiar e consulta das próprias médias.
- **Área administrativa:** cadastro de seminários por nome real, tema e código privado; gerenciamento de contas com bloqueio e desbloqueio.
- **Controle da votação:** iniciar com votação fechada, abrir, fechar, reabrir e encerrar, com apenas uma apresentação ativa por vez.
- **Voto único:** uma avaliação por conta em cada apresentação, sem autoavaliação, alteração ou exclusão da nota enviada.
- **Nota do professor:** lançamento e atualização de 0 a 5 pelo administrador, com detalhamento da média da turma, nota do professor e soma final.
- **Resultados automáticos:** notas com uma casa decimal e médias com duas casas, preservadas ao reabrir votações.
- **Atualização a cada 10 segundos:** acompanhamento sem recarregar a página inteira nem apagar o formulário em preenchimento.
- **Interface responsiva:** paleta azul e layout para celulares e computadores.

## Tecnologias

| Camada | Tecnologia |
| --- | --- |
| Backend | PHP 8.1+ com PDO MySQL |
| Banco de dados | MySQL 5.7+ ou MariaDB 10.3+, com InnoDB |
| Frontend | HTML, CSS e JavaScript |
| Hospedagem | Compatível com InfinityFree e hospedagens PHP/MySQL |

Não requer Node.js, Composer, Docker ou processos permanentes no servidor.

## Executar localmente

1. Importe `schema.sql` em um MySQL/MariaDB local. O script cria e seleciona o banco `entrenotas`.
2. Copie `config.example.php` para `config.php` e configure a conexão. O arquivo real de configuração é ignorado pelo Git.
3. Gere dois segredos diferentes para `app_key` e `install_token`, executando duas vezes:

   ```bash
   php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
   ```

4. Use `require_https => false` somente no ambiente local e inicie na raiz:

   ```bash
   php -S 127.0.0.1:8080
   ```

5. Acesse `http://127.0.0.1:8080/index.php?view=install`, informe o token e crie o primeiro administrador. Depois, deixe `install_token` vazio no `config.php`.
6. Entre em `http://127.0.0.1:8080/`. Para testar administrador e alunos ao mesmo tempo, use perfis de navegador diferentes.

No XAMPP Linux, substitua `php` por `/opt/lampp/bin/php` se o comando não estiver no PATH.

## Publicar no InfinityFree

O **`index.php` fica na raiz** e deve ser enviado diretamente para a pasta `htdocs`, junto de `app/`, `assets/`, `.htaccess` e do seu `config.php` configurado para produção.

Crie o banco pelo painel da hospedagem. Antes de importar `schema.sql`, retire o bloco inicial `CREATE DATABASE ...;` e a linha `USE entrenotas;` e selecione o banco correto no phpMyAdmin. Use o hostname MySQL e o nome completo fornecidos pelo painel, mantenha `require_https => true` e crie o administrador pela rota `?view=install`.

Consulte o [guia completo de instalação e operação](docs/INSTALACAO.md) para configuração, publicação, migração dos códigos antigos e solução de problemas.

## Segurança e anonimato

As senhas usam `password_hash` e `password_verify`. O backend utiliza consultas preparadas, proteção CSRF, escape de conteúdo, sessões protegidas e limites de tentativas. O instalador permite criar somente o administrador inicial.

O controle de participação e as notas ficam em tabelas separadas: o registro da nota não contém o identificador do avaliador. Transações e bloqueios no banco mantêm o voto único e a consistência quando alguém vota enquanto o administrador fecha a votação.

O anonimato **não é absoluto** contra acesso privilegiado ao banco, servidor, logs ou observação da atividade da turma. O código de quatro dígitos serve apenas para vincular apresentações; não substitui a senha. O bloqueio de cadastros por navegador é básico e pode ser contornado ao limpar os dados ou usar outro dispositivo.

## Estrutura

```text
index.php             Entrada da aplicação e telas
schema.sql            Tabelas e índices do banco
config.example.php    Modelo sem credenciais reais
app/                  Configuração, autenticação e regras de negócio
assets/               Estilos e JavaScript
docs/                 Instalação e operação
tests/                Testes de integração e roteiro manual
tools/                Migração dos códigos antigos
```

## Testes

A suíte integrada cobre cadastro, autenticação, autorização, vínculo por código, bloqueio de contas, validação de notas, autoavaliação, médias, reabertura e concorrência real entre processos PHP.

Foram aprovadas **64 verificações**, incluindo voto duplicado simultâneo e fechamento concorrente com envio de voto. Veja [como executar os testes](tests/README.md). Use sempre um banco de testes separado e vazio.

## Deploy automático

O repositório inclui um workflow de GitHub Actions que envia os arquivos da aplicação ao InfinityFree por FTPS após alterações na `main`, preservando o `config.php` da hospedagem. Configure os Secrets `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` e `FTP_SERVER_DIR` conforme o [guia de publicação automática](docs/INSTALACAO.md#publicação-automática-pelo-github).
