# Base simplifiée — 4.0.0-alpha.15

## Décision

Conserver un seul module : une limite de personnes par demande de réservation, appliquée au parcours natif FluentBooking. Les fonctions expérimentales ne sont plus proposées ni exécutées.

## Retiré du code livré

- Formulaire de groupe et son adaptateur JavaScript au formulaire Svelte natif.
- Catégories, champs personnalisés, âges et accompagnateurs.
- Tarifs par catégorie, calculs et adaptations des commandes/Stripe.
- Jauges partagées, retenues de places et verrou MySQL de capacité.
- Import de profils, devis REST et API des participants expérimentaux.
- Tests propres à ces moteurs retirés et styles de leur éditeur.

L’historique Git garde ces travaux, sans les embarquer dans le plugin courant.

## Conservé et amélioré

- Une seule page Modules, avec navigation FluentBooking, modes clair/sombre et styles isolés.
- Deux options : appliquer la limite et choisir un maximum. « Niveau supérieur » explique l’héritage ; le nombre devient inactif lorsqu’il est hérité.
- Résumé du réglage enregistré et origine distincte de l’activation et du maximum.
- Accès à la gestion des calendriers ; accès à la page publique du contexte sélectionné lorsque FluentBooking la fournit, dans un nouvel onglet. Une page publique désactivée est signalée, sans être activée automatiquement.
- Contrôle serveur, permissions natives, nonce, validation stricte et protection contre les sauvegardes concurrentes.
- Diagnostics repliés, export de configuration REST authentifié en lecture seule.
- Séparation du header, du formulaire et du raccordement à BookingService pour faciliter les prochaines modifications.

## Données et mise à jour

Avant modification, le site local ne contenait aucun profil expérimental enregistré/actif, aucune métadonnée de participants et aucune retenue de capacité. Aucune donnée utilisateur n’a été supprimée.

Pour les autres installations, les profils historiques sont conservés en lecture seule, y compris lors d’une sauvegarde des limites simples. Un profil encore activé bloque les nouvelles demandes concernées : il faut vérifier les anciens engagements avant de supprimer manuellement sa configuration. La modification d’horaires et la réactivation des anciennes réservations enrichies restent protégées. L’export/effacement de leurs données par l’e-mail du réservant est conservé. La table historique n’est plus créée ni utilisée pour de nouvelles retenues.

## Limites actuelles

- Alpha : les contrôles locaux ne constituent pas une certification de stabilité en production.
- Recette locale sur FluentBooking 2.4.0 ; garde de compatibilité 2.4.x. PHP 8.1 minimum.
- Le module complète les limites natives : il ne gère pas la capacité cumulée d’une séance, les tarifs ou les paiements.
- La limite intervient à la création via BookingService. Les modifications, reports, SQL direct et intégrations contournant ce service ne sont pas couverts par la limite simple.
- Ce n’est pas un remplacement fonctionnel de la version 3.3.6. Pas de migration automatique des anciennes options.
- Le header reprend des ressources natives mais reste une présentation propre à l’extension ; vérifier son rendu à chaque mise à jour majeure de FluentBooking.
- Recette visuelle au navigateur restant à faire. Aucun paiement réel n’a été testé.

## Suite conseillée

Valider ce seul parcours en conditions réelles de test avant une version stable. Ajouter ensuite une fonction à la fois, avec un besoin explicite, une interface simple et sa recette complète. Les prix doivent rester natifs tant qu’une intégration de paiement complète n’est pas validée.
