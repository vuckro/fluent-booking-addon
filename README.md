# WaasKit — FluentBooking Addon

Version **4.0.0-alpha.1** : nouvelle fondation, issue de l'historique de `fluent-booking-guests`.

Cette version est destinée à la validation locale. Elle ne remplace pas encore les fonctionnalités de participants sans e-mail et de tarification de la version 3.

## Disponible

- Architecture PHP avec namespace WaasKit et chargement PSR-4, sans dépendance de production à installer.
- Configuration globale, par agenda et par événement dans **FluentBooking → WaasKit**.
- Héritage explicite, valeurs effectives et provenance ; `false` et `0` sont conservés.
- Stockage versionné : option WordPress globale et métadonnées natives contextualisées.
- Droits des gestionnaires vérifiés via les permissions FluentBooking ; nonces sur les écritures.
- Contrôle de révision et verrou pour éviter l'écrasement des réglages concurrents.
- Registre de règles et premier exemple : limite de participants par demande.
- Diagnostics et inventaire des options historiques, sans migration automatique.
- Tests unitaires, tests d'intégration locale et CI PHP.

Tout est désactivé par défaut. Le plugin n'altère ni les montants ni les champs du formulaire public.

## Installation locale

1. Utiliser une installation de test sauvegardée avec FluentBooking 2.4.0.
2. Décompresser le ZIP dans `wp-content/plugins/fluent-booking-addon`.
3. Activer **WaasKit — FluentBooking Addon**.
4. Ouvrir **FluentBooking → WaasKit** et choisir le contexte à configurer.

Un clone Git peut être relié au dossier des plugins par un lien symbolique pour le développement. Le site WordPress, sa base, les clés et les licences ne font pas partie du dépôt. Un `git push` publie le code ; il ne synchronise pas la base WordPress.

Ne pas activer simultanément l'ancienne version et celle-ci. Le fichier principal historique est conservé, mais une installation ayant un autre nom de dossier nécessite un remplacement explicite. Aucun mécanisme de mise à jour automatique n'est fourni.

## Configuration

Sélectionner **Hériter** pour supprimer une surcharge, ou **Définir ici** pour enregistrer la valeur choisie. Le niveau global hérite des valeurs du produit ; un événement hérite de son agenda, puis du site.

La règle `max_participants` est expérimentale. Elle compte les personnes représentées par les données natives au passage dans `BookingService::createBooking`. Elle ne valide pas les participants auparavant écartés par FluentBooking et ne gère ni l'âge, ni les rôles, ni une capacité de séance. Les modifications, reports, imports directs et écritures SQL ne sont pas couverts. Ne pas employer cette règle comme garantie de jauge en production.

## Développement et tests

PHP 8.1 minimum. Tests unitaires sans WordPress :

```sh
php tests/unit.php
```

Tests d'intégration sur un site jetable accessible en localhost, avec au moins un événement natif et un administrateur :

```sh
WAASKIT_WP_PATH=/chemin/wordpress php tests/integration.php
```

Configurer `mysqli.default_socket` si nécessaire avec Local. Le plugin doit être actif. Le test utilise une transaction et annule ses changements ; les tables doivent utiliser InnoDB. Ne jamais le lancer sur un site en production.

Voir [architecture](docs/architecture.md), [contrat d'extension](docs/extensions.md), [validation](docs/validation.md) et [migration et exploitation](docs/migration.md).

## Compatibilité

Recette locale : WordPress 7.1, FluentBooking 2.4.0, Pro 2.4.0. Le socle n'exige pas Pro, car il n'engage aucun paiement. Le contrôle de compatibilité accepte FluentBooking 2.4.x ; seule 2.4.0 a été testée. Les autres versions sont explicitement non validées.

## Suite

Prototypes de participants sans e-mail, chaîne de paiement réelle, capacité concurrente des réservations et intégration d'un panneau dans l'administration native. L'interface actuelle utilise une page WordPress contextuelle ; elle ne modifie pas le JavaScript compilé de FluentBooking.

Licence GPL-2.0-or-later. Historique 3.3.6 conservé dans Git au tag `archive/pre-rewrite-2026-09-12`.
