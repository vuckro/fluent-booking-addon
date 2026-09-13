# Un tarif par personne — alpha.22

## Utilisation

1. Dans les paiements de l’événement FluentBooking, créez les lignes **Adulte : 70 €** et **Enfant : 55 €**. Utilisez les paiements natifs, une durée unique et un événement de groupe.
2. Activez **Invités supplémentaires** dans les questions FluentBooking. La capacité du créneau reste gérée par FluentBooking.
3. Dans **Modules**, sélectionnez cet événement et cochez **Un tarif par personne** : les options de personnalisation apparaissent. Décochez puis enregistrez pour retrouver le formulaire et le calcul natifs.
4. Choisissez **Nom obligatoire** et **Courriel masqué** pour les invités, puis enregistrez.

L’événement collectif local est déjà configuré ainsi. Ses tarifs natifs n’ont pas été modifiés. L’objet de la réunion reste une question native : vous pouvez le désactiver dans les questions FluentBooking.

Le réservant choisit son tarif. Chaque invité choisit le sien. Le premier tarif est sélectionné initialement ; avec un seul tarif, chaque personne l’utilise. Le total affiche uniquement les choix retenus :

| Réservation | Montant | Places |
| --- | --- | --- |
| Un adulte | 70 € | 1 |
| Un enfant | 55 € | 1 |
| Un adulte et un enfant | 125 € | 2 |
| Deux adultes et un enfant | 195 € | 3 |

Les montants de FluentBooking sont normalement des lignes additionnées. **Ce module les interprète comme des choix exclusifs**, uniquement sur les événements personnalisés utilisant ce mode. Si vous désactivez la personnalisation, FluentBooking retrouve son comportement natif : les lignes redeviennent cumulatives.

## Administration simplifiée

Le mode par personne n’a pas de multiplicateur à configurer. Les champs supplémentaires recueillent des informations, sans définir un deuxième tarif. Leur validation reste côté serveur : texte, nombre, liste, radios ou case à cocher.

Les anciens événements conservent leur calcul jusqu’à adoption explicite du nouveau mode. S’ils ont des champs avec supplément ou remplacement de prix, retirez d’abord ces effets tarifaires : l’enregistrement refuse de mélanger les deux moteurs. Les anciennes réservations conservent leurs montants.

Le résumé présente le maximum possible sur un créneau vide, soit le minimum entre le plafond des questions et la capacité. Avec capacité 5 et plafond 10, il affiche 5. Le nombre disponible peut être inférieur après d’autres réservations. Le badge vert signifie personnalisation activée ; rouge signifie désactivée, pas une erreur de réservation.

## Technique et limites

- `NativeTariffs` lit les lignes natives, normalise les centimes et donne une identité au couple libellé/montant. Un choix modifié après ouverture du formulaire est refusé : il faut recharger.
- Le navigateur ne fournit aucun montant faisant autorité. Le serveur retrouve chaque choix dans les réglages et calcule le total.
- Un instantané conserve le choix du réservant, ceux des invités et les montants. La commande native comporte une ligne par personne, quantité 1 ; aucune double multiplication.
- Chaque invité crée une place native rattachée au réservant. Les invités sans courriel n’ont pas de faux e-mail. Le réservant reçoit les communications.
- La page autonome FluentBooking ne passe pas par `wp_footer`. Les ressources sont imprimées via ses hooks `author_landing_head` et `author_landing_footer`, en conservant l’enqueue WordPress pour les pages ordinaires.
- Le champ technique de transport est masqué ; l’interface propre au module conserve le moyen de paiement natif et remplace seulement le récapitulatif additif.
- Support ciblé : FluentBooking 2.4.x, événement de groupe, durée unique, devises à deux décimales, Stripe natif et paiement hors ligne. Pas de coupons, WooCommerce, multi-durée, report ou réactivation automatique. Aucun encaissement Stripe réel n’a été réalisé durant cette recette.

## Vérification

`tests/native-tariffs.php` vérifie les totaux, les lignes de commande, les choix falsifiés/périmés, les champs numériques, la capacité et l’annulation. Les fixtures sont exécutées sous transaction puis annulées ; envois et HTTP externes bloqués.

`tests/native-tariffs-dom.cjs` vérifie les choix et le récapitulatif, l’identité masquée, l’âge et la suppression d’un invité. La recette locale a également exécuté le vrai bundle JavaScript FluentBooking avec le module sous jsdom, à partir du HTML public et des disponibilités lues, sans soumission réseau : passage calendrier → formulaire, 70/125/70, champ technique masqué et moyen de paiement conservé. Cela ne remplace pas une vérification visuelle dans un navigateur.

Sauvegarde locale avant adoption : `.local-backups/native-tariffs-2026-09-13.json`, dans le dossier parent du dépôt (hors package).
