/*
 * Auhof Schulseiten – Diashow (Karussell nach dem WAI-ARIA-Muster).
 * – Pause-/Abspielen-Knopf, Anhalten bei Maus darüber oder Tastaturfokus darin
 * – kein automatisches Wechseln bei „Bewegung reduzieren“
 * – nicht sichtbare Folien sind inert (weder fokussierbar noch für Screenreader sichtbar)
 * – Tempo: Bilder werden erst kurz vor dem Erscheinen geladen; außerhalb des sichtbaren Bereichs,
 *   in einem Hintergrund-Tab und im Datensparmodus wird nichts vorgeladen bzw. nicht weitergeschaltet.
 */
(function () {
	'use strict';
	var wenigBewegung = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var sparen = !!(navigator.connection && navigator.connection.saveData);

	Array.prototype.forEach.call(document.querySelectorAll('.auhof-diashow'), function (box) {
		var folien = Array.prototype.slice.call(box.querySelectorAll('.auhof-diashow__folie'));
		if (folien.length < 2) { return; }
		var liste = box.querySelector('.auhof-diashow__folien');
		var pause = box.querySelector('.auhof-diashow__pause');
		var label = pause.querySelector('.auhof-diashow__label');
		var zaehler = box.querySelector('.auhof-diashow__zaehler');
		var dauer = Math.max(3, parseInt(box.getAttribute('data-sekunden'), 10) || 6) * 1000;
		var aktuell = 0;
		var timer = null;
		var angehalten = wenigBewegung; // von der Person gewählt (oder durch Systemeinstellung)
		var unterbrochen = false;        // vorübergehend: Maus darüber, Fokus darin, Tab im Hintergrund
		var sichtbar = true;             // Diashow im sichtbaren Bereich der Seite?
		var vorladen = false;

		box.classList.add('ist-bereit');

		// Bild einer Folie laden (Adressen stehen bis dahin in data-src/data-srcset, auch bei <source>).
		function lade(folie) {
			if (!folie) { return; }
			Array.prototype.forEach.call(folie.querySelectorAll('[data-srcset], [data-src]'), function (el) {
				if (el.dataset.srcset) { el.srcset = el.dataset.srcset; el.removeAttribute('data-srcset'); }
				if (el.dataset.src) { el.src = el.dataset.src; el.removeAttribute('data-src'); }
				if (el.tagName === 'IMG') { el.loading = 'eager'; }
			});
		}
		function ladeNaechstes() { if (sichtbar && !sparen) { lade(folien[(aktuell + 1) % folien.length]); } }

		function zeige(i) {
			aktuell = (i + folien.length) % folien.length;
			lade(folien[aktuell]);
			folien.forEach(function (f, n) {
				var an = n === aktuell;
				f.classList.toggle('ist-aktiv', an);
				if (an) { f.removeAttribute('aria-hidden'); f.removeAttribute('inert'); }
				else { f.setAttribute('aria-hidden', 'true'); f.setAttribute('inert', ''); }
			});
			zaehler.textContent = (aktuell + 1) + ' / ' + folien.length;
			if (vorladen) { setTimeout(ladeNaechstes, 600); }
		}

		// Das nächste Bild erst laden, wenn die Seite fertig ist und kurz bevor es erscheint.
		function startVorladen() { setTimeout(function () { vorladen = true; ladeNaechstes(); }, 1500); }
		if (document.readyState === 'complete') { startVorladen(); } else { window.addEventListener('load', startVorladen); }

		function laeuft() { return !angehalten && !unterbrochen && sichtbar; }

		function planen() {
			clearTimeout(timer);
			if (laeuft()) { timer = setTimeout(function () { zeige(aktuell + 1); planen(); }, dauer); }
			// Während des automatischen Wechsels keine Ansagen, sonst schon.
			liste.setAttribute('aria-live', laeuft() ? 'off' : 'polite');
		}

		function knopfAnzeigen() {
			pause.setAttribute('aria-pressed', angehalten ? 'true' : 'false');
			label.textContent = angehalten ? 'Abspielen' : 'Pause';
			box.classList.toggle('ist-angehalten', angehalten);
		}

		pause.addEventListener('click', function () { angehalten = !angehalten; knopfAnzeigen(); planen(); });
		box.querySelector('.auhof-diashow__zurueck').addEventListener('click', function () { zeige(aktuell - 1); planen(); });
		box.querySelector('.auhof-diashow__weiter').addEventListener('click', function () { zeige(aktuell + 1); planen(); });

		box.addEventListener('mouseenter', function () { unterbrochen = true; planen(); });
		box.addEventListener('mouseleave', function () { unterbrochen = box.contains(document.activeElement); planen(); });
		box.addEventListener('focusin', function () { unterbrochen = true; planen(); });
		box.addEventListener('focusout', function (e) {
			if (!box.contains(e.relatedTarget)) { unterbrochen = box.matches(':hover'); planen(); }
		});
		box.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') { zeige(aktuell - 1); planen(); }
			if (e.key === 'ArrowRight') { zeige(aktuell + 1); planen(); }
		});
		// Im Hintergrund-Tab nicht weiterschalten.
		document.addEventListener('visibilitychange', function () {
			unterbrochen = document.hidden || box.matches(':hover') || box.contains(document.activeElement);
			planen();
		});
		// Außerhalb des sichtbaren Bereichs (weggescrollt) nicht weiterschalten und nichts vorladen.
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (eintraege) {
				sichtbar = eintraege[0].isIntersecting;
				if (sichtbar && vorladen) { ladeNaechstes(); }
				planen();
			}).observe(box);
		}

		knopfAnzeigen();
		zeige(0);
		planen();
	});
})();
