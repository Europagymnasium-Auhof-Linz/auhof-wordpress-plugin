/* Auhof Schulseiten – Raumplan im Backend: Feld und Plan miteinander verbinden. */
(function () {
	'use strict';
	var root = document.getElementById('raumplan');
	if (!root) return;
	var felder = document.querySelectorAll('.auhof-rp-felder input[data-i]');
	function feld(i) { return document.querySelector('.auhof-rp-felder input[data-i="' + i + '"]'); }
	function aktiv(input) {
		Array.prototype.forEach.call(felder, function (f) { f.classList.toggle('ist-aktiv', f === input); });
	}
	Array.prototype.forEach.call(felder, function (input) {
		input.addEventListener('focus', function () {
			aktiv(input);
			root.dispatchEvent(new CustomEvent('auhof-raum-markieren', { detail: +input.getAttribute('data-i') }));
		});
		input.addEventListener('input', function () {
			if (root.auhofUmbenennen) root.auhofUmbenennen(+input.getAttribute('data-i'), input.value.trim() || input.placeholder);
		});
	});
	root.addEventListener('auhof-raum-gewaehlt', function (e) {
		var input = feld(e.detail);
		if (!input || input === document.activeElement) return;
		aktiv(input);
		input.focus({ preventScroll: true });
		input.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		input.select();
	});
})();
