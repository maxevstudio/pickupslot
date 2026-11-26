/* Widget « Click & Collect » du tableau de bord (Pickup Slot) : lieux et pastilles de fermeture. */
(function () {
	var champ = function (id) { return document.getElementById(id); };
	if (!champ('pickupslot-lieux')) return;
	var n = Date.now();

	/* ---------- Lieux ---------- */
	var lieux = champ('pickupslot-lieux');
	lieux.addEventListener('click', function (ev) {
		if (ev.target.classList.contains('pickupslot-lieu-x')) ev.target.parentNode.remove();
	});
	champ('pickupslot-lieu-ajouter').addEventListener('click', function () {
		n++;
		var l = champ('pickupslot-lieu-modele').content.firstElementChild.cloneNode(true);
		l.querySelectorAll('[data-champ]').forEach(function (i) { i.name = 'lieux[' + n + '][' + i.dataset.champ + ']'; });
		lieux.appendChild(l);
		l.querySelector('input').focus();
	});

	/* ---------- Pastilles de fermeture ---------- */
	var JOURS = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
	var MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
	var boite = champ('pickupslot-pastilles');
	var court = function (iso, avecJour) {
		var p = iso.split('-'), d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
		var t = (avecJour ? JOURS[d.getUTCDay()] + ' ' : '') + (+p[2]) + ' ' + MOIS[+p[1] - 1];
		return t.charAt(0).toUpperCase() + t.slice(1);
	};
	var libelle = function (debut, fin, motif) {
		var t = fin && fin !== debut ? 'Du ' + court(debut, false).toLowerCase() + ' au ' + court(fin, false).toLowerCase() : court(debut, true);
		return motif ? t + ' · ' + motif : t;
	};
	var cache = function (nom, val) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'fermetures[' + n + '][' + nom + ']'; i.value = val; return i; };
	boite.addEventListener('click', function (ev) {
		if (ev.target.classList.contains('pickupslot-pastille-x')) ev.target.parentNode.remove();
	});

	var ajouter = function () {
		var debut = champ('pickupslot-f-debut').value, fin = champ('pickupslot-f-fin').value, motif = champ('pickupslot-f-motif').value.trim();
		if (!debut) { champ('pickupslot-f-debut').focus(); return; }
		if (fin && fin < debut) { var x = debut; debut = fin; fin = x; }
		if (fin === debut) fin = '';
		if (boite.querySelector('.pickupslot-pastille[data-debut="' + debut + '"]')) { champ('pickupslot-f-debut').value = ''; return; }
		n++;
		var p = document.createElement('span');
		p.className = 'pickupslot-pastille';
		p.dataset.debut = debut;
		p.appendChild(document.createTextNode(libelle(debut, fin, motif) + ' '));
		var b = document.createElement('button');
		b.type = 'button'; b.className = 'pickupslot-pastille-x'; b.setAttribute('aria-label', 'Retirer cette fermeture'); b.innerHTML = '&times;';
		p.appendChild(b);
		p.appendChild(cache('debut', debut));
		p.appendChild(cache('fin', fin || debut));
		p.appendChild(cache('motif', motif));
		var suivante = [].find.call(boite.querySelectorAll('.pickupslot-pastille'), function (el) { return el.dataset.debut > debut; });
		boite.insertBefore(p, suivante || null);
		champ('pickupslot-f-debut').value = ''; champ('pickupslot-f-fin').value = ''; champ('pickupslot-f-motif').value = '';
		champ('pickupslot-f-debut').focus();
	};
	champ('pickupslot-f-ajouter').addEventListener('click', ajouter);
	['pickupslot-f-debut', 'pickupslot-f-fin', 'pickupslot-f-motif'].forEach(function (id) {
		champ(id).addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); ajouter(); } });
	});
})();
