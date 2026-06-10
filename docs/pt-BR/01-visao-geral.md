# 01 — Visão Geral

## 🎯 Filosofia do tema

O **ReloadeD** é um tema WordPress construído com 4 princípios não-negociáveis:

### 1. Zero plugin

Todos os recursos são **nativos do tema**. Nada de:
- ❌ ACF / Custom Fields plugin
- ❌ Yoast SEO / RankMath
- ❌ Cookie consent plugins
- ❌ Markdown plugins (usamos Parsedown empacotado)
- ❌ Cache plugins (cabe ao usuário escolher se quer um)

✅ Os recursos vivem em `inc/mod-*.php` e podem ser ligados/desligados via painel.

### 2. Performance acima de tudo

- **Fontes locais** (sem chamadas externas a Google Fonts em runtime)
- **Facades** pra YouTube/Discord (substitui iframes pesados por imagem clicável)
- **`font-display: swap`** em todos os `@font-face`
- **JS vanilla** (sem jQuery no frontend, exceto no painel admin)
- **Lazy loading** automático em `<img>` (suporte HTML5 nativo via `loading="lazy"`)
- **Subset Latin + Latin Extended** nas fontes (cobre PT-BR sem peso desnecessário)

### 3. Dark / Light mode "first-class"

- Toggle via botão sun/moon na barra de menu
- Cascata de decisão: localStorage do usuário → preferência do admin → preferência do SO
- Script anti-FOUC inline no `<head>` (zero flash entre temas)
- Hierarquia de cores via CSS Custom Properties (`--rd-bg`, `--rd-text`, `--rd-glass-shadow`, etc.) que adaptam automaticamente

### 4. Internacionalização nativa

- Todas as strings de UI passam por `__()`, `_e()`, `esc_html__()` etc.
- Text domain: `'reloaded'`
- Source language: `en-US`
- Tradução pt_BR mantida em `languages/pt_BR.po` (compilada pra `pt_BR.mo`)
- Suporte a múltiplos idiomas via WP standard

## 📦 O que está incluído

### Recursos de conteúdo
- ⭐ **Markdown** em posts via biblioteca embarcada **Parsedown** (`lib/Parsedown.php`)
- 🎨 **Syntax highlighting** com Prism.js (vendored em `lib/prism.js`)
- 🎬 **Facades de iframe** pra YouTube e Discord (carrega só quando usuário clica)
- 👁️ **Sistema de views por post** com deduplicação por IP, filtro de bots, janelas temporais (dia/semana/mês/ano)
- 🔍 **Busca avançada** com 4 layouts simultâneos (Grid, Vertical, Compact, Google) e redistribuição via AJAX
- 🏷️ **Categoria principal** opcional (resolve destaque ambíguo no menu quando post tem várias categorias)
- 🎨 **Cor por categoria** configurável na edição da categoria, com contraste de texto auto-calculado (YIQ) — chip nos cards do grid + kicker no single
- 📰 **Overline (chapéu)** por post — texto livre estilo jornalismo (ENTREVISTA, ANÁLISE) que aparece acima do título no single e nos cards
- 📂 **Header de archive contextual** — ícone, contador de posts e borda colorida com a cor da categoria

### Recursos de estrutura
- 🍔 **Menu hambúrguer** em ≤1024px (mesmo breakpoint em que o sidebar empilha) com busca integrada no painel
- 📰 **News ticker** rolante na top bar (mostra últimas notícias)
- 🌐 **Top bar** com data, redes sociais e ticker
- 💬 **Widget Discord** pra comunidade (com facade ou iframe direto)
- 💝 **Sistema de doações** integrado (PIX, PayPal, GitHub Sponsors)
- 🔥 **Widget "Most Read"** — primeiro `WP_Widget` nativo do tema, lista posts mais visualizados por janela (semana/mês/ano/all-time), 3-15 itens, thumb 16/9 + título + views
- 📄 **Page templates nomeados** (`page-templates/`) — `template-legal.php` (Privacy/Terms), `template-contact.php`, `template-about.php` (hero do autor + Schema.org Person). Atribuíveis a qualquer página via Atributos → Modelo (slug-agnostic, padrão de tema distribuível)
- 👤 **Multi-redator ready** — `author.php` evoluído com Schema.org Person + ícones sociais por user (campos no perfil via filter `user_contactmethods`). Cada redator novo ganha `/author/{slug}/` automaticamente sem configuração extra

### Recursos de admin
- 🎛️ **Painel próprio** (não usa Customizer do WP) com 10 abas organizadas
- 🛠️ **Modo manutenção** com tela "Voltaremos em breve" + senha de acesso pra dev
- 🔐 **Headers de segurança HTTP** opcionais (X-Frame-Options, Permissions-Policy, etc.)
- 🔄 **Sidebar configurável esquerda/direita** via toggle no painel
- 🚨 **Set completo de páginas de erro HTTP** (403, 404, 500, 503) com identidade visual unificada

