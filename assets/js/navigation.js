document.addEventListener('DOMContentLoaded', function () {

	/* Voltar ao topo */
	const backToTopBtn = document.getElementById("back-to-top");

	if (backToTopBtn) {
		window.addEventListener("scroll", function() {
			let scrollPosition = window.scrollY || document.documentElement.scrollTop;

			if (scrollPosition > 300) {
				backToTopBtn.classList.add("show");
			} else {
				backToTopBtn.classList.remove("show");
			}
		});

		backToTopBtn.addEventListener("click", function() {
			window.scrollTo({
				top: 0,
				behavior: "smooth"
			});
		});
	}

	/* MENU (HAMBÚRGUER) */
	const ul = document.getElementById('primary-menu');
	const button = document.querySelector('.menu-toggle');

	if (ul && button) {
		button.onclick = function () {
			if (ul.classList.contains('toggled')) {
				ul.classList.remove('toggled');
				button.innerHTML = '<span class="hamburger-icon" aria-hidden="true">&#9776;</span>';
				button.setAttribute('aria-expanded', 'false');
				button.setAttribute('aria-label', 'Abrir menu de navegação');
			} else {
				ul.classList.add('toggled');
				button.innerHTML = '<span class="hamburger-icon" aria-hidden="true">&#10006;</span>';
				button.setAttribute('aria-expanded', 'true');
				button.setAttribute('aria-label', 'Fechar menu de navegação');
			}
		};
	}

	/* FACHADAS(Facades) (YOUTUBE, DISCORD, ETC.) - OTIMIZADO */
	const facades = document.querySelectorAll('.rd-facade');
	const isDiscordOpen = sessionStorage.getItem('rd_discord_open');

	facades.forEach(function (facade) {
		const id = facade.getAttribute('data-id');
		const type = facade.getAttribute('data-type');

		if (type === 'discord' && isDiscordOpen === 'true') {
			const iframeUrl = `https://ptb.discord.com/widget?id=${id}&theme=dark`;
			facade.innerHTML = `<iframe src="${iframeUrl}" width="100%" height="500" allowtransparency="true" frameborder="0" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>`;
			facade.style.cursor = 'default';
			return;
		}

		facade.addEventListener('click', function () {
			let iframeUrl = '';
			let iframeHtml = '';

			if (type === 'youtube') {
				iframeUrl = `https://www.youtube.com/embed/${id}?autoplay=1&rel=0`;
				iframeHtml = `<iframe src="${iframeUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%; height:100%; position:absolute; top:0; left:0;"></iframe>`;
			}
			else if (type === 'discord') {
				iframeUrl = `https://ptb.discord.com/widget?id=${id}&theme=dark`;
				iframeHtml = `<iframe src="${iframeUrl}" width="100%" height="500" allowtransparency="true" frameborder="0" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>`;
				sessionStorage.setItem('rd_discord_open', 'true');
			}

			if (iframeUrl !== '') {
				this.innerHTML = iframeHtml;
				this.style.cursor = 'default';
			}
		});
	});

    /* COPIAR CHAVE PIX */
    const pixButtons = document.querySelectorAll('.js-copy-pix');
    if (pixButtons.length > 0) {
        pixButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const pixKey = this.getAttribute('data-pix-key');
                const textSpan = this.querySelector('.rd-copy-text');
                const originalText = textSpan.innerText;

                navigator.clipboard.writeText(pixKey).then(() => {
                    // reloaded_i18n vem do wp_localize_script no PHP
                    textSpan.innerText = reloaded_i18n.copied;
                    this.classList.add('copied');

                    setTimeout(() => {
                        textSpan.innerText = originalText;
                        this.classList.remove('copied');
                    }, 2000);
                }).catch(err => {
                    console.error(reloaded_i18n.copy_error, err);
                });
            });
        });
    }

	/* PRISM - Adiciona line-numbers em todos os <pre> */
	document.querySelectorAll('pre').forEach(block => block.classList.add('line-numbers'));

});


// LGPD e Cookies
document.addEventListener("DOMContentLoaded", function() {
	var banner = document.getElementById("rd-lgpd-banner");
	var btn = document.getElementById("rd-cookie-accept");

	if (btn && banner) {
		btn.addEventListener("click", function() {
			banner.style.opacity = "0";
			setTimeout(function() { banner.style.display = "none"; }, 300);

			var date = new Date();
			date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000));
			document.cookie = "rd_lgpd_accepted=true; expires=" + date.toUTCString() + "; path=/";
		});
	}
});

/* CONTROLE DE LAYOUT DA BUSCA — AJAX Redistribuição (Fase 5.5)
 *
 * Em vez de só esconder seções, dispara AJAX pra redistribuir os
 * resultados da página atual entre os layouts ativos. Compact funciona
 * como bucket de overflow + safety net (lógica no backend).
 *
 * Quando visitor está em página > 1 e clica chip, navega via full
 * reload pra page 1 (evita pagination dessincronizada). Quando já
 * está em page 1, AJAX puro.
 */
