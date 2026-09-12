# Invités — alpha.18

## Configurer

Dans Modules, sélectionner **l’événement de groupe**, puis activer **Personnaliser la réservation avec invités**.

- **Décompter une place par personne** : contrôle sous verrou que le créneau dispose d’une place pour le réservant et chaque invité. FluentBooking crée les réservations individuelles ; aucun second débit n’est ajouté. Désactivé, le comptage natif reste utilisé : cette case ne transforme pas un groupe natif en une seule place.
- **Multiplier le prix par le nombre de personnes** : le tarif de base natif de 100 devient 100, 200 ou 300 selon le nombre de personnes. Désactivé, le groupe paie une seule fois le tarif de base. La quantité de commande et celle utilisée pour payer restent cohérentes.
- **Questions pour chaque invité** : ajouter une liste de choix, un texte ou un nombre. Exemple : question « Catégorie », choix « Adulte » et « Enfant », réponse obligatoire. Un âge peut être une question de type Nombre. Rien n’est codé spécialement pour une catégorie.

Activer également **Invités supplémentaires** dans les questions natives via le lien fourni. Enregistrer. Les options sont propres à l’événement et désactivées par défaut ; les limites existantes sont préservées.

## Logique

Le réservant compte toujours comme une personne. Le récapitulatif public compte les lignes invité dès leur ajout, même avant la saisie de leur identité. La validation serveur refuse une identité ou réponse obligatoire incomplète ; aucune ligne ne doit disparaître silencieusement lors de la réservation.

Les réservations natives restent la source du nombre de places consommées. Quand le contrôle supplémentaire est activé, un verrou MySQL par événement et début de créneau sérialise le contrôle et la création du groupe. Il n’existe pas de second stock à synchroniser. Un échec partiel du moteur natif peut néanmoins laisser des réservations à examiner : aucune garantie de transaction atomique de l’ensemble des notifications/paiements n’est annoncée.

Les questions sont identifiées par une clé stable, indépendante du libellé. Les réponses et leurs libellés sont conservés avec la réservation. Modifier une question ne réécrit pas les réponses passées. Le tarif en centimes et la quantité sont également conservés, puis utilisés pour la commande.

Les réponses sont présentées dans le détail de confirmation natif et couvertes par les outils de confidentialité WordPress. L’effacement des données personnelles conserve les quantités nécessaires à la réservation et à la commande.

## Limites

- Événements de groupe, créneau unique ; aucune prise en charge des séries récurrentes.
- Nom et e-mail distinct restent obligatoires pour chaque invité. Aucun faux e-mail n’est créé.
- Stripe et paiement hors ligne natifs, tarif unique, devises à deux décimales. Coupons, WooCommerce, tarifs alternatifs et autres passerelles refusés dans ce mode.
- Les questions collectent des informations ; elles ne modifient pas encore le tarif ou le poids en places. Les règles Adulte/Enfant viendront ensuite.
- Les reports et réactivations des nouvelles réservations enrichies sont bloqués jusqu’à un parcours adapté. Les annulations restent natives ; ne pas modifier directement les tables pour contourner ces contrôles.
- Le frontend complète les lignes d’invités du formulaire FluentBooking 2.4 ; une recette navigateur réelle reste nécessaire avant production, notamment pour les intégrations tierces et les pages contenant plusieurs formulaires.
- Aucun paiement Stripe réel n’a été effectué pendant les tests.

## Tests

Les tests ciblés vérifient trois personnes/trois places, le refus à capacité insuffisante, 100 × 3 = 300, le forfait 100 avec multiplicateur désactivé, la persistance des champs, les réponses manquantes, le maintien du prix après modification et les outils de confidentialité. Les écritures sont annulées et la configuration utilisateur est comparée avant/après.

Un test DOM vérifie le récapitulatif 100/200/300, les questions obligatoires, la sérialisation et la conservation des bonnes réponses après suppression d’un invité. Il ne remplace pas une recette du véritable composant Svelte dans le navigateur.
