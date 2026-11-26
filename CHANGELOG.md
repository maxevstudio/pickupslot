# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [Unreleased]

## [2.1.2] - 2026-11-26

- Les mises à jour ne passent plus par l'API GitHub : sur un hébergement mutualisé, l'adresse IP partagée épuise son quota de 60 appels par heure (403 constaté sur balicco.fr), et aucune mise à jour n'apparaissait. Chaque release porte désormais un manifeste `pickupslot.json`, lu sur github.com.
- Le lien « Activer / Désactiver les mises à jour auto » s'affiche toujours, même si le contrôle de version échoue.

## [2.1.1] - 2026-11-26

- Mises à jour alignées sur Onside : la nouvelle release apparaît dans le quart d'heure (cache 15 min, réponse injectée à chaque lecture de la liste des mises à jour, cache vidé par « Vérifier à nouveau »).
- Les mises à jour auto ne sont plus forcées : activées une seule fois à la première installation, puis le lien natif « Activer / Désactiver les mises à jour auto » de l'écran Extensions fait foi. Le filtre `pickupslot_auto_update` est retiré.

## [2.1.0] - 2026-11-26

- Chaque lieu de retrait a une **adresse** et des **horaires d'ouverture**, tous deux facultatifs. L'adresse donne un lien d'itinéraire Google Maps (l'ancien lien Google Maps reste utilisé tant qu'aucune adresse n'est saisie).
- Horaires **saisis jour par jour** (deux plages au plus, pour la pause de midi ; jour vide = fermé) et toujours **mis en forme pareil**, jours identiques consécutifs regroupés : « Du mardi au samedi : 8 h – 19 h 30 ».
- **Consigne de retrait** facultative (ex. « Munissez-vous de votre numéro de commande… »).
- **Encart « Retrait de votre commande »** dans les e-mails WooCommerce (HTML et texte seul) et sur la page de confirmation / le détail de commande du compte client : jour, lieu, adresse, itinéraire, horaires, consigne. Sans style propre : il reprend la charte des e-mails WooCommerce. Titre filtrable (`pickupslot_titre_encart`).
- Au passage de commande, un lieu unique affiche son adresse et le lien « Itinéraire ».

## [2.0.0] - 2026-11-26

Première version de l'extension (2.x pour la distinguer de l'essai React 1.0.x de juin 2026).

- Reprise à l'identique du MU-plugin `balicco-click-collect.php` du 2026-11-10 : widget du tableau de bord, champs jour et lieu au passage de commande, validation, affichage récap / e-mails / admin / colonne « Retrait », changement du jour dans l'admin.
- Plus aucune dépendance à CodeRockz : retrait de `bcc_actif()` et de l'écriture de `delivery_status`, `delivery_type`, `delivery_details_timestamp`.
- Préfixes propres : option `pickupslot`, métas `_pickupslot_date` / `_pickupslot_location`, champs `pickupslot_date` / `pickupslot_lieu`, filtre `pickupslot_capacite`.
- Reprise : lecture de repli de l'option `bcc_click_collect` (copiée à l'activation) et des métas `pickup_date` / `pickup_location`.
- Si l'ancien MU-plugin est encore chargé, seul le widget tourne (avec un avertissement), pour ne pas doubler les champs.
- Compatible HPOS ; déclarée incompatible avec le passage de commande en blocs.
- Mises à jour automatiques depuis les releases GitHub (`includes/maj.php`, même mécanisme que Page Lock Manager).
