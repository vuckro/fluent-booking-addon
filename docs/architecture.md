> **Alpha.22 :** le mode recommandé est désormais [Un tarif FluentBooking par personne](tarifs-par-personne.md). Les passages ci-dessous sur le multiplicateur et les champs tarifaires décrivent le mode antérieur, conservé pour les configurations existantes.

# Architecture après suppression des doublons — alpha.21

## Parcours unique

`Plugin` enregistre la page admin, les outils de confidentialité, le garde-fou historique et `Guests/BookingAdapter`. Aucune API REST propre ni registre générique de règles n’est enregistré.

| Fichier | Responsabilité |
| --- | --- |
| `Admin/SettingsPage` | Sélection d’événement, permissions, sauvegarde, résumé enregistré et liens natifs |
| `Admin/GuestOptionsForm` | Champs admin de personnalisation |
| `Integrations/FluentBooking/ConfigurationStore` | Configuration par événement, révision et verrou de sauvegarde |
| `Guests/Options` | Valeurs permises et validation des réponses |
| `Guests/Identity` | Normalisation des invités ; compatibilité d’entrée BookingService |
| `Guests/Pricing` | Calcul pur en centimes : remplacement puis suppléments |
| `Guests/BookingAdapter` | Formulaire public, contrôle serveur, réservation et commandes natives |
| `Guests/AttachedSeats` | Places natives rattachées au réservant, annulation et suppression |
| `Infrastructure/Privacy` | Export et effacement, y compris les copies rattachées |
| `Integrations/FluentBooking/RetiredProfiles` | Protection des anciennes réservations expérimentales uniquement |

## Source unique des limites

La capacité et la limite d’invités sont lues depuis l’événement et ses questions FluentBooking. Le contrôle serveur de l’add-on subsiste parce que son formulaire personnalisé peut créer des invités sans identité. Ce contrôle applique les valeurs natives ; il ne définit aucune limite supplémentaire. Le comptage se fonde sur les réservations natives et n’utilise aucune table de stock parallèle.

## Stockage

Schéma 2 : `waaskit_fluent_booking_config` dans les métadonnées de l’événement, avec `revision` et `values.guest_options`. Les anciens niveaux site/calendrier et les clés de limite ont été supprimés. Les sauvegardes concurrentes sont protégées par une révision et un verrou ; une relecture vérifie l’écriture.

Les réponses, libellés, montants et quantité sont conservés dans `fba_guests_v1` sur la réservation. Les paiements reprennent ces montants, pas un total reçu du navigateur. Les identités ne servent pas de compteur de places.

## Compatibilité et limites techniques

Le script de migration est distinct du runtime. Si une configuration ancienne est détectée, les réservations sont temporairement bloquées jusqu’à la migration explicite : on ne doit pas ignorer silencieusement une ancienne restriction. Les métadonnées de réservations expérimentales ne sont pas supprimées.

Le formulaire ajoute son propre bloc à FluentBooking ; il ne manipule plus les lignes d’invités Svelte. Un observateur sert uniquement à repérer le montage du formulaire. Les lignes créées appartiennent à l’add-on.

Un verrou MySQL sérialise l’admission des invités personnalisés sur un événement et un début de créneau. Ce verrou n’est pas une transaction distribuée couvrant les notifications, les intégrations tierces ou les paiements. La recette navigateur et Stripe reste nécessaire.
