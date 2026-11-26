# Pickup Slot

Extension WordPress de Click & Collect pour WooCommerce, par [Maxev](https://maxev.fr/).
Le client choisit son jour et son lieu de retrait au passage de commande ; la boutique règle tout depuis
**un seul widget du tableau de bord** (aucune page de réglages, aucune dépendance, aucun build).

## Ce que fait l'extension

- **Widget « Click & Collect »** (capacité `edit_shop_orders`, filtrable par `pickupslot_capacite`) :
  lieux de retrait (nom + lien Google Maps), jours où le retrait est possible, délai de préparation selon
  le jour de la commande, nombre de jours proposés, heure limite, fermetures exceptionnelles (jour ou période).
  Un aperçu indique le premier jour de retrait pour une commande passée maintenant.
- **Passage de commande** (formulaire classique, hook `woocommerce_before_order_notes`) : lieu (choisi d'office
  s'il n'y en a qu'un) et jour de retrait, contrôlés à la validation.
- **Commande** : métas `_pickupslot_date` (AAAA-MM-JJ) et `_pickupslot_location`. Affichage dans le récapitulatif,
  les e-mails, le compte client, la fiche commande (jour modifiable) et une colonne « Retrait » de la liste des commandes.
- Compatible HPOS. Le passage de commande en **blocs** n'est pas pris en charge (il faut le formulaire classique).

## Reprise depuis le MU-plugin Balicco

- Réglages : l'option `bcc_click_collect` est copiée dans `pickupslot` à l'activation, et lue en repli tant que
  `pickupslot` n'existe pas. L'ancienne option n'est jamais modifiée.
- Commandes : à défaut de `_pickupslot_*`, les métas `pickup_date` / `pickup_location` (CodeRockz puis MU-plugin) sont lues.
- Tant que `balicco-click-collect.php` est chargé, Pickup Slot n'affiche que son widget, avec un avertissement.

## Structure

| Fichier | Rôle |
|---|---|
| `pickupslot.php` | En-tête, réglages, calcul des jours, lecture des métas, chargement |
| `includes/widget.php` | Widget du tableau de bord et enregistrement |
| `includes/commande.php` | Passage de commande, affichage, admin commande, colonne |
| `includes/maj.php` | Mises à jour depuis les releases GitHub |
| `assets/` | CSS et JS du widget (chargés sur le tableau de bord seulement) |
| `bin/package.sh` | Construit le ZIP installable |

Publication : voir [RELEASE.md](RELEASE.md). Historique : [CHANGELOG.md](CHANGELOG.md).
