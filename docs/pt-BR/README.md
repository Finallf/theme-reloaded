# Documentação do Tema ReloadeD

Bem-vindo à documentação completa do tema **ReloadeD** — um tema WordPress customizado, focado em performance, com filosofia **zero-plugin** (todos os recursos vêm do próprio tema, sem dependências externas).

> Esta documentação está em **português brasileiro**. A tradução para o inglês será feita em uma fase futura.

## 📚 Índice

| Capítulo | Conteúdo |
|----------|----------|
| [01 — Visão Geral](01-visao-geral.md) | Filosofia do tema, instalação, ativação e primeiros passos |
| [02 — Painel de Controle](02-painel-de-controle.md) | Cada aba do painel admin (`Aparência → ReloadeD`) explicada opção por opção |
| [03 — Arquitetura do Código](03-arquitetura-do-codigo.md) | Estrutura de pastas, convenções, padrões adotados |
| [04 — Módulos PHP](04-modulos-php.md) | Cada arquivo `inc/mod-*.php` documentado: o que faz, por que existe, como hookar |
| [05 — Templates](05-templates.md) | Cada `.php` da raiz (`header.php`, `single.php`, etc.) explicado |
| [06 — Frontend SCSS + JS](06-frontend-scss-js.md) | Arquitetura do SCSS modular, sistema de variáveis, JS vanilla |
| [07 — Recursos Especiais](07-recursos-especiais.md) | Markdown, Prism.js, Maintenance, Views, Search com layouts, Facades |
| [08 — i18n e Traduções](08-i18n-e-traducoes.md) | Workflow de strings traduzíveis, Poedit, geração de `.pot`, compilação `.mo` |
| [09 — Desenvolvimento e CI/CD](09-desenvolvimento-e-ci.md) | Git workflow (master/beta), semantic-release, GitHub Actions, PHPCS + WPCS lint |
| [10 — Content Security Policy](10-content-security-policy.md) | Sistema CSP do tema — Report-Only + Enforce, endpoint REST, calibração e promoção |

## 🚀 Quick Start (versão TLDR)

1. Faça download do tema (zip ou clone)
2. Instale em `wp-content/themes/reloaded/` e ative no admin do WP
3. Acesse o menu **ReloadeD** na sidebar do admin (logo abaixo do Dashboard)
4. Configure as abas do painel conforme sua necessidade (a maioria já vem com defaults sensatos)
5. Escolha um logo via **Aparência → Personalizar → Identidade do Site** (ou deixe como texto, usando o `bloginfo('name')`)
6. Pronto! O tema já está funcionando com Markdown, Prism, Dark/Light Mode, busca avançada e todas as features.

## 📦 Versão atual

Veja [CHANGELOG.md](../../CHANGELOG.md) na raiz pra histórico completo de mudanças, ou a [Releases page](https://github.com/Finallf/theme-reloaded/releases) no GitHub.

## 🤝 Contribuindo

Veja [CONTRIBUTING.md](../../CONTRIBUTING.md) na raiz pra fluxo de Pull Requests, padrões de commit (Conventional Commits) e branches (`master` estável, `beta` em desenvolvimento).

## 📜 Licença

GNU GPL v2 ou superior (mesma licença do WordPress) — veja `style.css` no header do tema pra detalhes formais.
