# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [Unreleased]

## [2.0.0] - 2026-11-26

Première version de l'extension (2.x pour la distinguer de l'essai React 1.0.x de juin 2026).

- Reprise à l'identique du MU-plugin `balicco-click-collect.php` du 2026-11-10 : widget du tableau de bord, champs jour et lieu au passage de commande, validation, affichage récap / e-mails / admin / colonne « Retrait », changement du jour dans l'admin.
- Plus aucune dépendance à CodeRockz : retrait de `bcc_actif()` et de l'écriture de `delivery_status`, `delivery_type`, `delivery_details_timestamp`.
- Préfixes propres : option `pickupslot`, métas `_pickupslot_date` / `_pickupslot_location`, champs `pickupslot_date` / `pickupslot_lieu`, filtre `pickupslot_capacite`.
- Reprise : lecture de repli de l'option `bcc_click_collect` (copiée à l'activation) et des métas `pickup_date` / `pickup_location`.
- Si l'ancien MU-plugin est encore chargé, seul le widget tourne (avec un avertissement), pour ne pas doubler les champs.
- Compatible HPOS ; déclarée incompatible avec le passage de commande en blocs.
- Mises à jour automatiques depuis les releases GitHub (`includes/maj.php`, même mécanisme que Page Lock Manager).
