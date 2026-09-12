# Fluent Booking Addon

Version **4.0.0-alpha.11** — développement local, cible fonctionnelle en cours.

Extension générique de FluentBooking : participants typés, règles, capacités et tarification. Aucun réglage Cooms Cookies n’est codé en dur.

## Modules

La page **Fluent Booking → Modules** conserve sa présentation légère et ses thèmes clair/sombre. Chaque contexte hérite du niveau global, puis du calendrier, avec remplacement du profil à l’événement.

Le nouveau profil, désactivé par défaut, propose les types de participants, noms, e-mail facultatif, règles d’âge/accompagnement, tarifs et jauges partagées. La réservation native principale porte les participants en métadonnées. Le stock est protégé par une retenue persistante et un verrou MySQL.

Les montants sont calculés côté serveur et transmis aux commandes natives. Les chemins Stripe et hors ligne sont adaptés ; aucun paiement réel n’a été exécuté pendant le développement.

## État de livraison

**Le périmètre demandé n’est pas entièrement livré.** Coupons avec tarifs par type, acomptes, modifications/reports, mappings CRM prêts à l’emploi et migration historique restent à développer. Les parcours non couverts sont refusés quand nécessaire. La recette navigateur et Stripe de test reste requise.

Voir le [point d’étape et la matrice complète](docs/implementation-modules.md) avant d’activer le profil. Cette alpha ne remplace pas fonctionnellement la version 3.3.6 et n’est pas une version de production.

## Installation et tests

PHP 8.1+, WordPress, FluentBooking 2.4.x. Recette sur FluentBooking/Pro 2.4.0. Les tarifs nécessitent Pro et un moyen de paiement natif configuré. MySQL doit autoriser `GET_LOCK` ; ouvrir Modules avec un compte administrateur initialise la table de capacités.

```sh
php tests/unit.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/modules-integration.php
WAASKIT_WP_PATH=/chemin/wordpress php tests/capacity-concurrency.php
python3 scripts/package.py
```

Les tests d’intégration exigent un site localhost jetable, un événement de groupe et des tables InnoDB. Configurer le socket MySQL de Local si nécessaire. Les réservations fictives sont annulées par transaction et les retenues de concurrence nettoyées. Le test des modules initialise la nouvelle table si nécessaire ; il bloque e-mails et requêtes HTTP.

Le dépôt ne contient ni site WordPress, ni base, ni licence. Aucun push ne synchronise la base. Pas de mise à jour automatique. Les données sont conservées à la désinstallation par défaut. Historique pré-refonte conservé au tag `archive/pre-rewrite-2026-09-12`.

Licence GPL-2.0-or-later.
