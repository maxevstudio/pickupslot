# Publier une version

Une release publiée apparaît dans le quart d'heure dans les mises à jour de chaque site, et s'installe toute seule
sur ceux où les mises à jour auto sont activées (activées par défaut à la première installation). Ne taguer qu'une version testée.

1. Décrire les changements sous `[Unreleased]` dans `CHANGELOG.md`, puis renommer la section avec la version et la date.
2. Changer la version à trois endroits : en-tête `Version:` et constante `PICKUPSLOT_VERSION` de `pickupslot.php`, `Stable tag` de `readme.txt`.
3. Commit `Version X.Y.Z`, tag `vX.Y.Z`, puis `git push origin main --follow-tags`.
4. Le workflow `.github/workflows/release.yml` vérifie que le tag correspond à la version, contrôle la syntaxe PHP,
   construit le ZIP (`bin/package.sh`) et le joint à la release GitHub.

`sh bin/package.sh` construit `dist/pickupslot-X.Y.Z.zip` en local (test, installation manuelle).

## Contenu du ZIP

Dossier racine `pickupslot/` (c'est lui que WordPress remplace) : `pickupslot.php`, `includes/`, `assets/`, `readme.txt`, `LICENSE`.

## Premier déploiement sur un site

Extensions → Ajouter → Téléverser le ZIP → Activer. Les mises à jour suivantes sont automatiques.
Le dépôt doit être **public** : le contrôle lit l'API publique des releases, sans jeton.
