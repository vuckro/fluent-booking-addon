# Fluent Booking Addon

Version **4.0.0-alpha.18** — base simplifiée pour FluentBooking 2.4.x, PHP 8.1+.

Limite par réservation et options d’invités pour les événements de groupe : places par personne, prix proportionnel ou forfaitaire, et questions supplémentaires. Le moteur utilise les réservations et commandes natives, sans table de stock parallèle. Les anciens moteurs expérimentaux restent retirés.

Dans **Fluent Booking → Modules**, sélectionner tous les calendriers, un calendrier ou un événement. Choisir de reprendre les réglages communs, de garder uniquement les limites FluentBooking ou de fixer un maximum. Le nombre n’est demandé que dans ce dernier cas. Le résumé indique les valeurs enregistrées et leur provenance. Le bouton de consultation publique apparaît si la page du calendrier est activée dans FluentBooking.

Cette alpha n’est pas un remplacement fonctionnel de la 3.3.6 ni une version certifiée pour la production. Les modifications et reports ne sont pas couverts. Le mode personnalisé est désactivé par défaut. Son tarif de base vient de FluentBooking ; l’extension contrôle la quantité facturée et conserve le montant de la réservation.

- [Compte rendu : conservé, retiré, limites et suite](docs/compte-rendu.md)
- [Fonctionnement des réglages](docs/fonctionnement.md)
- [Architecture et maintenance](docs/architecture.md)
- [Validation](docs/validation.md)
- [Invités : logique actuelle et champs à faire évoluer](docs/invites-et-evolutions.md)
- [Migration et données historiques](docs/migration.md)

## Vérifier et distribuer

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/booking-service.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-logic.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-options.php
python3 scripts/package.py
```

Les tests d’intégration nécessitent un site localhost jetable, des tables InnoDB, un événement natif et le socket PHP/MySQL adapté à Local. Les écritures de test sont annulées par transaction.

Le ZIP exclut les tests, Git et les données locales. Aucune nouvelle table n’est nécessaire. Les données sont conservées par défaut à la désinstallation ; la suppression volontaire requiert `FBA_DELETE_DATA_ON_UNINSTALL=true`. Aucun push ne synchronise la base et aucune mise à jour automatique n’est installée.

Licence GPL-2.0-or-later.
