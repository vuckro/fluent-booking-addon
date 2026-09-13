> Nouveau : [tarifs FluentBooking au choix par personne](docs/tarifs-par-personne.md) (alpha.22).

# Fluent Booking Addon

Version **4.0.0-alpha.21**, pour FluentBooking 2.4.x et PHP 8.1+.

Une page **Modules** pour personnaliser les invités des événements de groupe : identités facultatives ou masquées, champs supplémentaires, forfait ou tarif par personne, suppléments et prix par choix.

**FluentBooking garde la gestion des capacités, du maximum de personnes, des disponibilités et du tarif de base.** L’add-on ne possède plus de limite ni de système d’héritage parallèle.

- [Utilisation et limites](docs/fonctionnement.md)
- [Architecture](docs/architecture.md)
- [Suppression des doublons : compte rendu](docs/compte-rendu.md)
- [Migration des anciennes alphas](docs/migration.md)
- [Tests et validation](docs/validation.md)

Le mode personnalisé est désactivé par défaut. Il reste en alpha : événements de groupe sur créneau unique, Stripe ou paiement hors ligne, sans coupons ni reports. Recette navigateur et Stripe en mode test nécessaire avant production.

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-options.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-pricing.php
python3 scripts/package.py
```

Le ZIP exclut Git, les tests et les données locales. Aucune table de stock parallèle. Les données sont conservées à la désinstallation ; l’effacement volontaire requiert `FBA_DELETE_DATA_ON_UNINSTALL=true`. Pas de synchronisation de base ni de mise à jour automatique.

Licence GPL-2.0-or-later.
