# 🛡️ Content Security Policy (CSP)

Guia completo e operacional do sistema CSP do tema ReloadeD. Cobre conceito, arquitetura, operação dia-a-dia, calibração e promoção pra enforce mode.

> **TL;DR** — Status atual: `Content-Security-Policy-Report-Only` ativo, calculado dinamicamente conforme integrações do painel + nonce + `'strict-dynamic'`. Browser **não bloqueia nada**, apenas reporta violações pra `/wp-json/rd/v1/csp-report`. Visualização e gestão em **Painel → Segurança**. Origens extras (integrações futuras) podem ser adicionadas direto no painel sem editar PHP.

---

## 1. O que CSP protege contra

CSP é um **firewall a nível de browser**: você declara "esse site só pode carregar X de origens Y", e o browser bloqueia o resto.

| Ameaça | Como CSP defende |
|--------|------------------|
| **XSS (Cross-Site Scripting)** | `script-src` bloqueia execução de JS não-autorizado mesmo se atacante injetar `<script>` no HTML |
| **Clickjacking** | `frame-ancestors` controla quem pode embedar seu site em `<iframe>` |
| **Tracking não-autorizado** | Reports revelam origens externas — útil pra LGPD/auditoria |
| **Mixed content** | Força HTTPS em tudo via `default-src https:` |
| **Data exfiltration** | `connect-src` controla pra onde JS pode mandar dados (fetch/XHR/WebSocket) |
| **Plugin malicioso** | Plugin que injeta tracking não-listado é bloqueado e reportado |

**Pegada principal:** CSP é a **última linha de defesa contra XSS**. Mesmo se bug permitir injeção de HTML, o browser não executa `<script>` proibido pelo CSP.

---

## 2. Arquitetura no tema

Tudo vive em `inc/mod-csp.php` (módulo dedicado, isolado do `mod-security.php` que cuida de outros headers HTTP). Quatro partes:

### a) Policy builder — `rd_csp_build_policy()`

Monta a string CSP dinamicamente conforme integrações ativas + nonce + custom origins do painel:

```php
// Baseline com nonce + strict-dynamic
$d = [
    'default-src' => ['self'],
    'script-src'  => ['self', "'nonce-XXX'", "'strict-dynamic'", "'unsafe-inline'"],
    'style-src'   => ['self', "'nonce-XXX'", "'unsafe-inline'"],
    'img-src'     => ['self', 'data:', 'https:'], // permissivo pra markdown externo
    'font-src'    => ['self', 'data:'],
    'connect-src' => ['self'],
    'frame-src'   => ['self'],
    'object-src'  => ['none'],                    // bloqueia Flash legacy
    'base-uri'    => ['self'],
    'form-action' => ['self'],
];

// Origens condicionais — adicionadas só se integração está ON no painel
if (rd_get_option('ga_id'))            $d['script-src'][] = 'https://www.googletagmanager.com';
if (is_active_widget(false, false, 'rd_discord')) $d['frame-src'][] = 'https://ptb.discord.com';
// ...etc

// Custom origins do painel (3 textareas — admin adiciona origens novas sem PHP)
$d['script-src']  = array_merge($d['script-src'],  rd_csp_parse_custom_origins(rd_get_option('csp_custom_scripts')));
$d['connect-src'] = array_merge($d['connect-src'], rd_csp_parse_custom_origins(rd_get_option('csp_custom_scripts')));
$d['frame-src']   = array_merge($d['frame-src'],   rd_csp_parse_custom_origins(rd_get_option('csp_custom_frames')));
$d['style-src']   = array_merge($d['style-src'],   rd_csp_parse_custom_origins(rd_get_option('csp_custom_styles')));
$d['font-src']    = array_merge($d['font-src'],    rd_csp_parse_custom_origins(rd_get_option('csp_custom_styles')));
```

**Vantagem da arquitetura condicional:** ativa uma integração (ex.: configura o GA, ou coloca o widget Discord) → policy automaticamente permite a origem; remove → origem some. **Sem editar PHP** pra integrações já mapeadas.

