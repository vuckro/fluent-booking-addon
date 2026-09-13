# Validation — alpha.21

Environnement local : WordPress 7.1, FluentBooking/Pro 2.4.0, PHP 8.2.29, MySQL InnoDB. Le plugin cible FluentBooking 2.4.x.

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-options.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-pricing.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/migration.php
```

- Unitaire : les options de limite/décompte sont refusées ; calcul des tarifs, forfaits, suppléments et remplacements.
- Administration : permissions, événement uniquement, révisions, titre Modules, absence du bloc et de l’héritage, résumé et liens natifs, options repliées.
- Réservation : places natives, capacité insuffisante, tarifs 100/200/300 et forfait 100, choix tarifaires 135, absence de double multiplication, conservation des montants et réponses, annulation/suppression des places, export/effacement.
- Migration : conversion des limites effectives de groupe/individuelles en simulation, aucune activation implicite des invités, restauration des fixtures et idempotence.
- Les tests d’intégration s’exécutent dans une transaction annulée et vérifient la restauration des réglages. Les appels réseau, courriels et actions de notification sont neutralisés dans les fixtures de réservation.

Tests DOM avec jsdom installé dans un répertoire temporaire, sans dépendance ajoutée au plugin :

```sh
NODE_PATH=/chemin/temporaire/node_modules node tests/guests-dom.cjs
NODE_PATH=/chemin/temporaire/node_modules node tests/guests-attached-dom.cjs
NODE_PATH=/chemin/temporaire/node_modules node tests/guest-admin-dom.cjs
```

Ils couvrent les identités requises/masquées, radios/cases, récapitulatifs, suppression d’un invité sans perdre les réponses de l’autre et masquage des options admin.

La syntaxe PHP/JS, le diff et le ZIP sont également vérifiés. Les anciens tests du module de limite ont été remplacés ; ils ne doivent plus être exécutés. Aucun paiement réel ni recette du vrai composant Svelte n’a été effectué. Les tests DOM ne constituent pas une validation visuelle du navigateur.
