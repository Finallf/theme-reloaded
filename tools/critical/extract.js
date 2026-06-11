/* ============================================================================
 * tools/critical/extract.js
 * ----------------------------------------------------------------------------
 * Extrai Critical CSS (regras necessárias pro above-the-fold) de uma página
 * do sandbox e salva em `assets/css/critical/critical-{template}.css`.
 * O PHP do tema (mod-critical-css.php) injeta esse arquivo inline no <head>
 * quando o toggle "Inline Critical CSS" está ativado no painel de Performance.
 *
 * Uso:
 *   npm run critical:home
 *   npm run critical:single
 *   npm run critical:page
 *   npm run critical:archive
 *   npm run critical:search
 *   npm run critical:all
 *
 * Pré-requisito: sandbox `reloaded.com.br` acessível publicamente
 * (Puppeteer faz visita real pra calcular o critical path).
 *
 * Quando re-rodar:
 *   - Mudou SCSS de above-the-fold (header, hero, primeiros cards, etc.)
 *   - Adicionou/removeu feature que afeta o top da página
 *   - Após cada release grande pré-produção
 * ========================================================================== */

import { generate } from 'critical';
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const themeRoot = resolve(__dirname, '../..');

// Configuração base — sobrescrita pelos templates abaixo.
const BASE_URL = process.env.RD_SANDBOX_URL || 'https://theme.reloaded.com.br';
const SRC_CSS = resolve(themeRoot, 'assets/css/style.css');
const OUT_DIR = resolve(themeRoot, 'assets/css');

// Dimensões cobertas — múltiplas dimensões garantem que o critical inclui
// regras pra mobile (412×823 — Lighthouse Mobile), tablet (768×1024) e
// desktop (1920×1080 — Lighthouse Desktop). O extractor mescla os 3 sets.
const DIMENSIONS = [
    { width: 412,  height: 823  }, // Mobile (Pixel-like, Lighthouse default)
    { width: 768,  height: 1024 }, // Tablet
    { width: 1920, height: 1080 }, // Desktop (Lighthouse desktop)
];

// Mapa de template → URL relativa no sandbox.
// Cada entry vira `npm run critical:{key}`. Pra cobrir um template novo,
// adicionar aqui + criar slug correspondente no sandbox + adicionar script
// no package.json.
const TEMPLATES = {
    home:    { path: '/',                                          desc: 'Home (front page)'      },
    single:  { path: '/em-um-passado-muito-distante/',             desc: 'Single post'            },
    page:    { path: '/sobre/',                                    desc: 'Static page (Sobre)'    },
    archive: { path: '/category/lorem-ipsum/',                     desc: 'Category archive'       },
    search:  { path: '/?s=lorem',                                  desc: 'Search results'         },
};

async function extractTemplate(templateKey) {
    const template = TEMPLATES[templateKey];
    if (!template) {
        console.error(`[ERROR] Template "${templateKey}" not found. Available: ${Object.keys(TEMPLATES).join(', ')}`);
        process.exit(1);
    }

    const targetUrl = BASE_URL + template.path;
    const outFile = resolve(OUT_DIR, `critical-${templateKey}.css`);

    console.log(`\n=== Extracting critical CSS for: ${template.desc} ===`);
    console.log(`  URL:    ${targetUrl}`);
    console.log(`  Source: ${SRC_CSS}`);
    console.log(`  Output: ${outFile}`);
    console.log(`  Dimensions: ${DIMENSIONS.map(d => `${d.width}x${d.height}`).join(', ')}`);

    try {
        const { css } = await generate({
            src: targetUrl,
            css: [SRC_CSS],
            inline: false,
            extract: false,
            width: 1920,
            height: 1080,
            dimensions: DIMENSIONS,
            penthouse: {
                timeout: 60000,
                renderWaitTime: 500,
                blockJSRequests: false,
                // Regras que o penthouse descarta por não casarem com o DOM no
                // momento do snapshot, mas que precisam estar no first paint:
                // o carousel ganha .is-ready via JS ANTES do snapshot, então o
                // seletor :not(.is-ready) (que esconde setas/dots até o JS
                // iniciar) nunca casa durante a extração — sem ele inline, os
                // controles aparecem mortos antes do carousel.js carregar.
                forceInclude: [/\.rd-carousel:not\(\.is-ready\)/],
                // Usa Chrome do sistema em vez do Chromium bundled (que vem com
                // o Puppeteer 2.1.1 obsoleto do critical e tem problemas no Win)
                puppeteer: {
                    executablePath: process.env.RD_CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                    args: ['--no-sandbox', '--disable-setuid-sandbox'],
                },
            },
            ignore: {
                atrule: ['@font-face'], // font-face é gerido separado (preload de fontes)
            },
        });

        // Garante que o diretório existe
        await mkdir(OUT_DIR, { recursive: true });

        // Cabeçalho informativo no arquivo.
        // NÃO incluir a URL da sandbox aqui — esse arquivo é shipped e injetado
        // inline em produção via <style id="rd-critical-css">. Vazar a URL do
        // sandbox no HTML público é leak desnecessário.
        const header = `/*!
 * Critical CSS for: ${template.desc}
 * Generated at ${new Date().toISOString()}
 * Dimensions: ${DIMENSIONS.map(d => `${d.width}x${d.height}`).join(', ')}
 *
 * DO NOT EDIT MANUALLY. Re-run: npm run critical:${templateKey}
 */
`;
        await writeFile(outFile, header + css, 'utf8');

        const sizeKb = (Buffer.byteLength(css, 'utf8') / 1024).toFixed(2);
        console.log(`  ✓ Saved ${sizeKb} KB of critical CSS`);

        await auditCoverage(targetUrl, css, templateKey);
    } catch (err) {
        console.error(`\n[ERROR] Failed to extract critical CSS for "${templateKey}":`);
        console.error(err.message || err);
        process.exit(1);
    }
}