**Custom origins do painel:** se aparecer integração nova que NÃO está no código (tracker novo, embed novo, CDN nova), admin cola no campo certo dos 3 textareas em **Painel → Segurança** sem precisar editar PHP. Ver seção 4 abaixo.

### a.1) Nonce + strict-dynamic — `rd_csp_nonce()` + helpers

Gerado uma vez por request, propagado pra todo `<script>`/`<style>` legítimo do tema. Sem nonce, o atacante que conseguir injetar HTML (XSS) pode botar `<script>` malicioso que o browser executa porque CSP tem `'unsafe-inline'`. Com nonce, browser só executa scripts que tenham aquele valor específico do request — atacante não consegue adivinhar.

**Três helpers principais** (todos em `inc/mod-csp.php`):

```php
rd_csp_nonce()        // → 'aB3xQ9...' (base64url, 22 chars) ou '' se CSP OFF
rd_csp_nonce_attr()   // → ' nonce="aB3xQ9..."' (atributo pronto pra concatenar) ou ''
rd_csp_inject_nonce($html)  // → injeta nonce em todas as <script> tags do $html, idempotente
```

**Como usar** em código novo do tema:

```php
// Inline script no PHP:
echo '<script' . rd_csp_nonce_attr() . '>console.log("ok");</script>';

// HTML com script vindo de input do admin (campos ad_*, snippets):
echo rd_csp_inject_nonce( $ad_global );

// Em enqueues normais (wp_enqueue_script), nonce é adicionado AUTOMATICAMENTE
// via filter script_loader_tag — não precisa fazer nada.
```

**Pattern da policy** segue recomendação Google CSP Evaluator / OWASP:

```
script-src     'self' 'nonce-XXX' 'strict-dynamic' 'unsafe-inline' [origins...]
style-src      'self' 'nonce-XXX' 'unsafe-inline'
style-src-attr 'unsafe-inline'
```

- **`'nonce-XXX'`** — só `<script>`/`<style>` com esse nonce executam (defesa contra XSS)
- **`'strict-dynamic'`** — script com nonce pode carregar scripts filhos dinamicamente sem precisar listar origens (essencial pro GA, Clarity, AdSense que fazem isso). Só aplica a `script-src`, não a `style-src` (CSP3 spec).
- **`'unsafe-inline'`** no `script-src`/`style-src` — fallback pra browsers pré-CSP3 (Safari < 15.4). Em browsers modernos, presença de nonce **automaticamente anula** `'unsafe-inline'` (regra do CSP3 spec) — fica como "no-op" pros modernos e graceful degradation pros velhos.
- **`style-src-attr 'unsafe-inline'`** (directive separada) — governa SOMENTE atributos `style="..."` em elementos HTML, não `<style>` tags. Nonces NÃO se aplicam a attributes (CSP3 spec), então sem essa directive separada, qualquer `style=""` legítimo (WP admin bar, archive header com cor de categoria, conteúdo do post com HTML inline, blocos Gutenberg) seria bloqueado em browsers modernos. Trade-off aceito: `style=""` injetado via XSS pode fazer defacing visual ou clickjacking, mas não executa JS (CSS `expression()` morreu com IE).

**Cache-friendliness:** o nonce muda a cada request. Se um dia ligarmos page cache (Cloudflare full-page cache, plugin de page cache), o nonce ficaria congelado no cache → atacante poderia coletar e reusar. **Atualmente seguro porque Redis Object Cache (o que vamos usar) NÃO cacheia HTML — só queries/transients.** Se um dia mudar pra page cache, precisa: (a) edge rewriting do nonce via Cloudflare Worker/Nginx; OU (b) migrar pra hash-based CSP (mais trabalhoso mas cacheable).

### b) Header injection — `rd_csp_inject_header()`

Hook em `wp_headers` filter. Injeta o header só:
- Quando `enable_csp_report_only` está ON
- No frontend (admin tem seus próprios scripts inline do WP core — sem CSP no admin)

### c) Report endpoint — `/wp-json/rd/v1/csp-report`

