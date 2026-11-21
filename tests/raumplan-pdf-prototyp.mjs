// Prototyp/Referenz für includes/raumplan-pdf.php: gleicher Ablauf, gleiche Zahlen.
// Aufruf: node raumplan-pdf-prototyp.mjs <raumplan.json> <ausgabe.pdf> ["Schuljahr 2026/27"]
import fs from 'fs';

const W_NORMAL = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
const W_FETT = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
const BASIS = { 'Ä': 'A', 'Ö': 'O', 'Ü': 'U', 'ä': 'a', 'ö': 'o', 'ü': 'u', 'é': 'e', 'è': 'e', 'á': 'a', 'à': 'a', 'ó': 'o', 'ú': 'u', 'í': 'i', 'ç': 'c', 'ñ': 'n', 'É': 'E', '–': '0', '·': '.' };

function zeichenbreite(z, fett) {
	if (z === 'ß') return 611;
	if (BASIS[z]) z = BASIS[z];
	const c = z.charCodeAt(0);
	if (c < 32 || c > 126) return 556;
	return (fett ? W_FETT : W_NORMAL)[c - 32];
}
// Breite eines Textes bei Schriftgröße 1.
function textbreite(t, fett) { let s = 0; for (const z of t) s += zeichenbreite(z, fett); return s / 1000; }
function zahl(x) { return (Math.round(x * 1000) / 1000).toString(); }
// PDF-Zeichenkette in WinAnsi (Windows-1252), maskiert.
function pdfText(t) {
	let aus = '';
	for (const z of t) {
		let c = z.codePointAt(0);
		if (c === 0x2013) c = 0x96; else if (c === 0x2014) c = 0x97; else if (c === 0x201E) c = 0x84; else if (c === 0x201C) c = 0x93; else if (c === 0x20AC) c = 0x80;
		else if (c > 255 || c < 32 || (c > 126 && c < 160)) c = 63;
		const ch = String.fromCharCode(c);
		aus += (ch === '(' || ch === ')' || ch === '\\') ? '\\' + ch : ch;
	}
	return '(' + aus + ')';
}
function farbe(hex) { const n = parseInt(hex.slice(1), 16); return [(n >> 16) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255].map(zahl).join(' '); }

const DRUCK = { breite: 277, hoehe: 188, titel: 9, abstand: 6 };
const STOCK = ['3', '2', '1', 'eg'];

function rahmen(P) {
	const aus = {};
	for (const id of STOCK) {
		const v = P.views[id]; let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
		const dazu = (x, y, w, h) => { if (y + h < v[1] || y > v[1] + v[3] || x + w < v[0] || x > v[0] + v[2]) return; x0 = Math.min(x0, x); y0 = Math.min(y0, y); x1 = Math.max(x1, x + w); y1 = Math.max(y1, y + h); };
		P.fills.forEach(f => dazu(f[0], f[1], f[2], f[3]));
		P.borders.forEach(b => dazu(b[0], b[1], b[2], b[3]));
		P.rooms.forEach(r => { if (r.f === id) r.shape.forEach(s => dazu(s[0], s[1], s[2], s[3])); });
		P.static.forEach(s => dazu(s.x, s.y - s.size, textbreite(s.t, s.bold) * s.size, s.size * 1.25));
		if (x0 === Infinity) { aus[id] = v.slice(); continue; }
		x0 = Math.max(v[0], x0 - 2); y0 = Math.max(v[1], y0 - 2); x1 = Math.min(v[0] + v[2], x1 + 2); y1 = Math.min(v[1] + v[3], y1 + 2);
		aus[id] = [x0, y0, x1 - x0, y1 - y0];
	}
	return aus;
}
function anordnung(R) {
	const B = DRUCK.breite, H = DRUCK.hoehe - DRUCK.titel, g = DRUCK.abstand; let beste = null;
	const w = id => R[id][2], h = id => R[id][3];
	const summe = (l, f) => l.reduce((a, id) => a + f(id), 0), groesst = (l, f) => Math.max(...l.map(f));
	for (let k = 1; k < STOCK.length; k++) {
		const a = STOCK.slice(0, k), b = STOCK.slice(k);
		for (const [art, mm] of [
			['spalten', Math.min((B - g) / (groesst(a, w) + groesst(b, w)), (H - g * (a.length - 1)) / summe(a, h), (H - g * (b.length - 1)) / summe(b, h))],
			['zeilen', Math.min((H - g) / (groesst(a, h) + groesst(b, h)), (B - g * (a.length - 1)) / summe(a, w), (B - g * (b.length - 1)) / summe(b, w))],
		]) if (!beste || mm > beste.mm + 1e-9) beste = { art, gruppen: [a, b], mm };
	}
	return beste;
}
function plaetze(R, a) {
	const B = DRUCK.breite, g = DRUCK.abstand, T = DRUCK.titel, aus = {};
	const w = id => R[id][2] * a.mm, h = id => R[id][3] * a.mm;
	if (a.art === 'spalten') {
		const sb = a.gruppen.map(gr => Math.max(...gr.map(w))); let x = (B - (sb[0] + g + sb[1])) / 2;
		a.gruppen.forEach((gr, k) => { let y = T; gr.forEach(id => { aus[id] = [x, y]; y += h(id) + g; }); x += sb[k] + g; });
	} else {
		let y = T;
		a.gruppen.forEach(gr => { const zb = gr.reduce((s, id) => s + w(id), 0) + g * (gr.length - 1); let x = (B - zb) / 2; gr.forEach(id => { aus[id] = [x, y]; x += w(id) + g; }); y += Math.max(...gr.map(h)) + g; });
	}
	return aus;
}
// Text an (x, y) mit Grundlinie; Koordinaten mit y nach unten (wie im SVG), daher gespiegelte Textmatrix.
function text(t, x, y, groesse, fett) { return `BT /${fett ? 'F2' : 'F1'} ${zahl(groesse)} Tf 1 0 0 -1 ${zahl(x)} ${zahl(y)} Tm ${pdfText(t)} Tj ET\n`; }
function gedreht(inhalt, cx, cy) { return `q 0 -1 1 0 ${zahl(cx - cy)} ${zahl(cy + cx)} cm\n${inhalt}Q\n`; }
export function raumtext(r) {
	if (r.spans) return r.spans.map(s => { const t = text(s.t, s.x, s.y, s.size, s.bold); return s.rot ? gedreht(t, s.x, s.y) : t; }).join('');
	const [bx, by, bw, bh] = r.box, plain = r.d.replace(/\|/g, '');
	const rotate = r.rot || (bh > bw * 1.8 ? plain.length > 3 : false);
	const W = (rotate ? bh : bw) - 2.4, H = (rotate ? bw : bh) - 1.6;
	const one = r.d.replace(/-\|/g, '-').replace(/\|/g, ' ');
	const varianten = [r.d.split('|'), [one]]; if (one.includes(' ')) varianten.push(one.split(' '));
	let best = null;
	for (const lines of varianten) {
		const maxW = Math.max(...lines.map(l => textbreite(l, r.bold)), 0.001);
		const size = Math.min(r.size * 1.15, 9, W / maxW, H / (lines.length * 1.12));
		if (!best || size > best.size + 0.01) best = { lines, size };
	}
	const cx = bx + bw / 2, cy = by + bh / 2, lh = best.size * 1.12, top = cy - (best.lines.length - 1) * lh / 2 + best.size * 0.36;
	let aus = '';
	best.lines.forEach((l, k) => { aus += text(l, cx - textbreite(l, r.bold) * best.size / 2, top + k * lh, best.size, r.bold); });
	return rotate ? gedreht(aus, cx, cy) : aus;
}