document.addEventListener('DOMContentLoaded', function() {
    const togglesContainer = document.getElementById('rd-search-toggles');
    const resultsContainer = document.querySelector('.rd-search-results-containers');

    // Sai cedo se: fora da página de busca, sem chips, ou sem dados localizados
    if (!togglesContainer || !resultsContainer || typeof rd_search_data === 'undefined') return;

    const chips = togglesContainer.querySelectorAll('.rd-chip');
    const STORAGE_KEY = 'rd_search_prefs';

    function loadPrefs() {
        try {
            const raw = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
            // Migração: formato antigo 'rd-wrap-{layout}' → '{layout}' puro
            const migrated = {};
            let needsSave = false;
            for (const k in raw) {
                if (k.indexOf('rd-wrap-') === 0) {
                    migrated[k.replace('rd-wrap-', '')] = raw[k];
                    needsSave = true;
                } else {
                    migrated[k] = raw[k];
                }
            }
            if (needsSave) savePrefs(migrated);
            return migrated;
        } catch (e) {
            return {};
        }
    }

    function savePrefs(prefs) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (e) { /* cheio ou bloqueado — silencia */ }
    }

    function getLayoutFromChip(chip) {
        // data-target="rd-wrap-{layout}" — extrai só o nome do layout
        return chip.getAttribute('data-target').replace('rd-wrap-', '');
    }

    function getActiveLayouts() {
        const active = [];
        chips.forEach(chip => {
            if (chip.classList.contains('active')) {
                active.push(getLayoutFromChip(chip));
            }
        });
        return active;
    }

    function applyChipState(prefs) {
        chips.forEach(chip => {
            const layout = getLayoutFromChip(chip);
            if (prefs[layout] === false) {
                chip.classList.remove('active');
            } else {
                chip.classList.add('active');
            }
        });
    }

    function setLoading(isLoading) {
        resultsContainer.classList.toggle('rd-loading', isLoading);
    }

    function redistribute(paged) {
        const active = getActiveLayouts();
        const body = new URLSearchParams({
            action: 'rd_search_redistribute',
            nonce: rd_search_data.nonce,
            search_query: rd_search_data.query,
            active_layouts: active.join(','),
            paged: paged || 1
        });

        setLoading(true);

        return fetch(rd_search_data.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success && data.data) {
                resultsContainer.innerHTML = data.data.html;
            } else {
                console.error('rd_search_redistribute failed:', data);
            }
        })
        .catch(err => {
            console.error('rd_search_redistribute error:', err);
        })
        .finally(() => {
            setLoading(false);
        });
    }

    // Detecta página atual considerando AMBOS formatos do WP:
    //   - Pretty permalink: /page/N/ no path
    //   - Query string:     ?paged=N
    function getCurrentPaged() {
        const url = new URL(window.location);
        const pathMatch = url.pathname.match(/\/page\/(\d+)\/?$/);
        if (pathMatch) return parseInt(pathMatch[1], 10);
        const q = parseInt(url.searchParams.get('paged'), 10);
        return q > 0 ? q : 1;
    }

    // Constrói URL pra page 1 removendo /page/N/ E ?paged=
    function urlForPage1() {
        const url = new URL(window.location);
        url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
        url.searchParams.delete('paged');
        return url.toString();
    }

    // INITIAL: aplica estado dos chips + redistribui se visitor tem prefs
    // diferentes do default (preservando paged atual)
    const savedPrefs = loadPrefs();
    if (Object.keys(savedPrefs).length > 0) {
        applyChipState(savedPrefs);
        const hasInactive = Array.from(chips).some(c => !c.classList.contains('active'));
        if (hasInactive) {
            redistribute(getCurrentPaged());
        }
    }

    // CLICK: toggle visual → salva prefs → redistribui (AJAX ou full reload)
    chips.forEach(chip => {
        chip.addEventListener('click', function() {
            this.classList.toggle('active');

            const prefs = loadPrefs();
            prefs[getLayoutFromChip(this)] = this.classList.contains('active');
            savePrefs(prefs);

            if (getCurrentPaged() > 1) {
                // Página > 1 → full reload pra page 1 (pagination sincroniza naturalmente)
                // Próxima carga: JS lê prefs e aplica via initial AJAX
                window.location.href = urlForPage1();
            } else {
                // Já em page 1 → AJAX puro
                redistribute(1);
            }
        });
    });
});

/* DARK/LIGHT MODE TOGGLE */
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('rd-theme-toggle');
    if (!themeToggle) return;

    themeToggle.addEventListener('click', function() {
        // Lê e escreve no <html> (documentElement) — consistente com o
        // script anti-FOUC que roda no <head>.
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('rd-theme', newTheme);
    });
});
