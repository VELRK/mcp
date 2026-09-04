(function () {
	var timers = [];

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function later(ms, fn) {
		timers.push(setTimeout(fn, ms));
	}

	function clearTimers() {
		timers.forEach(clearTimeout);
		timers = [];
	}

	function resetScene(modal) {
		qsa('.is-in, .is-out, .is-on, .is-done', modal).forEach(function (el) {
			el.classList.remove('is-in', 'is-out', 'is-on', 'is-done');
		});
		qsa('[data-type]', modal).forEach(function (el) {
			el.textContent = '';
		});
		var bar = qs('.site-hiw-progress span', modal);
		if (bar) {
			bar.style.transition = 'none';
			bar.style.width = '0%';
			void bar.offsetWidth;
			bar.style.transition = '';
			bar.style.width = '';
		}
	}

	function show(el) {
		if (el) {
			el.classList.add('is-in');
			if (el.scrollIntoView) {
				el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
			}
		}
	}

	function hide(el) {
		if (el) {
			el.classList.add('is-out');
			el.classList.remove('is-in');
		}
	}

	function typeText(el, text, speed, done) {
		if (!el) {
			if (done) {
				done();
			}
			return;
		}
		el.textContent = '';
		var i = 0;
		function tick() {
			el.textContent = text.slice(0, i);
			i += 1;
			if (i <= text.length) {
				later(speed, tick);
			} else if (done) {
				done();
			}
		}
		tick();
	}

	function setStep(modal, n) {
		qsa('.site-hiw-step', modal).forEach(function (step, idx) {
			step.classList.remove('is-on');
			if (idx + 1 < n) {
				step.classList.add('is-done');
			}
			if (idx + 1 === n) {
				step.classList.add('is-on');
			}
		});
	}

	function setChip(modal, name) {
		qsa('.site-hiw-chip', modal).forEach(function (chip) {
			chip.classList.toggle('is-on', chip.getAttribute('data-chip') === name);
		});
	}

	function replayHiw() {
		var modal = qs('#site-how-it-works');
		if (!modal) {
			return;
		}
		clearTimers();
		modal.classList.remove('is-playing');
		resetScene(modal);
		void modal.offsetWidth;
		modal.classList.add('is-playing');

		var b1 = qs('.site-hiw-b1', modal);
		var b2 = qs('.site-hiw-b2', modal);
		var b3 = qs('.site-hiw-b3', modal);
		var b4 = qs('.site-hiw-b4', modal);
		var typing = qs('.site-hiw-typing', modal);
		var product = qs('.site-hiw-product', modal);
		var pay = qs('.site-hiw-pay', modal);
		var paid = qs('.site-hiw-paid', modal);
		var invoice = qs('.site-hiw-invoice', modal);
		var tag = qs('.site-hiw-tag', modal);
		var typeTarget = qs('[data-type]', modal);

		later(180, function () {
			setChip(modal, 'whatsapp');
			setStep(modal, 1);
			show(b1);
		});
		later(1100, function () {
			show(typing);
			setStep(modal, 2);
		});
		later(2200, function () {
			hide(typing);
			show(b2);
			typeText(typeTarget, typeTarget ? typeTarget.getAttribute('data-type') : '', 16);
		});
		later(5600, function () {
			show(product);
		});
		later(6800, function () {
			setStep(modal, 3);
			show(b3);
		});
		later(7900, function () {
			show(pay);
		});
		later(9200, function () {
			setStep(modal, 4);
			show(paid);
		});
		later(10400, function () {
			setStep(modal, 5);
			show(b4);
		});
		later(11600, function () {
			show(invoice);
		});
		later(12600, function () {
			show(tag);
		});
	}

	function openHiw() {
		var modal = qs('#site-how-it-works');
		if (!modal) {
			return;
		}
		modal.classList.add('is-open');
		modal.setAttribute('aria-hidden', 'false');
		document.body.style.overflow = 'hidden';
		replayHiw();
	}

	function closeHiw() {
		var modal = qs('#site-how-it-works');
		if (!modal) {
			return;
		}
		clearTimers();
		modal.classList.remove('is-open', 'is-playing');
		modal.setAttribute('aria-hidden', 'true');
		document.body.style.overflow = '';
	}

	function isVideoTrigger(el) {
		if (!el || !el.closest) {
			return null;
		}
		return el.closest('.video-btn, .popup-videos, a.play__btn, .tp-el-video-play-btn');
	}

	document.addEventListener('click', function (e) {
		var trigger = isVideoTrigger(e.target);
		if (trigger) {
			e.preventDefault();
			e.stopPropagation();
			openHiw();
		}
	}, true);

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			closeHiw();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		var modal = qs('#site-how-it-works');
		if (!modal) {
			return;
		}
		var overlay = qs('.site-hiw-overlay', modal);
		var closeBtn = qs('.site-hiw-close', modal);
		var replayBtn = qs('.site-hiw-replay', modal);
		if (overlay) {
			overlay.addEventListener('click', closeHiw);
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', closeHiw);
		}
		if (replayBtn) {
			replayBtn.addEventListener('click', replayHiw);
		}
		if (window.jQuery && jQuery.fn && jQuery.fn.magnificPopup) {
			try {
				jQuery('.video-btn, .popup-videos, a.play__btn').off('click');
			} catch (err) {}
		}
	});
})();