### SEO técnico (zero plugin)
- 🌐 **Open Graph** completo (singular) + **Twitter Cards** com `summary_large_image`
- 🔗 **Canonical URLs** em todas as superfícies indexáveis (home, archives, autor, datas, busca, paginação)
- 📝 **Meta description** automática por contexto (excerpt, tagline, descrição do termo/autor, archive de data)
- 📊 **Schema.org JSON-LD**: Article (singles, com `Person` `@id` matching) + WebSite com SearchAction (home) + BreadcrumbList + Person standalone em `author.php` e `template-about.php` (alimenta E-E-A-T do Google)
- 🍞 **Breadcrumbs** visuais com toggle no painel (trilha contextual Home › Categoria › Post)

### LGPD / Compliance
- 🍪 **Banner de cookies com consent granular** (3 categorias: necessários/estatísticas/marketing) — LGPD-compliant, banner expansível inline, link de reabrir no footer
- 🛡️ **Gating automático** de scripts de tracking baseado no consent (GA, Clarity, Plausible, Umami, Facebook Pixel, TikTok Pixel)
- 🤖 Filtro automático de bots no contador de views

### Integrações de tracking (todas opcionais)
- 📊 **Google Analytics 4** (GA Tag ID)
- 🔥 **Microsoft Clarity** — heatmaps + session recordings grátis
- 🌱 **Plausible / Umami** — alternativas privacy-friendly ao GA
- 🎯 **Facebook Pixel** + **TikTok Pixel** — retargeting/conversões
- ✅ **Google Search Console** + **Bing Webmaster** — verificação de propriedade via meta tag

### Performance avançada (Wave 5)
- ⚡ **Preload de fontes críticas** — Inter Regular + Poppins Bold no `<head>`
- 🌐 **DNS Prefetch / Preconnect** condicional pra YouTube, Discord, GA, Gravatar
- 💤 **Lazy load de iframes** (Discord + dinâmicos do facade)
- 🗄️ **Limite de revisões de posts** configurável
- 💓 **Heartbeat API** otimizada (admin: 120s; frontend: deregister)
- 🗜️ **CSS minificado** via Live Sass Compiler (source maps preservados)
- 🖼️ **WebP/AVIF on-upload** — gera automaticamente versões next-gen de cada JPEG/PNG (todos os tamanhos), entrega via `<picture>` com fallback transparente. Detecção runtime de capability do servidor (Imagick + GD com WebP/AVIF). Botão de regeneração em chunks pra Media Library existente

## 💻 Requisitos

| Componente | Mínimo | Recomendado |
|------------|--------|-------------|
| **WordPress** | 6.0+ | 6.5+ |
| **PHP** | 8.0 | 8.2+ |
| **Navegador (frontend)** | Chrome 122+, Firefox 119+, Safari 17.4+, Edge 122+ | latest |

> O tema usa CSS moderno (`color-scheme`, `aspect-ratio`, `color-mix()`, `stretch` em sizing). Em navegadores muito antigos, alguns refinamentos visuais podem cair pra fallbacks.

## 📥 Instalação

### Opção 1 — via Admin do WordPress (mais fácil)

1. Baixe o ZIP da versão estável mais recente em [Releases do GitHub](https://github.com/Finallf/theme-reloaded/releases)
2. No admin: **Aparência → Temas → Adicionar novo → Enviar Tema**
3. Selecione o ZIP e clique em "Instalar agora"
4. Após instalado, clique em **Ativar**

### Opção 2 — via FTP/SFTP

1. Descompacte o ZIP
2. Envie a pasta `reloaded/` pra `wp-content/themes/`
3. No admin: **Aparência → Temas → Ativar** no card do ReloadeD

### Opção 3 — via Git (pra desenvolvimento)

```bash
cd wp-content/themes/
git clone -b master https://github.com/Finallf/theme-reloaded reloaded
```

> ⚠️ Use a branch `master` pra produção (releases estáveis). A `beta` recebe novidades antes mas pode ter bugs.

## ✅ Pós-instalação

Após ativar, você verá:

1. **Menu "ReloadeD"** na sidebar do admin (logo abaixo do Dashboard)
2. Todos os defaults já configurados (zero ação necessária pra começar)
3. As páginas de teste padrão do WP renderizam usando o tema imediatamente

### Configurações recomendadas (5 minutos)

1. **Aparência → Personalizar → Identidade do Site**: defina logo (ou deixe usar o título)
2. **Configurações → Geral**: confirme **Title** e **Tagline** (aparecem em meta tags + footer)
3. **ReloadeD → Geral**: ajuste preferências de Imagem Destacada, Top Bar, etc.
4. **ReloadeD → Doações**: adicione seus links de PIX/PayPal/GitHub Sponsors (se quiser)
5. **ReloadeD → Integrações**: adicione ID do Google Analytics se for usar

Pronto! O tema está pronto pra produção.

## 🔗 Próximos passos

- **[02 — Painel de Controle](02-painel-de-controle.md)**: detalhes de cada aba
- **[03 — Arquitetura do Código](03-arquitetura-do-codigo.md)**: pra desenvolvedores que vão estender o tema
