/* Auhof Schulseiten – Diashow im Backend: Bilder aus der Mediathek wählen, ordnen, entfernen. */
(function ($) {
	'use strict';
	var liste = document.getElementById('auhof-dia-bilder');
	if (!liste || !window.wp || !wp.media) return;
	var vorlage = liste.querySelector('li[hidden]');
	var rahmen = null;

	$(liste).sortable({ items: '> li:not([hidden])', cursor: 'move', placeholder: 'auhof-dia-platz', forcePlaceholderSize: true });

	function neu(anhang) {
		var li = vorlage.cloneNode(true);
		li.hidden = false;
		Array.prototype.forEach.call(li.querySelectorAll('input, select'), function (f) { f.disabled = false; });
		li.querySelector('input[type=hidden]').value = String(anhang.id);
		var bild = li.querySelector('img');
		var groesse = (anhang.sizes && (anhang.sizes.medium || anhang.sizes.thumbnail)) || anhang;
		bild.src = groesse.url;
		bild.alt = '';
		if (!anhang.alt) {
			var a = document.createElement('a');
			a.className = 'auhof-dia-bild__hinweis';
			a.href = anhang.editLink || '#';
			a.textContent = auhofDiashow.altFehlt;
			li.appendChild(a);
		}
		liste.appendChild(li);
	}

	document.getElementById('auhof-dia-hinzu').addEventListener('click', function () {
		if (!rahmen) {
			rahmen = wp.media({ title: auhofDiashow.titel, button: { text: auhofDiashow.knopf }, library: { type: 'image' }, multiple: 'add' });
			rahmen.on('select', function () {
				rahmen.state().get('selection').each(function (m) { neu(m.toJSON()); });
			});
		}
		rahmen.open();
	});

	// Nach vorne / nach hinten / entfernen – auch per Tastatur bedienbar.
	liste.addEventListener('click', function (e) {
		var knopf = e.target.closest('button');
		if (!knopf) return;
		var li = knopf.closest('li');
		if (knopf.classList.contains('auhof-dia-weg')) {
			var naechstes = li.nextElementSibling || li.previousElementSibling;
			li.remove();
			if (naechstes && !naechstes.hidden) naechstes.querySelector('button').focus();
			else document.getElementById('auhof-dia-hinzu').focus();
		} else if (knopf.classList.contains('auhof-dia-hoch')) {
			var vor = li.previousElementSibling;
			if (vor && !vor.hidden) { liste.insertBefore(li, vor); knopf.focus(); }
		} else if (knopf.classList.contains('auhof-dia-runter')) {
			var nach = li.nextElementSibling;
			if (nach) { liste.insertBefore(nach, li); knopf.focus(); }
		}
	});
})(jQuery);
