# Fluent Booking Addon

Version **4.0.0-alpha.31**, pour FluentBooking **2.4.x** et PHP **8.1+**.

Une page **Modules** pour configurer chaque événement de groupe, avec des options indépendantes :

- **Un tarif par personne** : le réservant et chaque invité choisissent un tarif défini dans FluentBooking. Seuls les tarifs choisis sont additionnés.
- **Personnaliser les informations des invités** : nom et courriel obligatoires, facultatifs ou masqués ; champs texte, nombre (minimum/maximum), liste ou radios.

Les personnalisations sont désactivées par défaut. Sans tarification personnalisée, le paiement natif est conservé. Chaque participant occupe une place native ; FluentBooking garde les capacités et disponibilités. Il n’existe plus de limite ni d’héritage parallèle dans l’add-on.

Nouvelle option facultative : **[Réserver pour d’autres personnes](docs/reserver-pour-autrui.md)**. Le contact peut ne pas participer ; seuls les participants occupent des places et paient en mode par personne. Le contact reste affiché comme réservant dans FluentBooking.

## Utilisation et maintenance

- [Guide des tarifs et informations](docs/tarifs-par-personne.md)
- [Recette avant main et limites de validation](docs/recette-finale.md)
- [Agendas connectés et confidentialité](docs/agendas-connectes.md)
- [Commandes de test](docs/validation.md)
- [Migration depuis la v3 ou les anciennes alphas](docs/migration.md)
- [Architecture](docs/architecture.md)

Périmètre : événements de groupe sur créneau unique, devise à deux décimales, paiements natifs Stripe ou hors ligne. Coupons, WooCommerce, multi-durée, reports et réactivation automatique restent exclus du mode personnalisé. Les anciens moteurs de prix sont conservés pour les configurations historiques uniquement.

**Cette alpha n’est pas encore validée pour une ouverture générale en production.** Les corrections alpha.31 sont sur `codex/nonparticipating-booker`. Consultez la recette avant déploiement : distribution officielle FluentBooking, réception des emails, agendas connectés et paiement Stripe restent à valider sur le site cible. Le passage depuis la v3 demande une configuration explicite : ses options ne sont pas automatiquement converties.

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-options.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/guest-pricing.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/native-tariffs.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/migration.php
python3 scripts/package.py
```

Le ZIP exclut Git, les tests et les données locales. Les données sont conservées à la désinstallation ; l’effacement volontaire requiert `FBA_DELETE_DATA_ON_UNINSTALL=true`. Aucune synchronisation de base ni mise à jour automatique.

Licence GPL-2.0-or-later.
