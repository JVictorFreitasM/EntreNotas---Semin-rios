Ordem de Serviço — Sistema de Avaliação Anônima de Seminários

1. Objetivo

Desenvolver um sistema web responsivo para uma turma de aproximadamente 15 alunos avaliar seminários.

Cada aluno terá uma conta e poderá dar uma nota de 0 a 10 para a apresentação dos colegas. O administrador controlará as apresentações e decidirá quando abrir ou fechar a votação. Ao final, a média do apresentador será calculada automaticamente.

A identidade de quem atribuiu cada nota não deve ser exibida aos alunos nem ao administrador.

2. Tecnologias e hospedagem

O sistema deve ser compatível com a hospedagem gratuita InfinityFree, utilizando:

PHP no backend;

MySQL no banco de dados;

HTML, CSS e JavaScript no frontend.

A aplicação deve funcionar sem Node.js no servidor, Docker, WebSocket ou processos permanentes.

Evitar dependências que precisem ser instaladas diretamente no servidor. Caso alguma ferramenta seja usada durante o desenvolvimento, os arquivos enviados à hospedagem devem estar prontos para funcionamento.

A interface deve ser desenvolvida prioritariamente para celulares, sem prejudicar o uso em computadores.

3. Perfis de acesso

O sistema deve possuir dois tipos de acesso:

3.1. Aluno

O aluno pode:

Criar uma conta com nome de usuário e senha.

Entrar novamente na conta.

Visualizar seu nome anônimo e seu código privado de apresentação.

Acompanhar qual apresentação está ativa e o estado da votação.

Avaliar a apresentação de outro aluno, quando a votação estiver aberta.

Consultar a média das suas próprias apresentações encerradas.

O nome de usuário é uma credencial de acesso e não deve ser exibido aos outros alunos. A interface deve orientar o usuário a não escolher informações que o identifiquem no nome de usuário.

3.2. Administrador

O administrador deve entrar em uma área administrativa protegida por senha.

Ele pode:

Cadastrar e gerenciar apresentações.

Informar o nome real do apresentador e associá-lo à conta correspondente por meio do código privado.

Iniciar e encerrar apresentações.

Abrir, fechar e reabrir a votação de uma apresentação.

Visualizar a quantidade de votos, as notas recebidas e a média de cada apresentação.

Consultar os resultados de todos os apresentadores.

Visualizar a lista de contas criadas para apoiar o gerenciamento, sem exibir a associação entre o nome anônimo e o nome real do apresentador.

Importante: não exibir ao administrador qual conta atribuiu cada nota.

4. Cadastro e identificação dos alunos

4.1. Conta

O aluno deve criar uma conta com:

Nome de usuário único;

Senha.

Não solicitar nome real no cadastro.

O sistema deve informar que o nome de usuário serve para acesso à conta e deve ser diferente de informações que identifiquem o aluno.

4.2. Nome anônimo

Ao cadastrar a conta, o sistema deve gerar um nome anônimo fixo, por exemplo, Aluno 014.

Esse nome:

É exibido ao dono da conta.

Não pode ser alterado pelo aluno.

Não deve ser apresentado ao administrador junto do nome real vinculado à apresentação.

Não deve aparecer associado às notas dadas.

4.3. Código privado de apresentação

Cada conta deve receber um código privado, gerado aleatoriamente, único e difícil de adivinhar, por exemplo: K7M-92P.

Esse código deve:

Ser exibido somente ao aluno responsável pela conta.

Ser usado pelo administrador para vincular uma apresentação à conta do aluno.

Não ser listado no painel administrativo após a associação.

Não aparecer em telas de outros alunos ou em relatórios de resultados.

Permanecer vinculado à conta, salvo se houver uma funcionalidade administrativa segura de substituição.

O aluno informa o código ao administrador quando for apresentar.

4.4. Limitação de cadastro por navegador

Implementar uma marca local no navegador, por exemplo, por meio de cookie ou localStorage, para dificultar a criação de múltiplas contas no mesmo navegador e dispositivo.

A documentação e a interface administrativa devem deixar claro que essa medida é apenas um bloqueio básico: ela pode ser contornada ao limpar os dados, usar outro navegador ou usar outro dispositivo. O sistema não deve afirmar que garante um único cadastro por celular.

O administrador deve poder visualizar a lista de contas e bloquear ou desbloquear contas suspeitas. Contas bloqueadas não podem entrar nem votar.

5. Apresentações

5.1. Cadastro e vínculo

