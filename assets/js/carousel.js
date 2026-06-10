/**
 * Featured Carousel — autoplay, arrows, dots and pause logic on top of the
 * native CSS scroll-snap track (mod-carousel.php renders the markup; the
 * no-JS fallback is a plain swipeable snap row with the controls hidden).
 *
 * Pause matrix (battery/CPU friendly, matches the theme's philosophy):
 *   - hover or keyboard focus inside the carousel  → pause, resume on leave
 *   - user grabs the track (pointerdown)           → pause, resume on release
 *   - tab in background (visibilitychange)         → pause
 *   - carousel off-screen (IntersectionObserver)   → pause
 *   - prefers-reduced-motion                       → autoplay never starts
 */
(function () {
	'use strict';

	function initCarousel(root) {
		var track = root.querySelector('[data-rd-track]');
		if (!track) return;

		var slides = Array.prototype.slice.call(track.children);
		var dots = Array.prototype.slice.call(root.querySelectorAll('.rd-carousel__dot'));
		var prevBtn = root.querySelector('.rd-carousel__arrow--prev');
		var nextBtn = root.querySelector('.rd-carousel__arrow--next');

		var motionOK = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var interval = parseInt(root.getAttribute('data-interval'), 10) || 0;
		var index = 0;
		var timer = null;
		var inView = true;

		// Single slide: nothing to rotate — just reveal and bail.
		if (slides.length < 2) {
			root.classList.add('is-ready');
			return;
		}

		function goTo(i) {
			// Rewind in both directions (no DOM cloning / infinite loop).
			index = (i + slides.length) % slides.length;
			var slide = slides[index];
			var left = slide.offsetLeft - (track.clientWidth - slide.clientWidth) / 2;
			track.scrollTo({ left: left, behavior: motionOK ? 'smooth' : 'auto' });
		}

		// Active slide = the one whose center is closest to the track center.
		// Driven by the scroll position so swipe, arrows and autoplay all
		// converge on the same source of truth.
		function syncFromScroll() {
			var center = track.scrollLeft + track.clientWidth / 2;
			var best = 0;
			var bestDist = Infinity;
			slides.forEach(function (slide, i) {
				var d = Math.abs(slide.offsetLeft + slide.clientWidth / 2 - center);
				if (d < bestDist) { bestDist = d; best = i; }
			});
			index = best;
			dots.forEach(function (dot, i) {
				if (i === index) {
					dot.setAttribute('aria-current', 'true');
				} else {
					dot.removeAttribute('aria-current');
				}
			});
		}

		var scrollDebounce;
		track.addEventListener('scroll', function () {
			clearTimeout(scrollDebounce);
			scrollDebounce = setTimeout(syncFromScroll, 120);
		}, { passive: true });

		/* --- Autoplay --- */
		function start() {
			if (!motionOK || !interval || timer || !inView || document.hidden) return;
			timer = setInterval(function () { goTo(index + 1); }, interval * 1000);
		}
		function stop() {
			clearInterval(timer);
			timer = null;
		}

		/* --- Controls --- */
		if (prevBtn) {
			prevBtn.addEventListener('click', function () { stop(); goTo(index - 1); start(); });
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', function () { stop(); goTo(index + 1); start(); });
		}
		dots.forEach(function (dot) {
			dot.addEventListener('click', function () {
				stop();
				goTo(parseInt(dot.getAttribute('data-rd-goto'), 10) || 0);
				start();
			});
		});

		// Keyboard: arrows navigate while focus is inside the carousel.
		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') { e.preventDefault(); stop(); goTo(index - 1); start(); }
			if (e.key === 'ArrowRight') { e.preventDefault(); stop(); goTo(index + 1); start(); }
		});

		/* --- Pause matrix --- */
		root.addEventListener('pointerenter', stop);
		root.addEventListener('pointerleave', start);
		root.addEventListener('focusin', stop);
		root.addEventListener('focusout', start);
		track.addEventListener('pointerdown', stop, { passive: true });
		track.addEventListener('pointerup', start, { passive: true });

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) { stop(); } else { start(); }
		});

		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				inView = entries[0].isIntersecting;
				if (inView) { start(); } else { stop(); }
			}, { threshold: 0.25 }).observe(root);
		}

		syncFromScroll();
		root.classList.add('is-ready'); // reveals arrows/dots (CSS gate)
		start();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-rd-carousel]').forEach(initCarousel);
	});
}());
