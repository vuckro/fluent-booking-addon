# Architecture

- `Plugin.php` : démarrage, compatibilité et enregistrement des composants.
- `Admin/SettingsPage.php` : page, contexte autorisé, sauvegarde et diagnostics.
- `Admin/SettingsForm.php` : les deux options, leur vocabulaire et validation de saisie.
- `Admin/Header.php` : navigation et ressources visuelles FluentBooking.
- `Configuration/Schema.php` : valeurs autorisées, défauts, résolution des niveaux.
- `Integrations/FluentBooking/ConfigurationStore.php` : option globale et métadonnées natives, révisions et exclusion des écritures simultanées.
- `Integrations/FluentBooking/ParticipantLimitAdapter.php` : raccordement au filtre natif BookingService et comptage de ses données normalisées.
- `Integrations/FluentBooking/GuestFields.php` : limite du composant public natif et validation des invités avant leur traitement par le contrôleur.
- `Guests/Options.php` : schéma des questions et validation des réponses par invité.
- `Guests/BookingAdapter.php` : raccordement au groupe natif, admission, réponses et quantité/prix de commande.
- `Admin/GuestOptionsForm.php` : configuration événementielle des options et questions.
- `Rules/` : règles pures, sans accès au réseau, à la base ou au paiement.
- `Integrations/FluentBooking/ConfigurationApi.php` : export authentifié en lecture seule.
- `Integrations/FluentBooking/RetiredProfiles.php` et `Infrastructure/Privacy.php` : protections et confidentialité des seules données historiques des alphas retirées.
- `assets/admin/` : styles isolés, synchronisation du thème et petit enrichissement du formulaire. Aucun framework ni compilation frontend.

## Contrat de maintenance

Les clés `enabled` et `max_participants` et le schéma 1 restent compatibles. `booking_profile` n’est plus une fonctionnalité : cette clé historique reste lisible et immuable afin de ne pas perdre les données d’une ancienne alpha.

Ne pas ajouter de tarification à la règle de limite. Ne pas charger l’application JavaScript de FluentBooking sur cette page. Ne pas modifier les fichiers du plugin natif. Toute dépendance à ses modèles, URLs ou assets doit être testée sur la version ciblée.

Les options invités sont enregistrées seulement sur un événement, sans ajouter un second mécanisme d’héritage. Une réservation principale conserve le groupe et son tarif ; chaque réservation invitée conserve ses propres réponses pour la confidentialité.

Les données saisies sont validées côté serveur même sans JavaScript. Les contrôles de permission et nonce restent au point d’entrée d’écriture. Le magasin contrôle la révision et vérifie la lecture après sauvegarde.