Ao cadastrar uma apresentação, o administrador deve informar:

Nome real do apresentador;

Código privado de apresentação fornecido pelo aluno;

Título ou tema do seminário.

O sistema deve validar o código e vincular a apresentação à conta correspondente.

A tela administrativa não deve revelar o nome anônimo ou o nome de usuário da conta vinculada. Para confirmar a operação, apresentar apenas uma mensagem como: “Código válido e apresentação vinculada.”

Se o código não existir, mostrar uma mensagem de erro sem revelar informações sobre outras contas.

5.2. Exibição para os alunos

Enquanto houver uma apresentação ativa, os alunos devem visualizar:

Nome real informado para o apresentador;

Título ou tema;

Estado da apresentação e da votação.

O apresentador deve ser identificado internamente pela conta vinculada, para impedir que vote na própria apresentação.

5.3. Estados e controle

O administrador deve poder:

Cadastrar a apresentação.

Iniciá-la com a votação fechada.

Abrir a votação quando desejar.

Fechar a votação.

Reabrir uma votação anteriormente fechada.

Encerrar a apresentação.

Selecionar uma apresentação anterior para reabrir sua votação, quando necessário.

Deve existir somente uma apresentação ativa por vez. Antes de ativar ou reabrir outra, o administrador deve encerrar a apresentação ativa.

Ao reabrir uma votação, os votos já registrados devem ser preservados, e os alunos que já votaram não podem votar novamente.

Diferenciar claramente os estados na interface:

Apresentação cadastrada;

Apresentação em andamento, com votação fechada;

Votação aberta;

Votação fechada;

Apresentação encerrada.

6. Votação

Cada aluno autenticado pode dar uma única nota de 0 a 10 por apresentação.

Regras:

Aceitar notas decimais, por exemplo, 7,5 e 8,0.

Padronizar a precisão das notas em uma casa decimal.

Não permitir notas fora do intervalo de 0 a 10.

Não permitir que o apresentador vote na própria apresentação.

Não permitir votos de contas bloqueadas.

Não permitir mais de um voto da mesma conta para a mesma apresentação.

Não permitir alterar ou excluir uma nota depois do envio.

Exibir uma mensagem de confirmação após o registro.

Não disponibilizar comentários nesta primeira versão.

Antes de salvar uma nota, o PHP deve verificar novamente no servidor:

Se o aluno está autenticado e sua conta está ativa.

Se a apresentação continua ativa.

Se a votação continua aberta.

Se o aluno não é o apresentador.

Se ele ainda não votou naquela apresentação.

Se a nota é válida.

Essas verificações devem ser feitas mesmo que a interface já tenha validado os dados. O fechamento da votação e o registro de votos devem ser tratados de forma consistente no banco de dados para evitar que uma nota seja aceita após o encerramento.

7. Anonimato e estrutura dos dados

O sistema precisa saber se uma conta já votou, mas essa informação não deve aparecer associada à nota no painel ou nos relatórios.

Implementar uma separação entre:

O controle de que uma conta já participou da votação;

As notas recebidas pela apresentação.

O registro de participação não deve conter a nota, e o registro da nota não deve conter o identificador da conta que votou. Utilizar transação para registrar a participação e a nota conjuntamente, garantindo a regra de um voto por conta.

Evitar armazenar horário individual, endereço IP ou outros dados diretamente associados às notas que possam facilitar a identificação do avaliador.

Limitação a documentar: a separação protege contra identificação direta pela aplicação, mas não garante anonimato absoluto contra alguém com acesso privilegiado ao servidor, ao banco, aos registros ou à atividade da rede. Em turmas pequenas, também pode haver identificação por circunstâncias externas.

O administrador poderá conhecer o nome real de quem apresentou e a média recebida, mas não deve ver qual conta deu cada nota nem qual nome anônimo está vinculado ao apresentador.

8. Médias e resultados

A média deve ser calculada pela fórmula:

Média = soma das notas recebidas ÷ quantidade de votos válidos.

Regras:

Exibir a média com duas casas decimais.

Quando não houver votos, mostrar “Ainda não há avaliações”.

Não contar notas inexistentes como zero.

Manter os votos já registrados ao fechar ou reabrir uma votação.

8.1. Visão do aluno

Cada aluno pode visualizar:

Suas próprias apresentações encerradas;

A média de cada uma;

A quantidade de avaliações recebidas.

Não mostrar aos alunos as médias dos colegas.

8.2. Visão do administrador

O administrador pode visualizar:

Nome real e tema da apresentação;

Estado atual;

Quantidade de votos;

Notas recebidas, sem identificação dos avaliadores;

Média parcial ou final.

A apresentação das notas não deve sugerir correspondência com a ordem de alunos ou de participação.

9. Atualização automática

Usar JavaScript no navegador para consultar o servidor PHP a cada 10 segundos, sem recarregar a página inteira.

A atualização deve ocorrer:

Na tela do aluno, para acompanhar a apresentação ativa e o estado da votação.

No painel administrativo, para acompanhar o estado da apresentação, a quantidade de votos e a média.

Imediatamente ao abrir a página ou voltar para uma aba que estava em segundo plano.

A atualização não deve interromper o preenchimento da nota nem apagar campos que o usuário esteja preenchendo.

Em caso de falha temporária de conexão, mostrar uma mensagem discreta e tentar novamente na próxima consulta.

10. Telas necessárias

10.1. Página inicial

Apresentar opções claras para:

Entrar como aluno;

Criar conta de aluno;

Entrar como administrador.

10.2. Área do aluno

Deve conter:

Nome anônimo;

Código privado de apresentação, com opção de copiar;

Apresentação ativa e estado da votação;

Formulário de nota quando permitido;

Mensagens de confirmação ou erro;

Resultados das próprias apresentações encerradas;

Opção de sair.

A interface deve orientar o aluno a não compartilhar seu código privado publicamente.

10.3. Área administrativa

Deve conter:

Cadastro e gerenciamento de apresentações;

Associação por nome real e código privado;

Controles para iniciar, abrir, fechar, reabrir e encerrar;

Painel de resultados;

Lista de contas e controles de bloqueio;

Opção de sair.

O painel não deve exibir o nome anônimo associado ao nome real nem a identidade dos avaliadores.

11. Segurança

Implementar, no mínimo:

Senhas armazenadas com password_hash e verificadas com password_verify.

Consultas preparadas para acesso ao banco.

Proteção contra CSRF nas operações que alteram dados.

Escape de conteúdo exibido nas páginas para reduzir riscos de XSS.

Sessões seguras, com renovação do identificador após login.

Controle de autorização no servidor para todas as ações administrativas.

Validação no servidor de todos os dados recebidos.

Credenciais do banco fora do código público versionado.

Proteção básica contra tentativas repetidas de login e de códigos de apresentação.

Uso de HTTPS na implantação.

A conta administrativa inicial deve ser criada por um processo de instalação seguro, sem senha padrão fixa. O instalador não pode continuar disponível para criar outra conta administrativa após a configuração inicial.

12. Instalação e documentação

Entregar:

Código-fonte completo;

Script SQL para criação das tabelas e dos índices necessários;

Arquivo de exemplo de configuração, sem credenciais reais;

Instruções para instalar localmente;

Instruções passo a passo para publicar no InfinityFree;

Procedimento para criar a conta administrativa inicial;

Orientações para configurar o banco de dados e proteger os arquivos sensíveis;

Explicação das limitações do bloqueio por navegador e do anonimato.

13. Critérios de aceitação

O projeto será considerado concluído quando:

Um aluno conseguir criar uma conta, entrar novamente e consultar seu código privado.

O administrador conseguir cadastrar uma apresentação usando o nome real e o código do aluno, sem visualizar o nome anônimo associado.

A apresentação puder ser iniciada com votação fechada.

O administrador conseguir abrir, fechar e reabrir a votação.

Apenas uma apresentação puder ficar ativa por vez.

Um aluno conseguir enviar somente uma nota válida por apresentação.

O apresentador não conseguir avaliar a própria apresentação.

Uma nota não for aceita pelo servidor após o fechamento da votação.

Os votos já registrados forem preservados quando a votação for reaberta.

O aluno conseguir ver somente suas próprias médias, e o administrador conseguir ver as médias de todos.

A identidade de quem votou não aparecer junto às notas ou nos resultados.

As telas atualizarem o estado automaticamente a cada 10 segundos, sem recarregar a página inteira.

O sistema funcionar em celulares e computadores.

A instalação puder ser realizada em hospedagem PHP/MySQL compatível com o InfinityFree.

14. Validação

Testar os principais fluxos de cadastro, login, vínculo da apresentação, abertura e fechamento da votação, envio de notas, prevenção de voto duplicado e cálculo da média.

Também testar a situação em que um aluno tenta enviar uma nota enquanto o administrador fecha a votação e verificar se o servidor respeita o estado efetivo da votação.