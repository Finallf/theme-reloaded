# 09 — Desenvolvimento e CI/CD

## 🌳 Branches e fluxo Git

O tema usa um modelo **two-branch** simples e robusto:

| Branch | Propósito | Ciclo de release |
|--------|-----------|------------------|
| **`master`** | Produção estável — deployável a qualquer momento | Releases finais (`v0.3.0`, `v0.3.1`, etc.) |
| **`beta`** | Desenvolvimento ativo — recebe features e fixes em primeira mão | Pre-releases (`v0.3.0-beta.1`, `v0.3.0-beta.2`, etc.) |

### Fluxo padrão de uma feature

```
1. Desenvolve na branch beta (commits diretos, ou via feature branches que mergeam em beta)
2. Push pra origin/beta → CI roda → semantic-release gera nova beta
3. Quando estável, abre PR de beta → master
4. Smoke-test passa → merge (com merge commit, NÃO squash)
5. CI no master detecta os commits acumulados e gera versão estável
6. Caminho B (sync master → beta): traz o release commit pra beta também
```

### Caminho B (sync master → beta após release)

Depois que master gerar uma nova release, beta fica "atrás" do release commit. Pra alinhar:

```bash
git checkout beta
git pull --ff-only          # garante beta local atualizada
git merge origin/master --no-edit   # traz o release commit (fast-forward)
git push origin beta
```

Geralmente é fast-forward sem conflitos, porque o release commit do master tem como base o que veio do beta.

---

## 📝 Conventional Commits

