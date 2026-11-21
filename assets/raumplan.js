/* Auhof Schulseiten – interaktiver Raumplan. Daten: window.auhofRaumplan (vom Plugin eingesetzt). */
(function () {
	'use strict';
	var P = window.auhofRaumplan;
	var root = document.getElementById('raumplan');
	if (!P || !root || root.dataset.ready) return;
	root.dataset.ready = '1';
	var ADMIN = !!P.admin;
	var NS = 'http://www.w3.org/2000/svg';
	var svg = root.querySelector('svg'), nav = root.querySelector('.rp-views');
	var q = root.querySelector('.rp-search input'), results = root.querySelector('.rp-results'), statusEl = root.querySelector('.rp-status');
	var FLOORNAME = {};
	P.floors.forEach(function (f) { FLOORNAME[f.id] = f.name; });
	var view = 'alle', selected = -1, hits = [], activeIdx = -1;

	function norm(s) { return String(s).toLowerCase().replace(/ß/g, 'ss').replace(/ü/g, 'u').replace(/ä/g, 'a').replace(/ö/g, 'o').replace(/[\s\-.|]+/g, ''); }
	function el(tag, attrs, parent) {
		var e = document.createElementNS(NS, tag);
		for (var k in attrs) if (attrs[k] !== undefined) e.setAttribute(k, attrs[k]);
		if (parent) parent.appendChild(e);
		return e;
	}

	/* Zeichnen */
	var gFill = el('g', { 'class': 'rp-nohit' }, svg);
	var gRoom = el('g', {}, svg);
	var gLine = el('g', { 'class': 'rp-nohit' }, svg);
	var gText = el('g', { 'class': 'rp-nohit' }, svg);
	P.fills.forEach(function (f) { el('rect', { x: f[0], y: f[1], width: f[2], height: f[3], fill: f[4] }, gFill); });
	var d = '';
	P.borders.forEach(function (b) { d += 'M' + b[0] + ' ' + b[1] + 'h' + b[2] + 'v' + b[3] + 'h' + (-b[2]) + 'z'; });
	P.polys.forEach(function (poly) { d += 'M' + poly.map(function (p) { return p.join(' '); }).join('L') + 'z'; });
	el('path', { d: d, fill: '#000' }, gLine);

	var probe = el('text', { 'font-size': 10 }, svg);
	function textWidth(s, bold) { probe.setAttribute('font-weight', bold ? 'bold' : 'normal'); probe.textContent = s; return probe.getComputedTextLength() / 10; }
	function fitText(r, i) {
		var bx = r.box[0], by = r.box[1], bw = r.box[2], bh = r.box[3];
		var plain = r.d.replace(/\|/g, '');
		var rotate = r.rot || (bh > bw * 1.8 ? plain.length > 3 : false);
		var W = (rotate ? bh : bw) - 2.4, H = (rotate ? bw : bh) - 1.6;
		var one = r.d.replace(/-\|/g, '-').replace(/\|/g, ' ');
		var variants = [r.d.split('|'), [one]];
		if (one.indexOf(' ') >= 0) variants.push(one.split(' '));
		var best = null;
		variants.forEach(function (lines) {
			var maxW = Math.max.apply(null, lines.map(function (l) { return textWidth(l, r.bold); }));
			var size = Math.min(r.size * 1.15, 9, W / maxW, H / (lines.length * 1.12));
			if (!best || size > best.size + 0.01) best = { lines: lines, size: size };
		});
		var cx = bx + bw / 2, cy = by + bh / 2;
		var t = el('text', { 'text-anchor': 'middle', 'font-size': best.size.toFixed(2), 'font-weight': r.bold ? 'bold' : 'normal', 'data-t': i,
			transform: rotate ? 'rotate(-90 ' + cx + ' ' + cy + ')' : undefined }, gText);
		var lh = best.size * 1.12, top = cy - (best.lines.length - 1) * lh / 2 + best.size * 0.36;
		best.lines.forEach(function (line, k) { el('tspan', { x: cx.toFixed(2), y: (top + k * lh).toFixed(2) }, t).textContent = line; });
	}
	function drawLabel(r, i) {
		if (r.spans) {
			r.spans.forEach(function (s) {
				el('text', { x: s.x, y: s.y, 'font-size': s.size, 'font-weight': s.bold ? 'bold' : 'normal', 'data-t': i,
					transform: s.rot ? 'rotate(-90 ' + s.x + ' ' + s.y + ')' : undefined }, gText).textContent = s.t;
			});
		} else fitText(r, i);
	}
	P.rooms.forEach(function (r, i) {
		var pd = '';
		r.shape.forEach(function (s) { pd += 'M' + s[0] + ' ' + s[1] + 'h' + s[2] + 'v' + s[3] + 'h' + (-s[2]) + 'z'; });
		var p = el('path', { d: pd, 'class': 'rp-room', tabindex: 0, role: 'button', 'aria-label': r.n + ', ' + FLOORNAME[r.f], 'data-i': i }, gRoom);
		el('title', {}, p).textContent = r.n + ' (' + FLOORNAME[r.f] + ')';
		drawLabel(r, i);
	});
	root.querySelector('.rp-year').textContent = P.year;
	root.querySelector('.rp-printonly').textContent = 'Raumplan · ' + P.year;
	P.static.forEach(function (s) {
		el('text', { x: s.x, y: s.y, 'font-size': s.size, 'font-weight': s.bold ? 'bold' : 'normal' }, gText).textContent = s.t;
	});

	/* Druck: immer alle Stockwerke auf einer A4-Seite quer, alle im selben Maßstab und so groß wie möglich –
	   unabhängig von Ansicht und Vergrößerung. Die Druckseite wird erst beim Drucken aus dem aktuellen Plan
	   gebaut (geänderte Raumnamen, markierter Raum). Seitenformat und Rand: @page in raumplan.css. */
	var DRUCK = { breite: 277, hoehe: 188, titel: 9, abstand: 6 }; // mm: A4 quer 297 × 210 minus 8 mm Rand und 2 mm Reserve
	var DRUCK_STOCK = ['3', '2', '1', 'eg'];
	var stockRahmen = {};
	// Eng zugeschnittener Ausschnitt je Stockwerk (aus den Plandaten, innerhalb der Stockwerksansicht).
	DRUCK_STOCK.forEach(function (id) {
		var v = P.views[id], x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
		function dazu(x, y, w, h) {
			if (y + h < v[1] || y > v[1] + v[3] || x + w < v[0] || x > v[0] + v[2]) return;
			x0 = Math.min(x0, x); y0 = Math.min(y0, y); x1 = Math.max(x1, x + w); y1 = Math.max(y1, y + h);
		}
		P.fills.forEach(function (f) { dazu(f[0], f[1], f[2], f[3]); });
		P.borders.forEach(function (b) { dazu(b[0], b[1], b[2], b[3]); });
		P.rooms.forEach(function (r) { if (r.f === id) r.shape.forEach(function (s) { dazu(s[0], s[1], s[2], s[3]); }); });
		P.static.forEach(function (s) { dazu(s.x, s.y - s.size, textWidth(s.t, s.bold) * s.size, s.size * 1.25); });
		if (x0 === Infinity) { stockRahmen[id] = v.slice(); return; }
		x0 = Math.max(v[0], x0 - 2); y0 = Math.max(v[1], y0 - 2); x1 = Math.min(v[0] + v[2], x1 + 2); y1 = Math.min(v[1] + v[3], y1 + 2);
		stockRahmen[id] = [x0, y0, x1 - x0, y1 - y0];
	});
	// Anordnung mit dem größten Maßstab: zwei Spalten (links oben → unten, dann rechts) oder zwei Zeilen,
	// Reihenfolge der Stockwerke bleibt erhalten.
	function druckAnordnung() {
		var B = DRUCK.breite, H = DRUCK.hoehe - DRUCK.titel, g = DRUCK.abstand, beste = null;
		function w(id) { return stockRahmen[id][2]; }
		function h(id) { return stockRahmen[id][3]; }
		function summe(l, f) { return l.reduce(function (a, id) { return a + f(id); }, 0); }
		function groesst(l, f) { return Math.max.apply(null, l.map(f)); }
		for (var k = 1; k < DRUCK_STOCK.length; k++) {
			var a = DRUCK_STOCK.slice(0, k), b = DRUCK_STOCK.slice(k);
			[
				['spalten', Math.min((B - g) / (groesst(a, w) + groesst(b, w)), (H - g * (a.length - 1)) / summe(a, h), (H - g * (b.length - 1)) / summe(b, h))],
				['zeilen', Math.min((H - g) / (groesst(a, h) + groesst(b, h)), (B - g * (a.length - 1)) / summe(a, w), (B - g * (b.length - 1)) / summe(b, w))]
			].forEach(function (c) { if (!beste || c[1] > beste.mm + 1e-9) beste = { art: c[0], gruppen: [a, b], mm: c[1] }; });
		}
		return beste;
	}
	var druck = document.createElement('div');
	druck.className = 'rp-druck';
	druck.setAttribute('aria-hidden', 'true');
	root.appendChild(druck);
	function druckBauen() {
		var a = druckAnordnung();
		while (druck.firstChild) druck.removeChild(druck.firstChild);
		var titel = document.createElement('p');
		titel.className = 'rp-druck-titel';
		titel.textContent = 'Raumplan · ' + P.year;
		druck.appendChild(titel);
		var flaeche = document.createElement('div');
		flaeche.className = 'rp-druck-flaeche rp-druck-' + a.art;
		druck.appendChild(flaeche);
		// Maße in mm für A4 quer mit 8 mm Rand; bei weniger Platz (größerer Rand im Druckdialog) schrumpft alles
		// gemeinsam (flex-shrink im Verhältnis der Breiten, Höhe folgt der viewBox).
		a.gruppen.forEach(function (gruppe) {
			var box = document.createElement('div'), breit = 0;
			box.className = 'rp-druck-gruppe';
			gruppe.forEach(function (id) { breit = a.art === 'spalten' ? Math.max(breit, stockRahmen[id][2]) : breit + stockRahmen[id][2]; });
			if (a.art === 'spalten') { box.style.flex = '0 1 ' + (breit * a.mm).toFixed(1) + 'mm'; box.style.width = (breit * a.mm).toFixed(1) + 'mm'; }
			flaeche.appendChild(box);
			gruppe.forEach(function (id) {
				var c = svg.cloneNode(true), r = stockRahmen[id];
				c.removeAttribute('style'); c.removeAttribute('role'); c.removeAttribute('aria-label');
				c.setAttribute('class', 'rp-druck-stock');
				c.setAttribute('viewBox', r.join(' '));
				if (a.art === 'spalten') {
					// Untereinander: Breite als Anteil der Spalte, damit beim Schrumpfen alle denselben Maßstab behalten.
					c.style.width = (r[2] / breit * 100).toFixed(3) + '%';
					c.style.flex = 'none';
				} else {
					c.style.width = (r[2] * a.mm).toFixed(1) + 'mm';
					c.style.flex = '0 1 ' + c.style.width;
				}
				c.style.maxWidth = '100%';
				c.style.height = 'auto';
				Array.prototype.forEach.call(c.querySelectorAll('.rp-room'), function (p) {
					if (P.rooms[+p.getAttribute('data-i')].f !== id) p.parentNode.removeChild(p);
					else { p.removeAttribute('tabindex'); p.removeAttribute('role'); }
				});
				box.appendChild(c);
			});
		});
	}
	window.addEventListener('beforeprint', druckBauen);

	var sheet = root.querySelector('.rp-sheet');
	var grid = document.createElement('div');
	grid.className = 'rp-grid'; grid.style.display = 'none';
	sheet.appendChild(grid);

	function roomFrom(e) { var t = e.target; while (t) { if (t.getAttribute && t.getAttribute('data-i') !== null) return +t.getAttribute('data-i'); if (t === sheet) return -1; t = t.parentNode; } return -1; }
	sheet.addEventListener('click', function (e) { var i = roomFrom(e); if (i >= 0) select(i === selected ? -1 : i, false); });
	sheet.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { var i = roomFrom(e); if (i >= 0) { e.preventDefault(); select(i, false); } } });
	function roomEls(i) { return root.querySelectorAll('.rp-room[data-i="' + i + '"]'); }
	function visibleRoom(i) {
		if (grid.style.display !== 'none') return grid.querySelector('svg[data-floor="' + P.rooms[i].f + '"] .rp-room[data-i="' + i + '"]');
		return svg.querySelector('.rp-room[data-i="' + i + '"]');
	}
	function reveal(i, smooth) { var e = visibleRoom(i); if (e) e.scrollIntoView({ block: 'nearest', inline: 'center', behavior: smooth ? 'smooth' : 'auto' }); }

	/* Rasteransicht (breite Bildschirme): je Stockwerk eine Kopie, nur mit den Räumen dieses Stockwerks */
	function buildGrid() {
		if (grid.childNodes.length) return;
		['3', '2', '1', 'eg'].forEach(function (id) {
			var c = svg.cloneNode(true);
			c.style.display = '';
			c.setAttribute('viewBox', P.views[id].join(' '));
			c.setAttribute('class', 'rp-cell');
			c.setAttribute('data-floor', id);
			c.setAttribute('aria-label', 'Raumplan ' + FLOORNAME[id]);
			Array.prototype.forEach.call(c.querySelectorAll('.rp-room'), function (p) {
				if (P.rooms[+p.getAttribute('data-i')].f !== id) p.parentNode.removeChild(p);
			});
			grid.appendChild(c);
		});
	}
	function gridOK() { return root.clientWidth >= 860; }
	var printBtn = root.querySelector('.rp-print');
	if (printBtn) printBtn.addEventListener('click', function () { druckBauen(); window.print(); });

	/* Ansichten */
	[['alle', 'Alle'], ['eg', 'EG'], ['1', '1. Stock'], ['2', '2. Stock'], ['3', '3. Stock']].forEach(function (v) {
		var b = document.createElement('button');
		b.type = 'button'; b.textContent = v[1]; b.setAttribute('data-id', v[0]);
		b.addEventListener('click', function () { setView(v[0]); updateHash(); });
		nav.appendChild(b);
	});
	/* Größe (Knöpfe − / +). Faktor 1 = Ausgangsgröße.
	   Passt der Plan nicht in die Breite (Handy, Stockwerk mind. 1000 px), gibt es nur Verkleinerungen bis
	   „ganzer Plan sichtbar“ – größer als die Ausgangsgröße wäre am Handy zu groß.
	   Passt er (Computer), gibt es nur Vergrößerungen (wischbar). */
	var faktor = 1, wunsch = 1; // wunsch = gewählte Größe (0 = ganzer Plan), faktor = in dieser Ansicht angewandt
	var zoomMinus = root.querySelector('.rp-zoom-minus'), zoomPlus = root.querySelector('.rp-zoom-plus');
	function platz() {
		var s = getComputedStyle(sheet);
		return sheet.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight);
	}
	// Ausgangsbreite des Plans (mit Mindestbreite am Handy), ohne eigene Größenangaben gemessen.
	function basisBreite() {
		svg.style.width = ''; svg.style.maxWidth = ''; svg.style.minWidth = '';
		return svg.getBoundingClientRect().width;
	}
	function stufen(basis) {
		var ganz = basis > 0 ? platz() / basis : 1;
		if (ganz >= 0.95) return [1, 1.5, 2, 3];
		var liste = [ganz];
		[0.5, 0.75].forEach(function (f) { if (f > ganz * 1.15) liste.push(f); });
		liste.push(1);
		return liste;
	}
	function naechste(liste, f) {
		var beste = 0;
		liste.forEach(function (x, i) { if (Math.abs(x - f) < Math.abs(liste[beste] - f)) beste = i; });
		return beste;
	}
	function zoomAnzeigen(liste) {
		var i = naechste(liste, faktor);
		if (zoomMinus) zoomMinus.disabled = i === 0;
		if (zoomPlus) zoomPlus.disabled = i === liste.length - 1;
	}
	function zoomAnwenden() {
		if (svg.style.display === 'none') { svg.style.width = ''; zoomAnzeigen([1, 1.5, 2, 3]); return; }
		var basis = basisBreite(), liste = stufen(basis);
		faktor = liste[naechste(liste, wunsch)]; // je Ansicht und Bildschirmbreite die passende Stufe
		zoomAnzeigen(liste);
		if (faktor === 1) return;
		svg.style.maxWidth = 'none'; svg.style.minWidth = '0';
		svg.style.width = Math.round(basis * faktor) + 'px';
	}
	function zoom(richtung) {
		var liste = stufen(svg.style.display === 'none' ? 0 : basisBreite());
		var i = Math.max(0, Math.min(liste.length - 1, naechste(liste, faktor) + richtung));
		faktor = liste[i];
		wunsch = i === 0 && faktor < 1 ? 0 : faktor;
		setView(view);
		if (selected >= 0) reveal(selected, false);
		statusEl.textContent = faktor === liste[0] && faktor < 1 ? 'Ganzer Plan sichtbar' : 'Plangröße ' + Math.round(faktor * 100) + ' %';
	}
	if (zoomMinus) zoomMinus.addEventListener('click', function () { zoom(-1); });
	if (zoomPlus) zoomPlus.addEventListener('click', function () { zoom(1); });

	function setView(id) {
		view = id;
		var useGrid = id === 'alle' && faktor === 1 ? gridOK() : false;
		if (useGrid) {
			buildGrid();
			grid.style.display = ''; svg.style.display = 'none';
		} else {
			grid.style.display = 'none'; svg.style.display = '';
			svg.setAttribute('viewBox', P.views[id].join(' '));
			svg.setAttribute('class', id === 'alle' ? 'rp-all' : 'rp-floor');
			// Nur Räume des sichtbaren Stockwerks per Tab erreichbar.
			Array.prototype.forEach.call(svg.querySelectorAll('.rp-room'), function (p) {
				var sichtbar = id === 'alle' || P.rooms[+p.getAttribute('data-i')].f === id;
				p.setAttribute('tabindex', sichtbar ? '0' : '-1');
				if (sichtbar) p.removeAttribute('aria-hidden'); else p.setAttribute('aria-hidden', 'true');
			});
		}
		zoomAnwenden();
		markRooms();
		Array.prototype.forEach.call(nav.children, function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-id') === id ? 'true' : 'false'); });
	}
	function select(i, jump) {
		selected = i;
		markRooms();
		if (i >= 0 && jump && view !== 'alle') setView(P.rooms[i].f);
		var r = P.rooms[i];
		statusEl.textContent = r ? 'Markiert: ' + r.n + ' – ' + FLOORNAME[r.f] : 'Tipp: Raum antippen oder oben suchen.';
		updateHash();
		if (i >= 0) root.dispatchEvent(new CustomEvent('auhof-raum-gewaehlt', { detail: i }));
	}
	function markRooms() {
		Array.prototype.forEach.call(root.querySelectorAll('.rp-room.rp-hl'), function (e) { e.classList.remove('rp-hl'); });
		if (selected >= 0) Array.prototype.forEach.call(roomEls(selected), function (e) { e.classList.add('rp-hl'); });
	}
	function updateHash() {
		if (ADMIN || !window.history || !history.replaceState) return;
		var h = '#stock=' + encodeURIComponent(view);
		if (selected >= 0) {
			var r = P.rooms[selected];
			h = '#stock=' + encodeURIComponent(view === 'alle' ? 'alle' : r.f) + '&raum=' + encodeURIComponent(r.n);
		}
		history.replaceState(null, '', location.pathname + location.search + h);
	}

	/* Suche (Kombinationsfeld mit Trefferliste) */
	function closeResults() { results.hidden = true; q.setAttribute('aria-expanded', 'false'); q.removeAttribute('aria-activedescendant'); }
	function search() {
		var term = norm(q.value.trim());
		results.innerHTML = ''; activeIdx = -1; hits = [];
		q.removeAttribute('aria-activedescendant');
		if (!term) { closeResults(); return; }
		P.rooms.forEach(function (r, i) { var n = norm(r.n); if (n.indexOf(term) >= 0) hits.push({ i: i, exact: n === term, start: n.indexOf(term) === 0 }); });
		var order = ['eg', '1', '2', '3'];
		hits.sort(function (a, b) {
			return (b.exact - a.exact) || (b.start - a.start) || P.rooms[a.i].n.localeCompare(P.rooms[b.i].n, 'de', { numeric: true }) || order.indexOf(P.rooms[a.i].f) - order.indexOf(P.rooms[b.i].f);
		});
		if (!hits.length) { var li0 = document.createElement('li'); li0.className = 'rp-none'; li0.textContent = 'Kein Raum „' + q.value + '“ gefunden'; results.appendChild(li0); }
		hits.forEach(function (h, k) {
			var r = P.rooms[h.i], li = document.createElement('li');
			li.id = 'rp-opt-' + k; li.setAttribute('role', 'option'); li.setAttribute('aria-selected', 'false');
			var b = document.createElement('b'); b.textContent = r.n;
			var sp = document.createElement('span'); sp.textContent = FLOORNAME[r.f];
			li.appendChild(b); li.appendChild(sp);
			li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(k); });
			results.appendChild(li);
		});
		results.hidden = false; q.setAttribute('aria-expanded', 'true');
	}
	function choose(k) {
		var h = hits[k]; if (!h) return;
		setView(P.rooms[h.i].f); select(h.i, true);
		closeResults(); q.blur();
		reveal(h.i, true);
	}
	q.addEventListener('input', search);
	q.addEventListener('focus', search);
	q.addEventListener('blur', function () { setTimeout(closeResults, 120); });
	q.addEventListener('keydown', function (e) {
		var items = results.querySelectorAll('li:not(.rp-none)');
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			if (!items.length) return;
			activeIdx = (activeIdx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
			Array.prototype.forEach.call(items, function (li, i) { li.classList.toggle('rp-active', i === activeIdx); li.setAttribute('aria-selected', i === activeIdx ? 'true' : 'false'); });
			q.setAttribute('aria-activedescendant', items[activeIdx].id);
		} else if (e.key === 'Enter') { e.preventDefault(); choose(activeIdx >= 0 ? activeIdx : 0); }
		else if (e.key === 'Escape') { closeResults(); }
	});

	/* Am Desktop die Breite der Menüleiste des Themes nutzen */
	function boxOf(sels) {
		for (var s = 0; s < sels.length; s++) {
			var list = document.querySelectorAll(sels[s]);
			for (var k = 0; k < list.length; k++) {
				if (root.contains(list[k])) continue;
				var r = list[k].getBoundingClientRect();
				if (r.width >= 300 && r.height > 0) return r;
			}
		}
		return null;
	}
	function menuBox() {
		var docW = document.documentElement.clientWidth;
		// Kadence: Inhaltsbreite des Kopfbereichs (ohne Innenabstand), damit der Plan bündig mit Logo und Menü steht.
		var kopf = document.querySelector('.site-main-header-inner-wrap, .site-header-row-container-inner .site-container');
		if (kopf) {
			var kr = kopf.getBoundingClientRect(), ks = getComputedStyle(kopf);
			var kl = kr.left + parseFloat(ks.paddingLeft), kw = kr.width - parseFloat(ks.paddingLeft) - parseFloat(ks.paddingRight);
			if (kw >= 300) return { left: kl, width: kw };
		}
		var n = boxOf(['#access', '[role="navigation"]', 'nav']);
		var head = boxOf(['[role="banner"]', '#header', '#masthead', 'header']);
		if (head && head.width >= docW - 20) head = null;
		if (n && n.width >= docW - 20) n = null;
		if (n) return head && head.width > n.width ? head : n;
		return head;
	}
	function bleed() {
		root.classList.remove('rp-wide');
		root.style.width = ''; root.style.maxWidth = ''; root.style.marginLeft = '';
		if (ADMIN || document.documentElement.clientWidth < 900) return;
		var m = menuBox();
		if (!m) return;
		root.style.marginLeft = '0px';
		var left = root.getBoundingClientRect().left;
		root.classList.add('rp-wide');
		root.style.maxWidth = 'none';
		root.style.width = Math.round(m.width) + 'px';
		root.style.marginLeft = Math.round(m.left - left) + 'px';
	}
	var lastW = -1, bleedTimer;
	function relayout() {
		var w = document.documentElement.clientWidth;
		if (w === lastW) return;
		lastW = w;
		bleed();
		setView(view);
	}
	function later() { clearTimeout(bleedTimer); bleedTimer = setTimeout(relayout, 80); }
	probe.parentNode.removeChild(probe);
	relayout();
	window.addEventListener('resize', later);
	window.addEventListener('load', relayout);
	if (window.ResizeObserver) new ResizeObserver(later).observe(document.body);

	/* Schnittstelle für die Backend-Seite: Raum markieren und Namen live ändern */
	root.addEventListener('auhof-raum-markieren', function (e) {
		var i = +e.detail;
		if (!P.rooms[i]) return;
		if (view !== 'alle') setView(P.rooms[i].f);
		selected = i; markRooms();
		statusEl.textContent = 'Markiert: ' + P.rooms[i].n + ' – ' + FLOORNAME[P.rooms[i].f];
		reveal(i, true);
	});
	root.auhofUmbenennen = function (i, name) {
		var r = P.rooms[i];
		if (!r || !name) return;
		Array.prototype.forEach.call(root.querySelectorAll('[data-t="' + i + '"]'), function (t) { t.parentNode.removeChild(t); });
		r.n = name; r.d = name; delete r.spans;
		svg.appendChild(probe);
		fitText(r, i);
		probe.parentNode.removeChild(probe);
		Array.prototype.forEach.call(roomEls(i), function (p) {
			p.setAttribute('aria-label', r.n + ', ' + FLOORNAME[r.f]);
			var t = p.querySelector('title'); if (t) t.textContent = r.n + ' (' + FLOORNAME[r.f] + ')';
		});
		if (grid.childNodes.length) { grid.innerHTML = ''; setView(view); }
	};

	/* Start und Direktlinks, z. B. #stock=3&raum=LIB */
	var params = {};
	(location.hash.slice(1) || location.search.slice(1)).split('&').forEach(function (kv) {
		var i = kv.indexOf('=');
		if (i > 0) { try { params[kv.slice(0, i)] = decodeURIComponent(kv.slice(i + 1).replace(/\+/g, ' ')); } catch (e) { /* ungültiger Link */ } }
	});
	if (ADMIN) params = {};
	var startView = P.views[params.stock] ? params.stock : (window.innerWidth < 700 ? 'eg' : 'alle');
	setView(startView);
	selected = -1;
	statusEl.textContent = 'Tipp: Raum antippen oder oben suchen.';
	if (params.raum) {
		var want = norm(params.raum), pick;
		P.rooms.forEach(function (r, i) { if (norm(r.n) === want && (pick === undefined || r.f === startView)) pick = i; });
		if (pick !== undefined) { if (startView !== 'alle') setView(P.rooms[pick].f); select(pick, true); reveal(pick, false); }
	}
})();
