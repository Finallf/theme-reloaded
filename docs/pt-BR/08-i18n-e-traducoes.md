# 08 — i18n e Traduções

O ReloadeD foi construído com internacionalização (i18n) **first-class**. Toda string de UI passa pelas funções gettext do WordPress — facilitando tradução pra qualquer idioma.

## 📐 Estrutura

```
languages/
├── reloaded.pot      # Template — todas as strings extraídas do código
├── pt_BR.po          # Tradução PT-BR (editável no Poedit)
└── pt_BR.mo          # Compilado (consumido pelo WP em runtime)
```

## 🔤 Convenções

### Text domain

**Sempre** `'reloaded'`. Em todas as funções i18n:

```php
__( 'Search', 'reloaded' )
_e( 'Search', 'reloaded' )
esc_html__( 'Search', 'reloaded' )
esc_attr__( 'Search', 'reloaded' )
esc_html_e( 'Search', 'reloaded' )
esc_attr_e( 'Search', 'reloaded' )
_n( '%s comment', '%s comments', $count, 'reloaded' )
```

> Pra plurais use `_n()` (singular vs plural). Pra strings com placeholder use sempre `sprintf()` em volta:

```php
sprintf(
    /* translators: %s: site name */
    __( 'The %s portal is undergoing scheduled maintenance...', 'reloaded' ),
    get_bloginfo( 'name' )
);
```

### Convenção do nome do `.mo` no tema

Quando arquivos de tradução vivem **dentro da pasta do tema** (em `/languages/`), a convenção é apenas **`{locale}.mo`** — sem o prefixo do text domain.

✅ **Correto**: `pt_BR.mo`, `en_US.mo`, `es_ES.mo`
❌ **Errado**: `reloaded-pt_BR.mo` (essa convenção é pra arquivos em `wp-content/languages/themes/`)

> Veja a [referência oficial WordPress](https://developer.wordpress.org/themes/classic-themes/functionality/internationalization/) na seção "Watch Out".

### Source language

Inglês (`en-US`). Toda string fica em inglês no código PHP. Traduções são overlays.

## 🛠️ Workflow de tradução

### 1. Gerar / atualizar o `.pot`

O `.pot` é o "template" — contém todas as strings traduzíveis extraídas do código.

#### Via WP-CLI (recomendado)

```bash
cd wp-content/themes/reloaded/
wp i18n make-pot . languages/reloaded.pot --domain=reloaded
```

#### Via Poedit

1. **Catalog → Update from sources**
2. Aponta pra pasta do tema
3. Configura keywords gettext e domain (Poedit detecta automaticamente)

### 2. Editar / atualizar a tradução `.po`

#### No Poedit (mais fácil)

1. Abre `languages/pt_BR.po`
2. Clica em **Catalog → Update from POT file...**
3. Aponta pro `reloaded.pot`
4. Strings novas aparecem como "untranslated" — traduz uma a uma
5. Strings que não existem mais ficam como "obsolete" (pode deletar)
6. Salva → Poedit compila o `.mo` automaticamente

#### Direto no arquivo (manual)

Editor de texto, segue o formato:

```po
#. translators: %s: search query
#: search.php:24
#, php-format
msgid "Results for: %s"
msgstr "Pesquisou por: %s"
```

`msgid` = original (não muda)
`msgstr` = sua tradução

### 3. Compilar o `.mo`

#### Via Poedit

Salvar o `.po` no Poedit já gera o `.mo` automaticamente.

#### Via WP-CLI

```bash
wp i18n make-mo languages/
```

#### Via msgfmt (gettext nativo)

```bash
msgfmt -o languages/pt_BR.mo languages/pt_BR.po
```

## 📌 Strings em JavaScript

Pra strings que precisam aparecer em JS (ex.: feedback "Copiado!"), use `wp_localize_script`:

```php
// PHP — no enqueue
wp_enqueue_script( 'rd-navigation', '...', array(), '1.0', true );
wp_localize_script( 'rd-navigation', 'reloaded_i18n', array(
    'copied'     => __( 'Copied!', 'reloaded' ),
    'copy_error' => __( 'Failed to copy', 'reloaded' ),
) );
```

```js
// JS — usa o objeto
btn.addEventListener('click', function() {
    navigator.clipboard.writeText(key).then(() => {
        textSpan.innerText = reloaded_i18n.copied; // string traduzida
    });
});
```

> Não use string em JS direto — ela não vai ser pega pelo `make-pot`.

## 🆕 Adicionando um novo idioma

Pra traduzir o tema pra espanhol, por exemplo:

1. Cria `languages/es_ES.po` (pode copiar do `pt_BR.po` e zerar os `msgstr`)
2. Atualiza o cabeçalho do arquivo:
   ```
   "Language: es_ES\n"
   "Plural-Forms: nplurals=2; plural=(n != 1);\n"
   ```
3. Traduz todas as strings via Poedit
4. Compila pra `es_ES.mo`
5. Pronto — quando algum visitante tiver locale `es_ES` (configurado no admin do WP em **Settings → General → Site Language**), o tema renderiza em espanhol

## 🔧 Como o WP descobre as traduções

Em `inc/core.php`:

```php
function rd_setup() {
    load_theme_textdomain( 'reloaded', get_template_directory() . '/languages' );
    // ...
}
add_action( 'after_setup_theme', 'rd_setup' );
```

Quando o WP renderiza uma página:
1. Pega o locale atual (`get_locale()`)
2. Procura `{locale}.mo` em `get_template_directory() . '/languages'`
3. Se achar, carrega — todas as `__()` calls passam a retornar a tradução
4. Se não achar, retorna a string original (`en-US`)

## 📊 Estatísticas atuais

```bash
msgfmt --statistics languages/pt_BR.po
```

Mostra quantas strings estão traduzidas vs total. Útil pra acompanhar progresso de tradução.

## ⚠️ Cuidados

1. **Não traduza strings técnicas** que viram identificadores (slugs, classes CSS, URLs)
2. **Mantenha `%s`, `%d`, etc.** intactos — placeholder de `sprintf`
3. **Mantenha HTML tags** intactas — ex.: se o original tem `<strong>`, a tradução também tem
4. **Em strings com `_n()`** (plural), traduz ambas as formas:
   ```po
   msgid "%s Comment"
   msgid_plural "%s Comments"
   msgstr[0] "%s Comentário"
   msgstr[1] "%s Comentários"
   ```
5. **Translator hints** (`/* translators: %s: site name */`) ajudam — eles aparecem no Poedit e dizem o contexto

## 🌍 Cobertura atual

| Idioma | Status | Arquivo |
|--------|--------|---------|
| **English (en-US)** | ✅ Source language | (no .po — strings literais no código) |
| **Português (pt-BR)** | ✅ Completo | `pt_BR.po` |

Outros idiomas: contribuições bem-vindas via PR! Veja [CONTRIBUTING.md](../../CONTRIBUTING.md).