Todas as mensagens de commit seguem [Conventional Commits 1.0.0](https://www.conventionalcommits.org/).

### Formato

```
<type>(<scope>): <short description>

<longer body, can have multiple paragraphs>

<footer (Co-Authored-By, BREAKING CHANGE, etc.)>
```

### Types e impacto no semantic-release

| Type | Quando usar | Bump |
|------|-------------|------|
| `feat` | Nova funcionalidade visível pro user | **Minor** (`0.X.0`) |
| `fix` | Correção de bug | **Patch** (`0.0.X`) |
| `refactor` | Mudança interna sem alterar comportamento externo | (sem bump por padrão) |
| `style` | Mudanças visuais/cosméticas (CSS) | (sem bump) |
| `chore` | Manutenção (deps, config, docs) | (sem bump) |
| `docs` | Documentação | (sem bump) |
| `test` | Testes | (sem bump) |
| `ci` | Configuração de CI/CD | (sem bump) |
| `perf` | Otimização de performance | **Patch** |
| `BREAKING CHANGE` (no footer ou `!` no type) | API breaking | **Major** (`X.0.0`) |

### Scopes mais comuns no projeto

- `header`, `footer`, `sidebar`
- `menu`, `search`, `markdown`, `prism`
- `theme` (cores, dark/light)
- `i18n` (traduções)
- `panel` (admin)
- `seo`, `security`, `performance`
- `brand` (identidade visual)
- `typography`, `soc` (Separation of Concerns)

### Exemplos reais (do CHANGELOG)

```
feat(menu): add primary-category meta box to disambiguate multi-category highlight
fix(sidebar): clean white-corner artifacts on Discord iframe + drop dead margin hack
refactor(theme): adopt --rd-text-light as default body and headings color
chore(i18n): translate Primary Category strings to pt_BR
style: standardize box-shadow opacity to 0.5
```

### Body do commit

Boa prática: explicar **o porquê**, não o quê (o "o quê" é visível no diff).

```
fix(sidebar): clean white-corner artifacts on Discord iframe

When the Facades feature is disabled, the Discord widget renders as a
plain `<iframe>` with no class. Two problems showed up there:

1. Visible white pixels at the four rounded corners. The cause turned
   out to be CSS color-scheme inheritance...

2. Dropped a leftover `iframe { margin-bottom: -6px }` rule that was
   inherited from old testing...
```

---

## 🤖 GitHub Actions

Configuração em `.github/workflows/`:

### `smoke-test.yml`

Roda em **cada push** pra `master` ou `beta`, e em **cada PR** pra essas branches. **2 jobs:**

1. **PHP Syntax Check** — PHP 8.3, `php -l` em TODOS os `.php` (exceto vendor/node_modules)
2. **PHPCS + WPCS Lint** — `composer install` + `vendor/bin/phpcs` (lê o `phpcs.xml.dist`). ⚠️ **Warning também reprova** (sem `ignore_warnings_on_exit` no ruleset)

Garantia: você não consegue mergear/pushar PHP quebrado **nem fora do padrão WPCS**. Réplica local oficial: o hook `tools/git-hooks/pre-push` (ver Setup local).

### `master.yml` (Release & Package)

Roda no push pra `master` **e** `beta`. **2 jobs encadeados:**

1. **contrib-readme-job** — atualiza a lista de contribuidores no README
2. **release** — semantic-release (analisa commits → bump major/minor/patch pelos types → atualiza `CHANGELOG.md` + versão no `style.css` → tag `vX.Y.Z` → GitHub Release com notes) e monta o `reloaded.zip` (estrutura interna `reloaded/`, excluindo sass/docs/vendor/tooling), anexando na release

> Na `beta`, o canal beta do semantic-release gera tags `vX.Y.Z-beta.N` marcadas como pre-release.

#### Por que toda release gera um run "skipped" (cinza)?

Anti-loop **por design**: o semantic-release faz push do próprio commit `chore(release): X` (com GH_PAT, que dispara workflows). O `[skip ci]` nesse commit foi descartado — matava TODOS os checks do SHA, inclusive os required dos PRs de release, que travavam em "Waiting for status". Solução atual: o Smoke Test **roda** no commit de release (dá o check verde pro SHA) e o Release & Package **se auto-pula** via `if: !startsWith(message, 'chore(release):')` nos 2 jobs. Esse auto-pulo aparece como run cinza/skipped na lista de Actions. **É cosmético e esperado**: cinza no "Release & Package" de um commit `chore(release)` = o guard anti-loop funcionando.

---

## 🚀 Semantic Release

Configuração em `.releaserc.json` (raiz do projeto).

### Plugins usados

- `@semantic-release/commit-analyzer` — decide o bump baseado em Conventional Commits
- `@semantic-release/release-notes-generator` — gera release notes a partir dos commits
- `@semantic-release/changelog` — atualiza `CHANGELOG.md`
- `@semantic-release/git` — commita CHANGELOG + style.css de volta
- `@semantic-release/github` — cria a release no GitHub

### Branches configuradas

```json
{
  "branches": [
    "master",
    { "name": "beta", "prerelease": true }
  ]
}
```

### Como o release commit fica

```
chore(release): 0.3.0 [skip ci]
```

`[skip ci]` evita loop infinito (esse commit não dispara nova release).

### Como atualiza style.css

Há um plugin custom no `.releaserc.json` que faz substituição no header `Version: ` do `style.css`. Isso é importante pq é como o WordPress detecta a versão do tema (mostra na tela de Aparência → Temas).

---

## 🛠️ Setup local pra desenvolvimento

### Requisitos

- WordPress local rodando (Local by Flywheel, XAMPP, Docker, ou similar)
- Node.js 18+ (pra Sass + ferramental opcional)
- Git
- Editor — VSCode com extensões PHP Intelephense + Sass (recomendado)

### Compilação SCSS

```bash
# Instalação one-time
npm install -g sass

# Watch mode (recompila automaticamente quando salva)
sass --watch sass/style.scss:assets/css/style.css --style=expanded

# Build production (minified)
sass sass/style.scss:assets/css/style.css --style=compressed --no-source-map
```

> Alguns devs preferem compilar via VSCode extension "Live Sass Compiler" pra não rodar terminal.

### Workflow recomendado

1. **Edita `.scss`** em `sass/`
2. **Sass watcher** recompila pra `assets/css/style.css`
3. **SFTP/sync** envia pro WP local (se desenvolve em ambiente remoto)
4. **Refresh** no browser

> **Truque do usuário Finallf**: se SFTP só detecta mudança em `style.css`, dar um "touch" (espaço + apaga) em `sass/style.scss` força o Sass a recompilar e o SFTP propaga.

### Sem build pipeline complexo

O tema **NÃO usa** Webpack, Vite, Babel, TypeScript, etc. Apenas:
- Sass → CSS
- JS vanilla escrito direto em `assets/js/`
- PHP nativo

Filosofia: minimalismo, dependências zero pra contribuição.

### Hook de pre-push (réplica local do CI)

O push só deve sair da máquina **verde**. O hook `tools/git-hooks/pre-push` roda exatamente o que o Smoke Test roda (php -l em todos os `.php` + `vendor/bin/phpcs` completo, decidindo pelo **exit code**) e **bloqueia o push** se algo reprovar.

```bash
# Ativação one-time por clone (requer composer install feito):
git config core.hooksPath tools/git-hooks

# Bypass de emergência (não recomendado):
git push --no-verify
```

> Nasceu em 2026-06-12 depois de uma sequência de pushes vermelhos em que a verificação local era mais fraca que o CI (ou o output do phpcs era engolido por pipes). Regra da casa: se mudar o smoke-test.yml, atualizar o hook junto.

---

## 📦 Releases — visão prática

### Lançar uma nova versão estável

1. Trabalhe na `beta` (commits + push)
2. Quando satisfeito com a beta: abra PR `beta → master`
3. Aguarde smoke-test (PHP lint) passar
4. Use **"Create a merge commit"** (NÃO use squash — semantic-release precisa ver os tipos individuais)
5. Após merge, `master.yml` roda automaticamente
6. Em ~2-3 minutos: release no GitHub + tag (`v0.3.0`) + CHANGELOG atualizado

### Hotfix em produção

Se algo crítico vaza pra master:

```bash
git checkout master
git pull
git checkout -b hotfix/descricao-curta
# faz a correção
git commit -m "fix(scope): descrição"
git push -u origin hotfix/descricao-curta
# abre PR direto pra master
```

Após merge, `master.yml` gera `v0.3.X` patch. Depois faz Caminho B na beta pra trazer o fix de volta.

---

## 📝 PR Templates e Issue Templates

> Status atual: **NÃO criados ainda** (na backlog).

Quando criados, vão pra `.github/`:
- `.github/PULL_REQUEST_TEMPLATE.md`
- `.github/ISSUE_TEMPLATE/bug_report.md`
- `.github/ISSUE_TEMPLATE/feature_request.md`

Hoje, PRs são manuais (cada um escreve o título + body). Veja exemplos nas PRs já mergeadas pra padrão.

---

## ✅ Pre-flight checklist antes de pushar

- [ ] Mudanças visuais testadas em **dark + light** mode
- [ ] Testado em **desktop (≥1441px), tablet (1024px) e mobile (375px)**
- [ ] Sem erros no console do browser
- [ ] Sem erros no PHP error log
- [ ] Strings novas adicionadas ao `.pot` e traduzidas no `.po` (se aplicável)
- [ ] Commit message seguindo Conventional Commits
- [ ] CSS compilado (`assets/css/style.css` atualizado se SCSS mudou)
- [ ] PR description detalhada com test plan

---

## 🔗 Referências externas

- [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)
- [Semantic Release docs](https://semantic-release.gitbook.io/)
- [WordPress Theme Handbook](https://developer.wordpress.org/themes/)
- [Sass `@use` docs](https://sass-lang.com/documentation/at-rules/use/)
