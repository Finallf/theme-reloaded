document.addEventListener('DOMContentLoaded', function () {

	/* Voltar ao topo */
	const backToTopBtn = document.getElementById("back-to-top");

	if (backToTopBtn) {
		// `passive: true` informa ao browser que o handler não vai prevenir o scroll,
		// permitindo otimização. requestAnimationFrame agrupa o write na próxima frame,
		// evitando layout thrashing quando o scroll dispara dezenas de vezes por segundo
		// e cada add/remove de classe invalida o style cache.
		let scrollTicking = false;
		window.addEventListener("scroll", function() {
			if (scrollTicking) return;
			scrollTicking = true;
			window.requestAnimationFrame(function() {
				const scrollPosition = window.scrollY || document.documentElement.scrollTop;
				if (scrollPosition > 300) {
					backToTopBtn.classList.add("show");
				} else {
					backToTopBtn.classList.remove("show");
				}
				scrollTicking = false;
			});
		}, { passive: true });

		backToTopBtn.addEventListener("click", function() {
			window.scrollTo({
				top: 0,
				behavior: "smooth"
			});
		});
	}

	/* MENU (HAMBÚRGUER) + BUSCA INTEGRADA NO PAINEL */
	const menuPanel       = document.getElementById('primary-menu-panel');
	const menuToggleBtn   = document.querySelector('.menu-toggle');
	const searchToggleBtn = document.querySelector('.menu-search-toggle');
	const menuSearchInput = document.querySelector('.menu-search-field');

	function openMenuPanel(focusSearch) {
		if (!menuPanel) return;
		menuPanel.classList.add('toggled');
		if (menuToggleBtn) {
			menuToggleBtn.innerHTML = '<span class="hamburger-icon" aria-hidden="true">&#10006;</span>';
			menuToggleBtn.setAttribute('aria-expanded', 'true');
			menuToggleBtn.setAttribute('aria-label', 'Fechar menu de navegação');
		}
		if (focusSearch && menuSearchInput) {
			// Pequeno delay pra esperar a transição do painel terminar
			setTimeout(function () { menuSearchInput.focus(); }, 250);
		}
	}

	function closeMenuPanel() {
		if (!menuPanel) return;
		menuPanel.classList.remove('toggled');
		if (menuToggleBtn) {
			menuToggleBtn.innerHTML = '<span class="hamburger-icon" aria-hidden="true">&#9776;</span>';
			menuToggleBtn.setAttribute('aria-expanded', 'false');
			menuToggleBtn.setAttribute('aria-label', 'Abrir menu de navegação');
		}
	}

	// Expor pra outros handlers (ex.: o trigger do 404)
	window.rdOpenMenuPanel  = openMenuPanel;
	window.rdCloseMenuPanel = closeMenuPanel;

	if (menuPanel && menuToggleBtn) {
		menuToggleBtn.onclick = function () {
			if (menuPanel.classList.contains('toggled')) {
				closeMenuPanel();
			} else {
				openMenuPanel(false);
			}
		};
	}

	if (searchToggleBtn) {
		searchToggleBtn.addEventListener('click', function () {
			openMenuPanel(true);
		});
	}

	// Resize → fecha o painel se passar pra desktop (>1440px),
	// evitando que ele apareça aberto se o usuário voltar pro tablet/mobile.
	// Também adiciona .rd-resizing no <body> durante o resize pra
	// neutralizar transitions globalmente — evita o "flash" do .menu-panel
	// quando troca de `display: contents` (desktop) pra `display: block`
	// (mobile/tablet) e a transition de transform dispara visualmente.
	// Debounce simples pra não rodar centenas de vezes.
	let resizeTimer;
	window.addEventListener('resize', function () {
		document.body.classList.add('rd-resizing');
		clearTimeout(resizeTimer);
		resizeTimer = setTimeout(function () {
			document.body.classList.remove('rd-resizing');
			if (window.innerWidth > 1440 && menuPanel && menuPanel.classList.contains('toggled')) {
				closeMenuPanel();
			}
		}, 150);
	});

	/* FACHADAS(Facades) (YOUTUBE, DISCORD, ETC.) - OTIMIZADO */
	const facades = document.querySelectorAll('.rd-facade');
	const isDiscordOpen = sessionStorage.getItem('rd_discord_open');

	facades.forEach(function (facade) {
		const id = facade.getAttribute('data-id');
		const type = facade.getAttribute('data-type');

		if (type === 'discord' && isDiscordOpen === 'true') {
			const iframeUrl = `https://ptb.discord.com/widget?id=${id}&theme=dark`;
			// loading="lazy" defensivo: session-restore pode acontecer com sidebar fora da viewport
			facade.innerHTML = `<iframe src="${iframeUrl}" width="100%" height="500" allowtransparency="true" frameborder="0" loading="lazy" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>`;
			facade.style.cursor = 'default';
			return;
		}

		facade.addEventListener('click', function () {
			let iframeUrl = '';
			let iframeHtml = '';

			if (type === 'youtube') {
				// Timestamp preservado do oembed original (PHP parseou `?t=Xh Ym Zs` → segundos no data-t)
				const t = parseInt(facade.getAttribute('data-t'), 10);
				const startParam = (t > 0) ? `&start=${t}` : '';
				iframeUrl = `https://www.youtube.com/embed/${id}?autoplay=1&rel=0${startParam}`;
				// loading="lazy" mais por consistência — no click o iframe já tá in-viewport
				iframeHtml = `<iframe src="${iframeUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" style="width:100%; height:100%; position:absolute; top:0; left:0;"></iframe>`;
			}
			else if (type === 'discord') {
				iframeUrl = `https://ptb.discord.com/widget?id=${id}&theme=dark`;
				iframeHtml = `<iframe src="${iframeUrl}" width="100%" height="500" allowtransparency="true" frameborder="0" loading="lazy" sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>`;
				sessionStorage.setItem('rd_discord_open', 'true');
			}

			if (iframeUrl !== '') {
				this.innerHTML = iframeHtml;
				this.style.cursor = 'default';
			}
		});
	});

    /* ============================================================
       COPY TO CLIPBOARD — função genérica + auto-handler

       Uso programático (de qualquer JS):
           window.rdCopyToClipboard('texto', { feedbackEl, feedbackText, revertAfter, onSuccess, onError });

       Uso declarativo (zero JS):
           <button data-rd-copy="valor a copiar"
                   data-rd-copy-feedback=".meu-span"  (opcional — selector ou 'self')
                   data-rd-copy-text="Copiado!"        (opcional — sobrescreve reloaded_i18n.copied)
                   data-rd-copy-revert="2000">         (opcional — ms até voltar ao texto original)
               <span class="rd-copy-text">valor visível</span>
           </button>
       ============================================================ */

    // 1. Função utility — exposta globalmente pra uso de qualquer feature
    window.rdCopyToClipboard = function (text, options) {
        options = options || {};
        var feedbackText = (options.feedbackText !== undefined)
            ? options.feedbackText
            : (typeof reloaded_i18n !== 'undefined' ? reloaded_i18n.copied : 'Copied!');
        var feedbackClass = options.feedbackClass || 'copied';
        var revertAfter   = options.revertAfter   || 2000;

        // Resolve feedbackEl — pode ser elemento DOM ou string-selector
        var feedbackEl = null;
        if (options.feedbackEl) {
            feedbackEl = (typeof options.feedbackEl === 'string')
                ? document.querySelector(options.feedbackEl)
                : options.feedbackEl;
        }

        return navigator.clipboard.writeText(text).then(function () {
            if (feedbackEl && feedbackText !== null) {
                var originalText = feedbackEl.innerText;
                feedbackEl.innerText = feedbackText;
                feedbackEl.classList.add(feedbackClass);
                setTimeout(function () {
                    feedbackEl.innerText = originalText;
                    feedbackEl.classList.remove(feedbackClass);
                }, revertAfter);
            }
            if (typeof options.onSuccess === 'function') options.onSuccess();
            return true;
        }).catch(function (err) {
            var errMsg = (typeof reloaded_i18n !== 'undefined') ? reloaded_i18n.copy_error : 'Copy failed:';
            console.error(errMsg, err);
            if (typeof options.onError === 'function') options.onError(err);
            return false;
        });
    };

    // 2. Auto-handler — qualquer elemento com [data-rd-copy] é tratado automaticamente.
    //    O feedback vai pro elemento apontado em data-rd-copy-feedback (selector),
    //    ou 'self' (o próprio botão), ou o primeiro .rd-copy-text descendente como fallback.
    document.querySelectorAll('[data-rd-copy]').forEach(function (el) {
        el.addEventListener('click', function () {
            var text = this.getAttribute('data-rd-copy');
            if (!text) return;

            var feedbackSelector = this.getAttribute('data-rd-copy-feedback');
            var feedbackEl = null;
            if (feedbackSelector === 'self') {
                feedbackEl = this;
            } else if (feedbackSelector) {
                feedbackEl = this.querySelector(feedbackSelector) || document.querySelector(feedbackSelector);
            } else {
                // Fallback: .rd-copy-text dentro do botão
                feedbackEl = this.querySelector('.rd-copy-text');
            }

            var customText   = this.getAttribute('data-rd-copy-text');
            var revertAttr   = parseInt(this.getAttribute('data-rd-copy-revert'), 10);

            // O contêiner do botão fica com a classe `.copied` (pra estilizações como mudar bg)
            var self = this;
            window.rdCopyToClipboard(text, {
                feedbackEl:   feedbackEl,
                feedbackText: customText || undefined,
                revertAfter:  isNaN(revertAttr) ? 2000 : revertAttr,
                onSuccess: function () { self.classList.add('copied'); },
            });

            // Remove a classe do botão depois do mesmo intervalo
            setTimeout(function () { self.classList.remove('copied'); }, isNaN(revertAttr) ? 2000 : revertAttr);
        });
    });

	/* PRISM - Adiciona line-numbers em todos os <pre> */
	document.querySelectorAll('pre').forEach(block => block.classList.add('line-numbers'));

	// ============================================================
	// AJAX comment submit — evita reload completo da página
	// ============================================================
	// Comportamento:
	//   1. Captura submit do form #commentform, previne envio nativo
	//   2. Envia FormData via fetch pra /wp-comments-post.php
	//   3. WP processa (Akismet, validations, save) e retorna 302
	//      redirect (success) ou 200 com HTML de erro
	//   4. Sucesso → mostra feedback + soft reload pra mostrar o
	//      comment recém-criado (com a posição/anchor correta)
	//   5. Erro → mostra mensagem extraída + form fica editável
	// ============================================================
	const commentForm = document.getElementById('commentform');
	if (commentForm) {
		const submitBtn = commentForm.querySelector('.submit, input[type="submit"], button[type="submit"]');
		const formActions = commentForm.querySelector('.form-submit') || commentForm;

		// Helper: cria/atualiza o bloco de feedback
		function showFeedback(type, message) {
			let box = commentForm.querySelector('.rd-comment-feedback');
			if (!box) {
				box = document.createElement('div');
				box.className = 'rd-comment-feedback';
				formActions.appendChild(box);
			}
			box.className = 'rd-comment-feedback is-' + type;
			box.textContent = message;
		}

		commentForm.addEventListener('submit', async function (e) {
			// Só ataca se o form ainda for o nativo WP — se outro plugin já
			// estiver controlando (Discourse, Disqus etc), não interfere.
			if (!commentForm.action || commentForm.action.indexOf('wp-comments-post.php') === -1) {
				return;
			}

			e.preventDefault();

			const originalLabel = submitBtn ? (submitBtn.value || submitBtn.textContent) : '';
			if (submitBtn) {
				submitBtn.disabled = true;
				if ('value' in submitBtn) submitBtn.value = 'Enviando…';
				else submitBtn.textContent = 'Enviando…';
			}

			try {
				// `redirect: 'manual'` — NÃO seguimos o 302 automaticamente.
				// Quando WP retorna 302 (= comment criado com sucesso), o fetch
				// devolve response.type === 'opaqueredirect'. Se seguirmos
				// (redirect: 'follow'), o fragmento #comment-XXX do Location
				// header se perde (HTTP não envia fragments), e fica impossível
				// distinguir sucesso de qualquer 200 que volte.
				const response = await fetch(commentForm.action, {
					method: 'POST',
					body: new FormData(commentForm),
					credentials: 'same-origin',
					redirect: 'manual',
				});

				// Redirect = comment criado. Recarrega a página atual pro user
				// ver o comment na lista (a página já tem ele no DB).
				if (response.type === 'opaqueredirect') {
					showFeedback('success', 'Comentário enviado! Carregando…');
					setTimeout(function () { window.location.reload(); }, 600);
					return;
				}

				// Não foi redirect → WP devolveu erro (400/403/500) ou wp_die.
				// Tenta extrair a mensagem do wrapper #error-page ou .wp-die-message.
				const html = await response.text();

				let errMsg = '';
				const dieMatch = html.match(/<div[^>]*id=["']error-page["'][^>]*>([\s\S]*?)<\/div>\s*<\/body>/i)
					|| html.match(/<div[^>]*class=["'][^"']*wp-die-message[^"']*["'][^>]*>([\s\S]*?)<\/div>/i);

				if (dieMatch) {
					const pMatch = dieMatch[1].match(/<p[^>]*>([\s\S]*?)<\/p>/i);
					if (pMatch) errMsg = pMatch[1].replace(/<[^>]+>/g, '').trim();
				}

				if (!errMsg) {
					errMsg = 'Erro inesperado ao enviar comentário. Recarregue a página e tente novamente.';
				}

				// "moderation" / "aprovação" são casos de sucesso-com-aviso
				const lower = errMsg.toLowerCase();
				if (lower.indexOf('moderation') !== -1 || lower.indexOf('aprovação') !== -1) {
					showFeedback('success', errMsg);
					setTimeout(function () { window.location.reload(); }, 1500);
				} else {
					showFeedback('error', errMsg);
				}
			} catch (err) {
				showFeedback('error', 'Erro de rede: ' + err.message);
			} finally {
				if (submitBtn) {
					submitBtn.disabled = false;
					if ('value' in submitBtn) submitBtn.value = originalLabel;
					else submitBtn.textContent = originalLabel;
				}
			}
		});
	}

});


// ============================================================
// LGPD — Consent granular (banner expansível + cookie JSON)
// ============================================================
// Fluxo:
//   1. Banner renderiza em modo "compact" (texto + 3 botões)
//   2. User clica "Personalizar" → banner expande, toggles aparecem
//   3. User clica "Salvar/Rejeitar/Aceitar" → grava cookie JSON +
//      apaga cookie legacy + dispara soft reload pra scripts
//      gated lerem o novo consent
//   4. Link "Cookie Preferences" no footer → apaga cookie e reload
// ============================================================
document.addEventListener("DOMContentLoaded", function () {

    const banner = document.getElementById("rd-lgpd-banner");

    // ------ Função utilitária: grava cookie JSON do consent ------
    function rdLgpdSaveConsent(analytics, marketing) {
        const data = {
            necessary: true,
            analytics: !!analytics,
            marketing: !!marketing,
            date: new Date().toISOString().slice(0, 10),
            version: 1
        };
        const expires = new Date();
        expires.setTime(expires.getTime() + 365 * 24 * 60 * 60 * 1000);
        const secure = location.protocol === "https:" ? "; Secure" : "";
        document.cookie =
            "rd_lgpd_consent=" + encodeURIComponent(JSON.stringify(data)) +
            "; expires=" + expires.toUTCString() +
            "; path=/; SameSite=Lax" + secure;

        // Limpa o cookie legacy (banner antigo "1 botão só") se ainda existir
        document.cookie = "rd_lgpd_accepted=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
    }

    // ------ Função utilitária: anima saída do banner e recarrega ------
    function rdLgpdCloseAndReload() {
        if (!banner) {
            location.reload();
            return;
        }
        banner.classList.add("rd-lgpd-closing");
        // 350ms = transição do CSS + folga; depois reload pra scripts gated lerem o novo consent
        setTimeout(function () { location.reload(); }, 350);
    }

    // ------ Handlers do banner (quando renderizado) ------
    if (banner) {
        const btnReject    = document.getElementById("rd-lgpd-reject");
        const btnCustomize = document.getElementById("rd-lgpd-customize");
        const btnAccept    = document.getElementById("rd-lgpd-accept");
        const checkAnalytics = document.getElementById("rd-lgpd-analytics");
        const checkMarketing = document.getElementById("rd-lgpd-marketing");

        // "Rejeitar tudo" — analytics + marketing = false (necessary fica true sempre)
        if (btnReject) {
            btnReject.addEventListener("click", function () {
                rdLgpdSaveConsent(false, false);
                rdLgpdCloseAndReload();
            });
        }

        // "Aceitar tudo" — analytics + marketing = true
        if (btnAccept) {
            btnAccept.addEventListener("click", function () {
                rdLgpdSaveConsent(true, true);
                rdLgpdCloseAndReload();
            });
        }

        // "Personalizar" / "Salvar preferências" — comportamento dual:
        //   Primeira click: expande banner pra mostrar toggles
        //   Segunda click (após expand): salva consent com base nos toggles
        if (btnCustomize) {
            btnCustomize.addEventListener("click", function () {
                const isExpanded = banner.getAttribute("data-state") === "expanded";

                if (!isExpanded) {
                    // Modo expanded — mostra toggles e troca label do botão.
                    // `inert` removido pra ativar tab order + screen readers nos
                    // checkboxes (resolve fail do Lighthouse "aria-hidden com
                    // descendentes focáveis").
                    banner.setAttribute("data-state", "expanded");
                    const optionsBlock = banner.querySelector(".rd-lgpd-options");
                    if (optionsBlock) optionsBlock.removeAttribute("inert");
                    btnCustomize.textContent = btnCustomize.getAttribute("data-label-expanded") || "Save";
                } else {
                    // Já expandido — salva com base nos toggles
                    const a = checkAnalytics ? checkAnalytics.checked : false;
                    const m = checkMarketing ? checkMarketing.checked : false;
                    rdLgpdSaveConsent(a, m);
                    rdLgpdCloseAndReload();
                }
            });
        }
    }

    // ------ Link "Cookie Preferences" no footer ------
    // Não deleta cookie, não recarrega. Apenas:
    //   1. Revela o banner (que já estava no DOM, oculto pela classe .rd-lgpd-hidden)
    //   2. Expande direto pro modo de toggles (user veio do "Customize", já quer ver)
    //   3. Toggles já vêm pré-marcados pelo PHP com os valores atuais do cookie
    // O reload só acontece quando o user salvar a NOVA escolha.
    const reopenLink = document.getElementById("rd-lgpd-reopen");
    if (reopenLink && banner) {
        reopenLink.addEventListener("click", function (e) {
            e.preventDefault();

            banner.classList.remove("rd-lgpd-hidden");
            banner.setAttribute("data-state", "expanded");

            const optionsBlock = banner.querySelector(".rd-lgpd-options");
            if (optionsBlock) optionsBlock.removeAttribute("inert");

            const btnCustomizeReopen = document.getElementById("rd-lgpd-customize");
            if (btnCustomizeReopen) {
                btnCustomizeReopen.textContent =
                    btnCustomizeReopen.getAttribute("data-label-expanded") || "Save";
            }
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
            const isActive = prefs[layout] !== false; // default true
            chip.classList.toggle('active', isActive);
            chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
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
            const isActive = this.classList.contains('active');
            this.setAttribute('aria-pressed', isActive ? 'true' : 'false');

            const prefs = loadPrefs();
            prefs[getLayoutFromChip(this)] = isActive;
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

    // KEYBOARD NAV: setas left/right pra navegar entre chips, Home/End pra
    // primeiro/último (padrão WAI-ARIA pra role="toolbar").
    togglesContainer.addEventListener('keydown', function(e) {
        const target = e.target;
        if (!target.classList || !target.classList.contains('rd-chip')) return;

        const chipsArr = Array.from(chips);
        const currentIndex = chipsArr.indexOf(target);
        if (currentIndex === -1) return;

        let nextIndex = -1;

        switch (e.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                nextIndex = (currentIndex + 1) % chipsArr.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                nextIndex = (currentIndex - 1 + chipsArr.length) % chipsArr.length;
                break;
            case 'Home':
                nextIndex = 0;
                break;
            case 'End':
                nextIndex = chipsArr.length - 1;
                break;
            default:
                return; // outras teclas (Space, Enter, Tab) — comportamento nativo do <button>
        }

        e.preventDefault();
        chipsArr[nextIndex].focus();
    });
});

/* AD CLOSE BUTTON (Mobile anchor ad) */
document.addEventListener('DOMContentLoaded', function () {
    const adCloseButtons = document.querySelectorAll('.rd-ad-close');

    adCloseButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const ad = this.closest('.rd-ad-mobile');
            if (!ad) return;
            ad.style.opacity = '0';
            setTimeout(function () { ad.style.display = 'none'; }, 300);
        });
    });
});

/* 404 PAGE — "Buscar conteúdo" foca a busca apropriada do breakpoint atual */
document.addEventListener('DOMContentLoaded', function () {
    const trigger = document.getElementById('rd-404-search-trigger');
    if (!trigger) return;

    trigger.addEventListener('click', function () {
        const desktopSearch = document.querySelector('.header-search-container .search-field');

        // ≥1441px: busca expansível do header visível → foca direto
        if (desktopSearch && desktopSearch.offsetParent !== null) {
            desktopSearch.focus();
            desktopSearch.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // ≤1440px: abre o painel do hambúrguer e foca a busca interna
        if (typeof window.rdOpenMenuPanel === 'function') {
            window.rdOpenMenuPanel(true);
        }
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
