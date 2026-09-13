# Tarifs et informations par invité — alpha.25

## Utilisation

1. Dans les paiements de l’événement FluentBooking, créez les lignes **Adulte : 70 €** et **Enfant : 55 €**. Utilisez les paiements natifs, une durée unique et un événement de groupe.
2. Activez **Invités supplémentaires** dans les questions FluentBooking. La capacité du créneau reste gérée par FluentBooking.
3. Dans **Modules**, sélectionnez cet événement et cochez **Un tarif par personne** pour remplacer les lignes cumulatives par un choix de tarif pour chaque personne. Cette case ne contrôle plus les informations demandées aux invités.
4. Cochez séparément **Personnaliser les informations des invités** pour afficher le nom, le courriel et les champs supplémentaires. Par exemple, choisissez **Nom obligatoire** et **Courriel masqué**, puis enregistrez.

Cet exemple ne modifie pas les réglages enregistrés de vos événements. L’objet de la réunion reste une question native : vous pouvez le désactiver dans les questions FluentBooking.

Le réservant choisit son tarif. Chaque invité choisit le sien. Le premier tarif est sélectionné initialement ; avec un seul tarif, chaque personne l’utilise. Le total affiche uniquement les choix retenus :

| Réservation | Montant | Places |
| --- | --- | --- |
| Un adulte | 70 € | 1 |
| Un enfant | 55 € | 1 |
| Un adulte et un enfant | 125 € | 2 |
| Deux adultes et un enfant | 195 € | 3 |

Les montants de FluentBooking sont normalement des lignes additionnées. **Ce module les interprète comme des choix exclusifs**, uniquement sur les événements personnalisés utilisant ce mode. Si vous décochez « Un tarif par personne », FluentBooking retrouve son calcul natif : les lignes redeviennent cumulatives.

## Informations indépendantes

Les deux cases peuvent être cochées ensemble ou séparément. Sans tarif par personne, les informations des invités restent disponibles et le paiement natif est conservé. Sans personnalisation des informations, le nom et le courriel des invités sont obligatoires et les champs supplémentaires ne sont pas demandés. Les valeurs configurées sont conservées lorsque vous masquez ce bloc.

Pour un champ **Nombre**, renseignez **Minimum** et/ou **Maximum** ; une borne vide signifie aucune limite de ce côté. Les bornes sont inclusives et peuvent être négatives ou décimales (jusqu’à quatre décimales). Un champ facultatif peut rester vide. Le serveur refuse une valeur hors limites ou un minimum supérieur au maximum.

Les choix proposés dans les exemples sont « Option 1 » et « Option 2 » ; remplacez-les par vos libellés.

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

## Maintenance

`enabled` conserve son rôle historique d’activation de la tarification. `customize_guests` active séparément les informations. Pour une ancienne configuration sans cette clé, sa valeur suit l’ancien interrupteur : aucune modification de comportement à la mise à jour. `Options::effective()` calcule les réglages utilisés sans effacer les valeurs enregistrées. Le mode informations seules marque le snapshot `preserve_payments` pour laisser les filtres de commandes et de lignes natifs intacts.

### Passage au paiement

Les choix deviennent un instantané enregistré avec la réservation. La commande et
Stripe utilisent ces lignes, sans additionner tous les tarifs disponibles. Les
participants ne sont plus modifiables dès l'envoi du formulaire. Si la validation
native échoue, les contrôles redeviennent disponibles. Après préparation de Stripe,
le récapitulatif reste visible et figé : le montant à payer vient de la réponse serveur,
vérifiée contre le PaymentIntent et les choix soumis. En cas de désaccord, Stripe ne
s'ouvre pas et un message demande de contacter l'organisateur.

Le bouton « Recommencer » recharge simplement la page pour une nouvelle saisie.
La flèche de retour native fait de même une fois Stripe affiché. Aucune annulation
Stripe, modification de commande ou conservation des coordonnées dans le navigateur
n'est effectuée. Les tentatives impayées expirent selon les réglages et le cron natifs
FluentBooking ; leurs places ne sont pas nécessairement libérées immédiatement.
Un rechargement n'annule pas un paiement déjà validé : dans ce cas, vérifiez sa
confirmation avant de recommencer. Cette option remplace le mécanisme d'annulation
et de reprise de l'alpha.37, supprimé à la demande de l'utilisateur.