Endpoint REST público que recebe relatórios de violação do browser. Valida estrutura (formato padrão CSP-2), aplica whitelist defensiva de campos, armazena FIFO de 100 entries na option `rd_csp_reports` (autoload=no).

**Rate limit:** 60 reports por IP por janela de 60s (constantes `RD_CSP_RATE_LIMIT_MAX` e `RD_CSP_RATE_LIMIT_WINDOW`). Necessário porque o endpoint é público por design (browsers não autenticam reports CSP) — sem teto, atacante podia floodar 100+ POSTs JSON minimalistas pra preencher o FIFO e ocultar violações legítimas. Quando o limite é excedido, endpoint responde **429** com header `Retry-After: 60`. IP vem do helper `rd_get_client_ip()` em `core.php` — valida `REMOTE_ADDR` antes de confiar em `CF-Connecting-IP`/`X-Forwarded-For` (mesma defesa de `mod-maintenance` e `mod-views`).

### d) Visualização

Função `rd_csp_render_reports_panel()` é callback de section em **Painel → Segurança**. Mostra tabela com colunas `When | Directive | Blocked URI | Document`. Botão "Clear reports" (nonce-protected) zera o storage.

---

## 3. Onde modificar coisas

| Quero... | Onde edita |
|----------|------------|
| Ligar/desligar CSP | Painel → Segurança → **Enable CSP** |
| **Promover pra enforce mode** | Painel → Segurança → **⚠️ Enforce Mode** (toggle) |
| **Adicionar origem nova (script/embed/style)** | Painel → Segurança → 3 textareas **Custom Origins** — 1 por linha. Não precisa editar PHP. |
| Adicionar origem global de baseline (raríssimo) | `inc/mod-csp.php` → `rd_csp_build_policy()` → array `$d['<directive>']` |
| Adicionar suporte a nova integração condicional (toggle dedicado) | `inc/mod-csp.php` → bloco "Integrações condicionais" — copia padrão de uma existente (ex: Discord) |
| Limpar reports armazenados | Painel → Segurança → botão **Clear reports** |
| Aumentar/diminuir limite FIFO | `inc/mod-csp.php` → constante `RD_CSP_REPORTS_MAX` |
| Aumentar/diminuir rate-limit do endpoint | `inc/mod-csp.php` → constantes `RD_CSP_RATE_LIMIT_MAX` (default 60) e `RD_CSP_RATE_LIMIT_WINDOW` (default 60s) |

**Nota:** integrações **mapeadas no código** (GA, Clarity, FB Pixel, Discord, YouTube, etc.) liberam suas origens automaticamente quando ativas (toggle ON, ID configurado, ou — no caso do Discord — o widget colocado). Integrações **não mapeadas** vão pros 3 campos Custom Origins do painel — admin controla sem precisar mexer em PHP.

### Os 3 campos Custom Origins

Cada campo cobre um agrupamento de directives que costuma andar junto:

| Campo no painel | Aplica em | Quando usar |
|-----------------|-----------|-------------|
| **Scripts & APIs** | `script-src` + `connect-src` | Trackers, SDKs, JS de terceiros, APIs que o frontend chama (fetch/XHR) |
| **Iframes & Embeds** | `frame-src` | Players de vídeo, embeds de música, widgets de terceiros |
| **Styles & Fonts** | `style-src` + `font-src` | CDN de fontes externa, folhas de estilo de terceiros |

**Sintaxe aceita** (1 por linha em cada campo):
- `https://hostname` ou `https://hostname:port`
- `https://*.subdomain.example.com` (wildcard só em subdomínio)
- `https:` (libera **qualquer** host HTTPS naquela directive — use com critério)

**Rejeitado silenciosamente:**
- HTTP puro (`http://...`) — força HTTPS
- Keywords com aspas (`'unsafe-inline'`, `'self'`, etc.) — anulariam o nonce
- Wildcard puro (`*`) — derrota o propósito
- Schemes não-padrão (`data:`, `blob:`, etc.) — exigem edição PHP