// Watchlist de módulos above-the-fold POR TEMPLATE. São os módulos togglables
// do painel que, quando ativos em produção, renderizam acima do fold — ou
// seja: se um deles estiver desligado no sandbox na hora da extração, o
// critical sai sem as regras dele e produção pinta o módulo "pelado" no first
// paint (caso real: carousel OFF durante um critical:all → CLS de 0,94 em
// produção). Comparar DOM × critical não pega esse caso — toggle OFF remove o
// módulo do DOM também e a comparação "bate" — daí a lista explícita.
// `_all` aplica a todos os templates. Ativou feature nova above-the-fold no
// painel? Adicionar o módulo aqui.
const ABOVE_FOLD_WATCH = {
    _all: ['rd-top-bar'], // top bar + ticker (header.php, todos os templates)
    home: ['rd-carousel'],
};

/**
 * Guard de cobertura: pra cada módulo da watchlist, confere as duas pontas —
 * (1) o módulo existe no DOM da página do sandbox? (não existir = toggle OFF
 * ou conteúdo faltando → sandbox não espelha produção); (2) existindo no DOM,
 * sobrou regra dele no critical gerado? (não sobrar = anomalia de extração).
 * Best-effort e não-bloqueante: avisa e segue.
 *
 * "Módulo" = classe normalizada até o bloco BEM: rd-carousel__track e
 * rd-carousel--x contam ambos como rd-carousel.
 */
async function auditCoverage(targetUrl, css, templateKey) {
    const toModule = (cls) => cls.split('__')[0].split('--')[0];
    const watch = [...ABOVE_FOLD_WATCH._all, ...(ABOVE_FOLD_WATCH[templateKey] || [])];

    try {
        const res = await fetch(targetUrl);
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        const html = await res.text();

        const domModules = new Set();
        for (const m of html.matchAll(/class=["']([^"']+)["']/g)) {
            for (const cls of m[1].split(/\s+/)) {
                if (cls.startsWith('rd-')) {
                    domModules.add(toModule(cls));
                }
            }
        }

        const cssModules = new Set();
        for (const m of css.matchAll(/\.(rd-[\w-]+)/g)) {
            cssModules.add(toModule(m[1]));
        }

        let ok = true;
        for (const mod of watch) {
            if (!domModules.has(mod)) {
                ok = false;
                console.warn(`  ⚠ Coverage: "${mod}" is expected above the fold but is NOT in the page DOM.`);
                console.warn('    Sandbox is not mirroring production (toggle OFF? missing content?).');
                console.warn(`    Fix the sandbox state and re-run npm run critical:${templateKey}`);
            } else if (!cssModules.has(mod)) {
                ok = false;
                console.warn(`  ⚠ Coverage: "${mod}" is in the page DOM but has NO rule in the generated critical.`);
                console.warn('    Extraction anomaly — inspect before shipping this file.');
            }
        }
        if (ok) {
            console.log(`  ✓ Coverage: above-the-fold watchlist OK (${watch.join(', ')})`);
        }
    } catch (err) {
        // Auditoria é best-effort: falha de rede aqui não invalida a extração.
        console.warn(`  (coverage check skipped: ${err.message || err})`);
    }
}

// Entry point
const templateArg = process.argv[2];
if (!templateArg) {
    console.error('Usage: node tools/critical/extract.js <template>');
    console.error(`Available templates: ${Object.keys(TEMPLATES).join(', ')}`);
    process.exit(1);
}

extractTemplate(templateArg);
