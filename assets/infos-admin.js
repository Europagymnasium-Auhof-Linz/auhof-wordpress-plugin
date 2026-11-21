/* Auhof Schulseiten – Aktuelle Infos im Backend: + hinzufügen, − entfernen, ↑/↓ verschieben (auch per Tastatur). */
(function () {
	'use strict';
	var liste = document.getElementById('auhof-info-liste');
	var hinzu = document.getElementById('auhof-info-hinzu');
	var leer = document.getElementById('auhof-info-leer');
	if (!liste || !hinzu) return;
	var vorlage = liste.querySelector('li[hidden]');
	var max = parseInt(liste.getAttribute('data-max'), 10) || 12;
	var zaehler = 0;

	function zeilen() { return liste.querySelectorAll('li:not([hidden])'); }
	function zustand() {
		var n = zeilen().length;
		hinzu.disabled = n >= max;
		if (leer) leer.hidden = n > 0;
	}

	hinzu.addEventListener('click', function () {
		if (zeilen().length >= max) return;
		var nr = 'n' + Date.now().toString(36) + (zaehler++);
		var li = vorlage.cloneNode(true);
		li.hidden = false;
		Array.prototype.forEach.call(li.querySelectorAll('input, textarea, label'), function (f) {
			['name', 'id', 'for'].forEach(function (a) {
				var w = f.getAttribute(a);
				if (w) f.setAttribute(a, w.replace(/__nr__/g, nr));
			});
			if (f.tagName !== 'LABEL') f.disabled = false;
		});
		liste.appendChild(li);
		zustand();
		li.querySelector('input[type=text]').focus();
	});

	liste.addEventListener('click', function (e) {
		var knopf = e.target.closest('button');
		if (!knopf) return;
		var li = knopf.closest('li');
		if (knopf.classList.contains('auhof-info-weg')) {
			var titel = li.querySelector('input[type=text]').value;
			if (titel && !window.confirm('„' + titel + '“ entfernen? Erst mit „Speichern“ wirksam.')) return;
			var naechstes = li.nextElementSibling || li.previousElementSibling;
			li.remove();
			zustand();
			if (naechstes && !naechstes.hidden) naechstes.querySelector('.auhof-info-weg').focus();
			else hinzu.focus();
		} else if (knopf.classList.contains('auhof-info-hoch')) {
			var vor = li.previousElementSibling;
			if (vor && !vor.hidden) { liste.insertBefore(li, vor); knopf.focus(); }
		} else if (knopf.classList.contains('auhof-info-runter')) {
			var nach = li.nextElementSibling;
			if (nach) { liste.insertBefore(nach, li); knopf.focus(); }
		}
	});
	zustand();
})();