**Como descobrir o campo certo:** olha o painel de reports. A coluna "Directive" mostra qual foi violada (`script-src-elem`, `frame-src`, `style-src`, etc.) → escolhe o campo correspondente.

---

## 4. Ciclo de vida — Report-Only → Enforce

```
┌─────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│   1. OBSERVE    │ → │  2. CALIBRATE     │ → │  3. ENFORCE      │
│  Report-Only    │    │ Ajusta policy    │    │ Bloqueia mesmo   │
│  Sem bloqueio   │    │ baseado em       │    │ Sai do "sandbox" │
│  Coleta dados   │    │ violações reais  │    │                  │
└─────────────────┘    └──────────────────┘    └──────────────────┘
   ~2-4 semanas           ~2-4 semanas              forever
```

**Estamos na fase 1.** Pular pra fase 3 sem calibrar = quebrar funcionalidades legítimas. Esse é o erro #1.

---

## 5. Operação dia-a-dia

Não precisa monitorar todo dia. Cadência saudável:

### Semana 1-2 (após ligar a feature): Aclimação
- Navega o site normalmente — single posts, archives, busca, comments, modal de cookies
- A cada 2-3 dias volta no painel → vê reports acumulados
- **Não muda policy ainda** — só observa padrões

### Semana 3+: Calibração ativa
Pra cada violação recorrente, classifica:

| Categoria | Sinal | Ação |
|-----------|-------|------|
| **Legítima esperada** | URL reconhecida (GitHub, CDN, badge) | Adiciona à policy ou já está coberta |
| **Legítima inesperada** | URL faz sentido mas não esperava (plugin novo, embed novo) | Investiga origem, adiciona se quiser |
| **Ruído** | `chrome-extension://`, `moz-extension://`, `safe-frame.com` | **Ignora** — é coisa do navegador do visitante |
| **Suspeita** | URL desconhecida sem relação com nada instalado | **Investiga urgente** — pode ser plugin comprometido ou tentativa de injeção |

### Mês 2+: Manutenção
- Quando adicionar feature/plugin/integração nova, espera reports aparecerem
- Atualiza policy conforme necessário
- Antes de subir pra produção, decide promoção pra enforce

---

## 6. Violações comuns e o que fazer

| Directive | Causa típica | O que fazer |
|-----------|--------------|-------------|
| `img-src` | Markdown externo, badges, screenshots | Já coberto pelo `https:` |
| `script-src` | Plugin/embed/tracker novo | Adicionar origem específica OU remover o plugin |
| `frame-src` | Embed novo (Twitch, Spotify, Vimeo) | Adicionar origem |
| `connect-src` | API externa (fetch/XHR), WebSocket | Adicionar origem |
| `style-src` | CSS de CDN (Google Fonts, etc.) | Adicionar origem |
| `font-src` | Fonte de CDN | Adicionar origem |
| `default-src` | Recurso de tipo não-mapeado | Analisar — provavelmente algo exótico (audio, manifest, worker) |

> ⚠️ **Atenção a violações de extensão de navegador.** Fontes/scripts injetados por extensões (ex.: a extensão do Adobe Acrobat carregando `use.typekit.net`) aparecem como violação mesmo o tema não pedindo nada disso. **Não adicione a origem** — é ruído do cliente, não do site.

### Identificando violações "inline" (report-sample)

O `script-src` carrega a keyword `'report-sample'`: navegadores anexam os primeiros ~40 chars do script inline bloqueado ao report (`script-sample`), exibidos na tabela do painel abaixo do blocked-uri (`↳ snippet`). Sem isso, violação inline é um "inline" anônimo impossível de diagnosticar remotamente. Caso real: o Auto Ads do AdSense injeta inline parser-inserted (`document.write`) que o `strict-dynamic` não cobre — o sample identifica o autor direto dos reports dos visitantes.

### Filtro de ruído (2 camadas)

O endpoint que recebe os reports descarta ruído **antes de gravar**, pra o painel mostrar só violação real:

- **Camada A — esquema de extensão (automática, em código):** descarta quando o `source-file` vem de `chrome-extension://`, `moz-extension://`, `safari-web-extension://` ou `webkit-masked-url://` (Safari). Cobre qualquer extensão, sem manutenção.
- **Camada B — denylist de hosts (painel):** campo `csp_report_denylist` em *Security*. Para o ruído que escapa da camada A sem `source-file` de extensão (alguns navegadores não atribuem a origem), basta listar o host (ex.: `use.typekit.net`). 1 host por linha, sem esquema.

Implementação em `inc/mod-csp.php`: `rd_csp_report_is_noise()` (predicado das 2 camadas) + `rd_csp_parse_report_denylist()` (parser do campo). O filtro só vale pra reports **novos** — pra limpar os antigos, use o botão *Clear reports* no painel.

---

## 7. Como promover pra Enforce mode

> ⚠️ **Não faça isso enquanto policy não estiver madura.** Promover cedo = quebrar funcionalidades.

### Pre-requisitos (checklist antes da promoção)

- [ ] **30+ dias** rodando em report-only
- [ ] **0 violações inesperadas** nos últimos 7 dias
- [ ] Testou em **múltiplos browsers** (Chrome, Firefox, Safari, mobile)
- [ ] Testou edge cases (post com markdown externo, embed novo, etc.)
- [ ] Site já está em **produção com tráfego real** (`reloaded.com`, não só sandbox)
- [ ] **Plano de rollback** claro (toggle no painel — você desliga em segundos)

### Promoção

**Pelo painel** (recomendado, sem deploy):

1. Vai em **Painel → Segurança**
2. Marca o toggle **⚠️ Enforce Mode**
3. Save Changes
4. **Pronto** — browser agora BLOQUEIA o que está fora da policy

**Rollback é instantâneo**: desmarca o toggle e save. Sem deploy, sem cache invalidation manual (Cloudflare pode segurar header por minutos, mas é só esperar TTL).

O badge no painel muda de `REPORT-ONLY` (azul) pra `ENFORCE MODE` (vermelho) — confirmação visual.

**Alternativa via código** (se preferir versionar a decisão no git):
O toggle do painel simplesmente decide qual header name o `rd_csp_inject_header()` injeta. Se quiser hardcoded no PHP, edita a função em `inc/mod-csp.php`:

```diff
- $header_name = rd_get_option_bool( 'csp_enforce_mode' )
-     ? 'Content-Security-Policy'
-     : 'Content-Security-Policy-Report-Only';
+ $header_name = 'Content-Security-Policy';
```

Aí o toggle do painel vira inerte. Faz sentido se você quer travar a decisão e impedir mudança acidental pelo painel.

### Estratégia gradual (recomendada)

Em vez de "big bang" (enforce em todo o site de uma vez), considere:

1. **Mantém report-only ativo** mas adiciona enforce em paralelo — browsers entendem **dois headers** simultâneos: um enforce, outro report-only mais permissivo
2. **Enforce só em algumas URLs** (ex: páginas estáticas tipo `/sobre/`, deixa posts/admin em report-only por mais tempo)
3. **Enforce com policy MAIS PERMISSIVA** primeiro (mantém `'unsafe-inline'`, libera mais origens) — vai apertando ao longo de meses

---

## 8. Armadilhas comuns

### A) Cache segurando policy antiga
Mudou `mod-csp.php` mas Cloudflare pode estar servindo response cacheada com header antigo. **Defesa:** purge cache do Cloudflare após mudanças importantes em CSP.

### B) `'unsafe-inline'` é fallback, não exposição
A policy atual tem `script-src 'self' 'nonce-XXX' 'strict-dynamic' 'unsafe-inline'` e `style-src 'self' 'nonce-XXX' 'unsafe-inline'`. Parece que `'unsafe-inline'` reabre a porta — mas **não reabre em browsers modernos.**

Por que: regra do CSP3 spec — quando uma directive contém um nonce ou hash, `'unsafe-inline'` é **automaticamente ignorado** pelo browser. Então em Chrome/Firefox/Safari modernos (CSP3, ~2017 em diante), só scripts com nonce executam.

