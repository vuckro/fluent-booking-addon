# Réserver pour d’autres personnes — alpha.27

## Utilisation

Dans Modules, cochez **Autoriser la réservation pour d’autres personnes**, puis enregistrez. Les **Invités supplémentaires** doivent également être activés dans les questions FluentBooking.

Le formulaire propose **Je participe également**, coché par défaut. En le décochant :

- le nom et le courriel du réservant restent demandés : c’est le contact et le payeur ;
- au moins un participant doit être renseigné, selon les règles d’identité et de champs configurées ;
- le tarif du contact est masqué en mode par personne ; seuls les tarifs des participants sont additionnés ;
- les places sont comptées sur les participants, sans ajouter de place pour le contact.

L’option ne modifie pas les réglages existants et ne s’active pas automatiquement. Les anciens formulaires et réservations continuent à considérer que le réservant participe.

## Prix et capacité

Avec les tarifs 70 € et 55 € :

| Situation | Places | Total en mode par personne |
| --- | --- | --- |
| Le contact participe seul à 70 € | 1 | 70 € |
| Le contact ne participe pas, un invité à 55 € | 1 | 55 € |
| Le contact ne participe pas, invités à 70 € et 55 € | 2 | 125 € |
| Le contact participe à 70 €, plus un invité à 55 € | 2 | 125 € |

Avec un **prix natif par réservation**, ce prix reste dû même si le contact ne participe pas : décocher sa participation ne rend pas la réservation gratuite. Les événements gratuits sont également couverts.

Le maximum natif et les places disponibles s’appliquent au nombre réel de participants. Si le contact se remet à participer alors que le maximum est atteint, le formulaire demande de retirer un invité sans supprimer ses informations automatiquement. Le serveur revalide dans tous les cas.

## Représentation dans FluentBooking

FluentBooking possède une réservation principale qui compte toujours comme une place. Nous conservons cette réservation au nom du contact pour les paiements et les communications. **Lorsque ce contact ne participe pas, cette première place représente le premier participant.** Les autres participants possèdent les réservations rattachées habituelles.

Invariant : nombre de réservations natives du groupe = nombre de participants effectifs. Aucun statut fictif, place négative ni deuxième stock n’est introduit.

Le snapshot contient explicitement `holder_participates`, tous les participants (y compris le premier), leurs informations et leurs tarifs. Le détail de la réservation indique que le réservant ne participe pas et affiche la liste réelle. Un export natif, un calendrier externe ou une autre extension lisant uniquement le nom de la réservation principale verra toujours le **contact** : ce mode ne transforme pas tous les écrans et intégrations FluentBooking en listes de présence. Utiliser le détail enrichi pour identifier les participants.

## Cycle de vie et limites

- Annulation du groupe : toutes les places rattachées sont annulées ; le contact conserve les communications natives.
- Suppression du groupe : les réservations rattachées sont supprimées.
- Les tarifs et le choix de participation sont conservés sur les anciennes réservations ; changer l’option n’en modifie pas les montants.
- Le report, la réactivation automatique et la modification partielle des participants restent hors périmètre, comme pour les autres réservations personnalisées.
- Les anciens moteurs de suppléments/remplacements ne peuvent pas être associés à cette option. Utiliser les tarifs FluentBooking par personne ou conserver le paiement natif.
- La compatibilité reste celle de l’alpha : FluentBooking 2.4.x et 2.5.x, groupes sur créneau unique, paiement natif Stripe/hors ligne, sans coupons ni WooCommerce.

## Recette

`tests/nonparticipating.php` couvre 50 contrôles transactionnels, sans envois ni paiement externe : prix, places, données obligatoires, payload falsifié, contact non participant interdit si option désactivée, capacité, annulation, suppression, confidentialité, prix natif fixe et gratuité.

`tests/nonparticipating-dom.cjs` couvre le prix affiché, les changements de participation, le maximum et la restauration des données. `FBA_NONPARTICIPATING=1` ajoute le scénario au parcours du vrai bundle natif sous jsdom (`tests/native-page-dom.cjs`).

Les tests automatisés ne remplacent pas le contrôle visuel, les notifications reçues et un paiement Stripe de bout en bout sur le site cible.
