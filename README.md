# Harpia Survey

[![Moodle](https://img.shields.io/badge/Moodle-5.1+-F98012?style=for-the-badge&logo=moodle)](https://moodle.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue?style=for-the-badge)](https://www.gnu.org/licenses/gpl-3.0.html)

## Visão Geral

O módulo Harpia Survey permite que pesquisadores criem experimentos para estudar LLMs (Large Language Models), interajam com eles através de APIs e coletem respostas de pesquisas de qualidade. O módulo oferece uma interface completa para gerenciar experimentos, modelos de IA, páginas de questionários e conversas com IA.

## Requisitos

- **Moodle**: 5.1 ou superior (requisito mínimo: 2024052700)
- **PHP**: 8.2 ou superior
- **Banco de dados**: MySQL 8.4+, MariaDB 10.11+, ou PostgreSQL 15+
- **Extensões PHP**: 
  - `curl` (para comunicação com APIs de LLM)
  - `json` (para processamento de dados JSON)
  - Extensões padrão do Moodle

## Instalação

1. Clone ou copie o diretório `harpiasurvey` para `moodle/mod/harpiasurvey`
2. Acesse o painel administrativo do Moodle
3. Navegue até **Site administration > Notifications**
4. O Moodle detectará automaticamente o novo módulo e executará a instalação
5. Siga as instruções na tela para completar a instalação

Alternativamente, via CLI:

```bash
php admin/cli/upgrade.php
```

## Configuração

O módulo não requer variáveis de ambiente adicionais. Todas as configurações são feitas através da interface do Moodle:

1. **Registrar Modelos de LLM**: 
   - Acesse a atividade Harpia Survey
   - Clique em "Register model"
   - Configure o nome, identificador do modelo, URL base da API, chave de API e campos extras (JSON)

2. **Criar Experimentos**:
   - Dentro da atividade, clique em "Create experiment"
   - Configure nome, tipo, descrição, datas de disponibilidade e modelos associados

3. **Gerenciar Páginas e Questões**:
   - Adicione páginas ao experimento (abertura, demográficos, interação, feedback)
   - Crie questões reutilizáveis no banco de questões
   - Associe questões às páginas

## Execução

Após a instalação, o módulo estará disponível como uma atividade de curso:

1. **Adicionar ao Curso**:
   - Edite o curso onde deseja adicionar a atividade
   - Clique em "Add an activity or resource"
   - Selecione "Harpia Survey"
   - Configure o nome e descrição da atividade

2. **Gerenciar Experimentos**:
   - Acesse a atividade criada
   - Use o menu de gerenciamento para criar e configurar experimentos
   - Publique experimentos para torná-los disponíveis aos estudantes

3. **Participação dos Estudantes**:
   - Estudantes acessam a atividade e podem participar dos experimentos publicados
   - Podem interagir com modelos de IA através de conversas
   - Respondem questões de pesquisa associadas aos experimentos

## Testes

Para executar testes PHPUnit (se disponíveis):

```bash
vendor/bin/phpunit mod/harpiasurvey/tests/
```

Para executar testes Behat (se disponíveis):

```bash
vendor/bin/behat --tags @mod_harpiasurvey
```

## Estrutura do Projeto

```
mod/harpiasurvey/
├── amd/                    # JavaScript AMD modules
│   ├── src/               # Código fonte JavaScript
│   └── build/             # Arquivos JavaScript compilados/minificados
├── classes/               # Classes PHP organizadas por namespace
│   ├── event/             # Eventos do Moodle
│   ├── forms/             # Formulários Moodle (experiment, model, page, question)
│   ├── llm_service.php    # Serviço para comunicação com APIs de LLM
│   └── output/            # Renderizadores e visualizações
├── cli/                   # Scripts de linha de comando
│   └── clear_data.php     # Utilitário para limpar dados
├── db/                    # Definições de banco de dados
│   ├── access.php         # Definições de capabilities
│   ├── install.xml        # Schema do banco de dados
│   └── upgrade.php        # Scripts de atualização
├── lang/                  # Arquivos de idioma
│   ├── en/                # Inglês
│   └── pt_br/             # Português (Brasil)
├── templates/             # Templates Mustache
│   ├── chat_ai_message.mustache
│   ├── chat_user_message.mustache
│   ├── experiment_view.mustache
│   └── ...
├── ajax.php               # Endpoint para requisições AJAX
├── lib.php                # Funções principais do módulo
├── mod_form.php           # Formulário de criação/edição da atividade
├── version.php            # Versão e metadados do plugin
├── view.php               # Página principal de visualização
└── styles.css             # Estilos CSS do módulo
```

## Funcionalidades Principais

- **Gerenciamento de Modelos de LLM**: Registro e configuração de múltiplos modelos de IA com suporte a diferentes APIs
- **Criação de Experimentos**: Sistema completo para criar e gerenciar experimentos de pesquisa
- **Tipos de Páginas**: Suporte a diferentes tipos de páginas (abertura, demográficos, interação, feedback, chat com IA)
- **Banco de Questões**: Sistema de questões reutilizáveis com múltiplos tipos (escolha única, múltipla escolha, Likert, número, texto, conversa com IA)
- **Conversas com IA**: Interface de chat integrada para interação com modelos de LLM
- **Estatísticas**: Visualização de respostas e conversas coletadas
- **Validação Cruzada**: Suporte a experimentos com validação cruzada
- **Controle de Participantes**: Limite de participantes e controle de disponibilidade por data

## Capabilities

- `mod/harpiasurvey:addinstance` - Adicionar uma nova instância de Harpia Survey
- `mod/harpiasurvey:view` - Visualizar atividades Harpia Survey
- `mod/harpiasurvey:manageexperiments` - Gerenciar experimentos

## Suporte

Para questões, bugs ou sugestões, consulte a documentação do Moodle ou entre em contato com a equipe de desenvolvimento.

## Licença

Este módulo é licenciado sob a GNU GPL v3 ou posterior. Veja o arquivo LICENSE para mais detalhes.