Por que mantemos `'unsafe-inline'`: é **graceful degradation** pra browsers pré-CSP3 (Safari < 15.4 / 2022). Sem o fallback, esses users veriam coisas quebrarem (anti-FOUC, tracking, etc.). Com fallback, eles voltam a ter CSP "estilo antigo" (sem proteção XSS forte, mas funcionando). Trade-off oficial recomendado pelo Google.

**Scanners externos** (securityheaders.com, Mozilla Observatory) reconhecem o pattern `nonce + strict-dynamic + unsafe-inline` e dão nota cheia — entendem que `'unsafe-inline'` é nullificado.

### C) Cloudflare Rocket Loader (se ativo)
Wraps todos os `<script>` numa shell diferente. Pode causar violações estranhas. Verifica em Cloudflare → Speed → Optimization → Rocket Loader. Se ON, ou desliga, ou ajusta CSP pra permitir o wrapper.

### D) Browsers reportam coisas diferentes
Chrome é o mais barulhento. Firefox reporta menos. Safari quase nada. Não se assuste se 80% das violações vierem de Chrome.

### E) Plugin que injeta inesperado
Plugin futuro pode injetar tracking/CDN sem você esperar. **Isso é uma feature, não bug** — CSP te revela o que o plugin realmente faz, antes de afetar usuários.

---

## 9. Valor adicional ALÉM da segurança

### 🔐 Auditoria LGPD/Privacidade
CSP-Report-Only mostra **toda** origem externa que o site carrega. Útil pra:
- Detectar tracking de third-party que você não sabia
- Mapear recursos de origens não-EU (relevante pra GDPR)
- Documentar dependências externas pra compliance

### 🚨 Sistema de alerta de invasão
Se invasor injetar script malicioso (`evil-tracker.com`), aparece na sua tabela CSP em minutos. **Sistema gratuito de detecção.**

### 📋 Documentação ativa
Lista de origens externas vira documentação viva. Auditor pergunta "que serviços vocês usam?" — abre o painel.

---

## 10. Resumo em 5 regras

1. **Deixa rodando** — custo é mínimo, ganho de visibilidade é alto
2. **Olha 1x por semana** ou quando algo estranho aparecer
3. **Investiga URLs desconhecidas** — pode ser ataque/plugin malicioso
4. **Ajusta policy** conforme adiciona features (a lógica condicional já cobre integrações mapeadas — só edita PHP pra integrações novas que não existiam)
5. **Espera 30+ dias sem surpresas** antes de promover pra enforce

---

## 11. Próximos passos

### Curto prazo
- Continuar usando em report-only (já está ativo)
- Acompanhar via painel 1x/semana
- Não promover pra enforce ainda

### Médio prazo (subida pra `reloaded.com` em produção)
- Deixar report-only rodando em produção também por 2-4 semanas
- Coletar reports de tráfego real (visitantes variados, browsers diferentes)
- Aí sim pensar em promoção

### Longo prazo
- Promover pra enforce gradual (não big-bang)
- ~~Migrar pra nonce-based CSP~~ ✅ implementado em 2026-05-23 (Wave 8.5) — nonce + `'strict-dynamic'` ativos na policy. Browsers modernos enforce strict via nonce; `'unsafe-inline'` mantido como fallback pra browsers pré-CSP3
- Se um dia ligar **page cache** (Cloudflare full-page, Redis page cache), avaliar: edge rewriting do nonce OU migração pra hash-based CSP (CSP cacheable). Object cache atual (Redis via plugin Till Kruss) não afeta — só cacheia queries/transients, não HTML.

---

## 📚 Referências externas

- **MDN — CSP overview**: https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP
- **OWASP CSP cheat sheet**: https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html
- **CSP Evaluator (Google)** — cola sua CSP, mostra fraquezas: https://csp-evaluator.withgoogle.com/
- **report-uri.com** — serviço pra reportar/analisar CSP (alternativa ao endpoint próprio, caso queira dashboards visuais um dia)
