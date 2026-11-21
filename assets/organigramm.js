/* Auhof Schulseiten – Organigramm: Suche (Treffer hervorheben). Aus der Vorlage der Schule. */
(function () {
  Array.prototype.forEach.call(document.querySelectorAll('.eg-org'), function (root) {
  var box = root.querySelector('.eg-search'), q = root.querySelector('#eg-q'), out = root.querySelector('.eg-count');
  var items = Array.prototype.slice.call(root.querySelectorAll('[data-s]'));
  function norm(s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ß/g, 'ss').replace(/\s+/g, ' '); }
  items.forEach(function (el) {
    var t = el.textContent;
    var card = el.closest('.eg-card, .eg-branch');           // Abschnittstitel mitdurchsuchen
    var h = card && card.querySelector('h3');
    if (h && !el.contains(h)) t = h.textContent + ' ' + t;
    el.egText = norm(t);
  });
  box.hidden = false;
  q.addEventListener('input', function () {
    var v = norm(q.value.trim()), n = 0;
    root.classList.toggle('is-searching', !!v);
    items.forEach(function (el) {
      var hit = !!v && el.egText.indexOf(v) > -1;
      el.classList.toggle('is-hit', hit);
      if (hit) n++;
    });
    out.textContent = v ? (n ? n + ' Treffer' : 'Keine Treffer') : '';
  });
  });
})();
