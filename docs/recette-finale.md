# Vérification avant production — 13 septembre 2026 — alpha.31

## Verdict

**Prête pour une recette contrôlée ; ouverture générale en production non validée.** Les tests locaux passent, mais ils ne certifient ni la réception des emails, ni les fournisseurs de paiement/agendas, ni une distribution officielle FluentBooking. Ne pas confondre synchronisation du dépôt GitHub et déploiement sur un site client.

## Résultats locaux

- 266 contrôles PHP : unité, configuration, champs et bornes, tarifs natifs, migration, contact non participant, entrée AJAX publique et intégration des agendas/notifications.
- 8 scénarios DOM : administration, invités, tarifs, informations seules, participation du contact et parcours du bundle natif.
- Réservations de test transactionnelles annulées ; emails et HTTP externes bloqués dans les tests. Les tests ne créent pas d’événement Google et ne débitent aucun paiement.
- Environnement : WordPress 7.1, PHP 8.2.29, FluentBooking et Pro annoncés 2.4.0. **Les sources natives locales contiennent des modifications : cette recette ne constitue pas une certification de la distribution officielle 2.4.0.**

Les contrôles couvrent 70 € adulte, 55 € enfant, 125 € pour les deux ; contact non participant + enfant = 55 € et une place ; validation serveur des tarifs et champs ; refus de zéro participant ou de six participants pour cinq places ; annulation groupée, suppression et préservation des montants historiques.

## Corrections trouvées pendant l’audit

Les gestionnaires de paiement natifs déclenchent aussi les hooks de réservation des fiches de places rattachées. Ces fiches ont volontairement un email vide. Elles pouvaient donc produire une tentative Google invalide et des notifications/rappels séparés.

`CalendarContacts` protège les callbacks des fournisseurs natifs après leur enregistrement : les fiches rattachées sont ignorées, et les collections de groupe sont filtrées sans modifier le stock. `SeatNotifications` protège les callbacks de notifications natifs, y compris les tâches asynchrones déjà en file. Le contact principal conserve ses communications, qu’il participe ou non. Aucun fichier FluentBooking n’est modifié par ces corrections.

Les tests vérifient l’enregistrement réel des protections pour Google, Outlook, Apple et Nextcloud ainsi que les neuf hooks de notifications observés. Ils bloquent le réseau : **le succès chez ces fournisseurs reste à vérifier.** Les intégrations tierces ajoutant leurs propres callbacks ne sont pas couvertes par cette protection native.

## Conditions avant ouverture aux clients

1. Installer sur une préproduction la distribution officielle FluentBooking/Pro compatible et cette alpha ; sauvegarder base et fichiers, puis refaire la recette. Ne pas remplacer silencieusement la copie locale modifiée.
2. Vérifier desktop/mobile, clair/sombre, clavier et confirmation avec les thèmes/extensions réellement utilisés.
3. Tester un contact participant avec un invité, puis un contact non participant avec un invité ; comparer commande, participants, champs, places restantes et confirmation.
4. Tester l’annulation de la **réservation principale** et la restitution des places. Les invités rattachés appartiennent au même dossier : ne pas les annuler, supprimer ou rembourser individuellement. Leur gestion indépendante n’est pas prise en charge et peut désaligner le récapitulatif conservé.
5. Sur un nouveau créneau, vérifier réellement la création Google/Outlook, l’ajout d’un deuxième contact et l’annulation. Ne pas attendre une correction rétroactive des anciens événements. Le lien de détails nécessite une connexion WordPress et un domaine accessible, pas localhost.
6. Vérifier confirmations et rappels dans les boîtes du contact et de l’hôte. La simulation FluentSMTP visible dans les essais précédents ne prouve pas une livraison réelle. Ne la désactiver que pour une recette explicitement autorisée.
7. Si Stripe est utilisé : en mode test, paiement réussi, refusé, abandon, reprise et webhook ; contrôler montant, statut et places. Vérifier séparément un remboursement. Aucun paiement Stripe réel n’a été exécuté dans cet audit.
8. Avant une campagne à forte affluence, tester les soumissions concurrentes sur le serveur cible. Les refus de capacité sont testés ; aucun test de charge multi-processus n’a été effectué ici.

## Périmètre et exploitation

Événements de groupe sur un créneau unique, devise à deux décimales, paiement natif Stripe ou hors ligne. Coupons, WooCommerce, récurrence, multi-durée, report et réactivation restent hors périmètre personnalisé. Conserver FluentBooking dans la branche compatible 2.4.x et refaire les tests avant toute mise à jour.

Les réponses supplémentaires ne modifient pas les tarifs natifs ; les réservations déjà enregistrées conservent leur snapshot. En cas d’incident, fermer temporairement l’événement aux nouvelles réservations. Ne pas simplement désactiver l’add-on sur des dossiers personnalisés existants : les règles et présentations de ces dossiers en dépendent. Restaurer une sauvegarde uniquement en tenant compte des réservations et paiements survenus depuis.

Voir [les commandes de test](validation.md), [les agendas connectés](agendas-connectes.md) et [le guide](tarifs-par-personne.md).
