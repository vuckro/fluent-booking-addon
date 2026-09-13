# Agendas connectés — alpha.30

Un événement de groupe Google ou Outlook peut être partagé par plusieurs réservations d’un créneau. Sa description est susceptible d’être visible par les contacts invités, même lorsque la liste des invités est masquée. Les snapshots individuels ne doivent donc pas être copiés dans cette description.

L’extension ajoute une explication générique : un contact invité peut réserver sans participer, le nombre d’adresses invitées n’est pas le nombre de places, et l’organisateur retrouve les rôles, réponses et tarifs dans FluentBooking après authentification. Le lien ouvre la liste native des réservations, jamais un reçu public ou la réservation du premier client qui pourrait ensuite être supprimée. Sur Local, ce lien utilise localhost ; il ne sera accessible à distance qu’une fois le site hébergé.

Les invitations et RSVP restent gérés par FluentBooking. La ligne Google « un invité » représente les adresses invitées ; l’extension ne la remplace pas par un compteur de participants. Aucun nom, âge ou tarif issu du snapshot n’est partagé. Les descriptions ajoutées par d’autres extensions sont conservées.

## Périmètre

`Guests/CalendarPresentation` utilise les filtres de création `fluent_booking/google_event_data` et `fluent_booking/outlook_event_data` observés dans FluentBooking Pro 2.4.0. Seules les réservations principales avec snapshot de personnalisation sont concernées. Aucun appel direct aux API, aucun nouveau mécanisme de synchronisation, aucun changement des emails de contact ou du nombre de places.

La description est stable : ajouter ou annuler une réservation sur le créneau ne rend pas son contenu obsolète. Les hooks ne s’exécutent pas lors d’un simple ajout d’invité à un événement distant déjà créé. **Tester avec un nouveau créneau sans événement distant existant.** Les événements antérieurs ne sont pas réécrits. Apple/Nextcloud et les modifications ultérieures du corps par les connecteurs natifs ne sont pas couverts ici.

## Vérification

`php tests/calendar-presentation.php` : 24 contrôles sans réseau, exécutés aussi dans GitHub Actions sur PHP 8.1–8.4. Préservation du payload natif, absence de données personnelles, idempotence, Outlook texte/HTML et réservations non concernées.

`tests/public-booking.php` vérifie en plus les filtres enregistrés sur une réservation réelle transactionnelle, avec et sans participation du contact. Aucun email ni événement Google/Outlook n’a été envoyé pendant cette recette. Vérifier le rendu chez le fournisseur avec une nouvelle réservation de test autorisée.
