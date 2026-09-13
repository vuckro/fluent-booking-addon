# Agendas connectés — alpha.31

Un événement de groupe Google ou Outlook peut être partagé par plusieurs réservations d’un créneau. Sa description est susceptible d’être visible par les contacts invités, même lorsque la liste des invités est masquée. Les snapshots individuels ne doivent donc pas être copiés dans cette description.

L’extension ajoute simplement « Voir les détails de la réservation » puis un lien vers la réservation qui déclenche la création de l’événement distant : `/wp-admin/admin.php?page=fluent-booking#/scheduled-events?period=all&booking_id=ID`. L’identifiant vient de la réservation, il n’est jamais fixé à une valeur d’exemple. L’accès nécessite la connexion WordPress et les droits natifs FluentBooking. Aucun lien de confirmation public n’est partagé.

Un créneau partagé conserve ce lien d’origine lorsque d’autres réservations le rejoignent ; il ne pointe pas vers le dernier client ajouté. Si la réservation d’origine est supprimée, la liste native reste accessible en retirant `booking_id`. Sur Local, l’URL utilise localhost et n’est pas accessible à distance.
Les invitations et RSVP restent gérés par FluentBooking. La ligne Google « un invité » représente les adresses invitées ; l’extension ne la remplace pas par un compteur de participants. Aucun nom, âge ou tarif issu du snapshot n’est partagé. Les descriptions ajoutées par d’autres extensions sont conservées.

## Périmètre

`Guests/CalendarPresentation` utilise les filtres de création `fluent_booking/google_event_data` et `fluent_booking/outlook_event_data` observés dans FluentBooking Pro 2.4.0. Seules les réservations principales avec snapshot de personnalisation sont concernées. Aucun appel direct aux API, aucun nouveau mécanisme de synchronisation, aucun changement des emails de contact ou du nombre de places.

La description ne contient aucun compteur à actualiser. Les hooks ne s’exécutent pas lors d’un simple ajout d’invité à un événement distant déjà créé. **Tester avec un nouveau créneau sans événement distant existant.** Les événements antérieurs ne sont pas réécrits. Apple/Nextcloud et les modifications ultérieures du corps par les connecteurs natifs ne sont pas couverts ici.

## Vérification

`php tests/calendar-presentation.php` : 24 contrôles sans réseau, exécutés aussi dans GitHub Actions sur PHP 8.1–8.4. Préservation du payload natif, absence de données personnelles, idempotence, Outlook texte/HTML et réservations non concernées.

`tests/public-booking.php` vérifie en plus les filtres enregistrés sur une réservation réelle transactionnelle, avec et sans participation du contact. Aucun email ni événement Google/Outlook n’a été envoyé pendant cette recette. Vérifier le rendu chez le fournisseur avec une nouvelle réservation de test autorisée.

## Protection des places rattachées (alpha.31)

`CalendarContacts` enveloppe les callbacks des fournisseurs natifs au hook `wp_loaded`, après leur enregistrement. Une fiche marquée `attached_seat` ne déclenche aucune opération distante ; les collections de groupe transmises aux fournisseurs excluent ces fiches. Le stock et les contacts principaux restent inchangés. La protection couvre Google, Outlook, Apple et Nextcloud ; les descriptions personnalisées restent limitées à Google/Outlook.

Cette adaptation est liée aux hooks observés en 2.4.0 et doit être retestée avant une mise à jour native. `tests/calendar-contacts.php` vérifie leur branchement sans réseau et la protection des notifications natives dans `SeatNotifications`. Les autres intégrations tierces ne sont pas interceptées.
