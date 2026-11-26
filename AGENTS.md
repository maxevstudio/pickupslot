# AGENTS.md — règles du projet Pickup Slot

Le dépôt est la seule source de vérité. Lire `README.md`, puis `RELEASE.md` pour toute publication.

1. **Légèreté avant tout** : pas de page de réglages, pas de React, pas de build, pas de dépendance Composer ou npm.
   Tout se règle dans le widget du tableau de bord.
2. **Ne jamais renommer** l'option `pickupslot` ni les métas `_pickupslot_date` / `_pickupslot_location` :
   des commandes réelles en dépendent. Garder la lecture de repli des anciennes clés (`bcc_click_collect`, `pickup_*`).
3. Les métas `_pickupslot_pickup_date`, `_pickupslot_location_name` et les options `pickupslot_settings` /
   `pickupslot_locations` appartiennent à l'ancien essai React 1.0.x (juin 2026) : ne pas les réutiliser.
4. Tout en français (interface, commentaires, docs). Chaque changement notable va sous `[Unreleased]` dans `CHANGELOG.md`.
5. Un tag `vX.Y.Z` poussé met à jour **tous les sites** : tester d'abord sur un WordPress local, jamais sur un site client.
6. `reference/` (hors Git) contient le MU-plugin d'origine de Balicco, en lecture seule.
