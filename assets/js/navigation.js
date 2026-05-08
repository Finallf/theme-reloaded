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

	/* 1. MENU (HAMBÚRGUER) */
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

	/* 2. FACHADAS(Facades) (YOUTUBE, DISCORD, ETC.) - OTIMIZADO */
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

	/* 3. PRISM - Adiciona line-numbers em todos os <pre> */
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

/* CONTROLE DE LAYOUT DA BUSCA (Chips & LocalStorage) */
document.addEventListener('DOMContentLoaded', function() {
    const togglesContainer = document.getElementById('rd-search-toggles');

    if (togglesContainer) {
        const chips = togglesContainer.querySelectorAll('.rd-chip');

        // Carrega as preferências salvas
        const savedPrefs = JSON.parse(localStorage.getItem('rd_search_prefs')) || {};

        chips.forEach(chip => {
            const targetId = chip.getAttribute('data-target');
            const wrapper = document.getElementById(targetId);

            // Aplica estado inicial baseado no LocalStorage
            if (savedPrefs[targetId] === false) {
                chip.classList.remove('active');
                if (wrapper) wrapper.style.display = 'none';
            }

            // Lida com o clique
            chip.addEventListener('click', function() {
                const isActive = this.classList.contains('active');

                if (isActive) {
                    this.classList.remove('active');
                    if (wrapper) wrapper.style.display = 'none';
                    savedPrefs[targetId] = false;
                } else {
                    this.classList.add('active');
                    // Remove o inline style e deixa o CSS decidir o display correto
                    // (grid pro layout-grid, flex pros demais)
                    if (wrapper) wrapper.style.removeProperty('display');
                    savedPrefs[targetId] = true;
                }

                localStorage.setItem('rd_search_prefs', JSON.stringify(savedPrefs));
            });
        });
    }
});
