# Fluent Booking Addon

Version **4.0.0-alpha.7** — base minimale pour validation locale.

## Ce qui est disponible

Une seule page **Fluent Booking → Modules**, avec les composants standards WordPress et une présentation légère inspirée de FluentBooking :

- Un sélecteur : tous les calendriers, un calendrier ou un événement.
- Deux réglages : activation des règles et maximum de participants par demande.
- Un bouton d'enregistrement et des diagnostics repliés en bas de page.

Choisir **Définir ici** pour appliquer une valeur ou **Hériter** pour utiliser le niveau supérieur. En mode Hériter, la valeur saisie est ignorée. Le maximum `0` n'ajoute aucune limite. Les valeurs effectives et leur provenance sont affichées après sauvegarde.

Aucune intégration dans les paramètres internes de FluentBooking, une seule feuille CSS limitée à cette page, un petit script pour le thème clair/sombre. Pro n'est pas requis pour ce socle.

Un bandeau reprend le logo et les liens de navigation FluentBooking. Le lien Modules est également présent dans le header natif ; notre sous-page reste indépendante de son application JavaScript. Les icônes et la palette reprennent FluentBooking 2.4.0 ; la préférence clair/sombre est partagée avec FluentBooking.

## Socle conservé

PHP 8.1+, chargement PSR-4, schéma de configuration strict et petit registre de règles. Stockage versionné dans une option WordPress et les métadonnées FluentBooking. Permissions, nonce, révision et verrou protègent les sauvegardes. Les réglages déjà enregistrés sont conservés ; une nouvelle installation est désactivée par défaut.

## Limites

Le module Participants reste expérimental : il limite les nouvelles demandes passant par le hook `fluent_booking/booking_data`. Il ne remplace pas la capacité de la séance et ne couvre pas les modifications, reports ni toutes les entrées API/import.

Participants sans e-mail, tarification, paiement, gestion atomique des places et migration de la version 3 ne sont pas implémentés. Cette alpha n'offre pas la parité avec 3.3.6.

## Installation et tests

Installer sur un site de test sauvegardé avec FluentBooking 2.4.0. Activer le fichier `wk-fluent-multireservation.php` dans le dossier `fluent-booking-addon`. Ne pas activer simultanément l'ancienne extension. Seule FluentBooking 2.4.0 a été testée ; le contrôle accepte 2.4.x.

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
python3 scripts/package.py
```

L'intégration exige un WordPress jetable sur localhost, un administrateur et un événement. Configurer le socket MySQL de Local si nécessaire. Les tests utilisent une transaction annulée ; tables InnoDB requises. Aucun test sur production.

Voir le [compte rendu](docs/compte-rendu.md), l'[architecture](docs/architecture.md), la [validation](docs/validation.md), les [extensions](docs/extensions.md) et la [migration](docs/migration.md).

Le dépôt ne contient ni site WordPress, ni base, ni licence. Un push Git ne synchronise pas la base. Aucune mise à jour automatique n'est fournie. Historique conservé au tag `archive/pre-rewrite-2026-09-12`.

Licence GPL-2.0-or-later.
