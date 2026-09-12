# Décisions d'architecture

## Démarrage et responsabilités

Le point d'entrée historique charge uniquement l'autoloader et `Plugin`. Les classes métier (`Configuration/Schema`, `Rules`) ne lisent ni superglobales, ni objets FluentBooking. L'adaptateur `ConfigurationStore` utilise les API WordPress et les helpers natifs. `Admin/SettingsPage` traite les permissions, le formulaire et l'affichage.

Le registre accepte des implémentations du contrat `Rule`, avec un identifiant unique et un résultat explicite. L'ordre d'enregistrement détermine l'ordre des validations ; la première erreur arrête la validation. Aucun moteur universel de règles ni table supplémentaire n'est nécessaire pour ce socle.

## Données

Clé `waaskit_fluent_booking_config` : enveloppe `{schema, revision, values}`. Au niveau site, option non autoloadée. Au niveau calendrier/événement, métadonnées natives `calendar` et `calendar_event`. Le schéma rejette les clés inconnues, les types approximatifs et les entiers hors limites.

Résolution : valeurs du produit → site → calendrier → événement. Une clé absente hérite ; une clé présente avec `false` ou `0` surcharge. Aucune écriture dans les réglages natifs historiques, aucune conversion de facturation.

Les écritures prennent un verrou via l'unicité de `option_name`, relisent la révision sans réutiliser un cache d'option périmé, puis vérifient la persistance. Ce verrou concerne la configuration, pas les places. Il ne couvre que les écritures via cet adaptateur.

## Contrats natifs vérifiés dans 2.4.0

- `Helper::getMeta($group, $id, $key)` et `Helper::updateMeta(...)` pour les réglages contextuels.
- `PermissionManager::canWriteCalendar` et `canUpdateCalendarEvent` pour les gestionnaires ; administration globale limitée à `manage_options`.
- `fluent_booking/booking_data` fournit les données préparées, l'événement, les champs et les données d'entrée ; `BookingService::createBooking` reconnaît un retour `WP_Error` avant création.

Un hook partagé ne garantit pas que toutes les entrées natives l'utilisent. L'exemple de limite est documenté avec cette restriction. La disponibilité d'un menu natif ne constitue pas une preuve de montage d'un composant tiers : le socle emploie une page WordPress contextuelle.

## Compatibilité et retrait

Hors FluentBooking 2.4.x, les nouvelles écritures de réglages sont désactivées. Si le service natif reste disponible, les créations concernées par une configuration activée sont refusées via le hook. Si FluentBooking manque ou si le plugin est désactivé, ce hook ne peut offrir aucune protection. Le retrait exige donc de mettre en sécurité les événements dépendants.

## Décisions reportées

Représentation des participants sans e-mail, instantané des règles d'un dossier de réservation, prix par catégorie, paiement, réconciliation et allocation atomique des places. Ces éléments ne sont pas implémentés dans une fondation de configuration et doivent suivre des prototypes natifs complets.