export function raumplanPdf(P, jahr) {
	const R = rahmen(P), a = anordnung(R), pl = plaetze(R, a);
	const PT = 72 / 25.4, SB = 297, SH = 210, x0 = (SB - DRUCK.breite) / 2, y0 = (SH - DRUCK.hoehe) / 2;
	// Seite: Punkte, Ursprung unten links → mm, Ursprung oben links, y nach unten.
	let s = `${zahl(PT)} 0 0 ${zahl(-PT)} 0 ${zahl(SH * PT)} cm\n1 0 0 1 ${zahl(x0)} ${zahl(y0)} cm\n0 g\n`;
	s += text('Raumplan · ' + jahr, 0, 4.5, 4.2, true);
	for (const id of STOCK) {
		const r = R[id], m = a.mm, p = pl[id];
		s += `q\n${zahl(m)} 0 0 ${zahl(m)} ${zahl(p[0] - r[0] * m)} ${zahl(p[1] - r[1] * m)} cm\n${r.map(zahl).join(' ')} re W n\n`;
		for (const f of P.fills) s += `${farbe(f[4])} rg ${zahl(f[0])} ${zahl(f[1])} ${zahl(f[2])} ${zahl(f[3])} re f\n`;
		s += '0 g\n';
		for (const b of P.borders) s += `${zahl(b[0])} ${zahl(b[1])} ${zahl(b[2])} ${zahl(b[3])} re\n`;
		for (const poly of P.polys) s += poly.map((q, i) => `${zahl(q[0])} ${zahl(q[1])} ${i ? 'l' : 'm'}`).join(' ') + ' h\n';
		s += 'f\n';
		for (const raum of P.rooms) if (raum.f === id) s += raumtext(raum);
		for (const t of P.static) if (t.y >= r[1] && t.y <= r[1] + r[3] && t.x >= r[0] && t.x <= r[0] + r[2]) s += text(t.t, t.x, t.y, t.size, t.bold);
		s += 'Q\n';
	}
	const objekte = [
		'<< /Type /Catalog /Pages 2 0 R /Lang (de-AT) >>',
		'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${zahl(SB * PT)} ${zahl(SH * PT)}] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>`,
		`<< /Length ${Buffer.byteLength(s, 'latin1')} >>\nstream\n${s}endstream`,
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
		`<< /Title ${pdfText('Raumplan · ' + jahr)} /Producer (Auhof Schulseiten) >>`,
	];
	let pdf = '%PDF-1.4\n%\xE2\xE3\xCF\xD3\n'; const pos = [];
	objekte.forEach((o, i) => { pos.push(Buffer.byteLength(pdf, 'latin1')); pdf += `${i + 1} 0 obj\n${o}\nendobj\n`; });
	const xref = Buffer.byteLength(pdf, 'latin1');
	pdf += `xref\n0 ${objekte.length + 1}\n0000000000 65535 f \n` + pos.map(n => String(n).padStart(10, '0') + ' 00000 n \n').join('');
	pdf += `trailer\n<< /Size ${objekte.length + 1} /Root 1 0 R /Info 7 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
	return Buffer.from(pdf, 'latin1');
}

if (process.argv[2]) {
	const P = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
	fs.writeFileSync(process.argv[3], raumplanPdf(P, process.argv[4] || 'Schuljahr 2026/27'));
	console.log('geschrieben', process.argv[3], fs.statSync(process.argv[3]).size, 'Bytes');
}
