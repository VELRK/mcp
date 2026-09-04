(function () {
	function on() {
		var root = document.querySelector('.site-story');
		if (!root || !('IntersectionObserver' in window)) {
			document.querySelectorAll('.site-story-card, .site-story-who article').forEach(function (el) {
				el.classList.add('is-on');
			});
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-on');
				}
			});
		}, { threshold: 0.22 });
		root.querySelectorAll('.site-story-card, .site-story-who article').forEach(function (el, i) {
			el.style.transitionDelay = (i % 3) * 0.08 + 's';
			io.observe(el);
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', on);
	} else {
		on();
	}
})();
