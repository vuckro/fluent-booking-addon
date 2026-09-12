# Validation alpha.17

## Exécuté localement

- 22 assertions unitaires : valeurs strictes, héritage, faux/0 explicites, limites, registre et validation des choix simplifiés du formulaire.
- 23 assertions d’intégration WordPress : permissions, stockage, révisions concurrentes, refus natif, rendu HTML, retrait du runtime expérimental, conservation et protection des profils historiques.
- 7 assertions avec BookingService natif : les événements individuel et de groupe acceptent deux personnes, refusent une troisième et n’insèrent rien en cas de refus.
- Syntaxe PHP et JavaScript, intégrité du ZIP et absence des anciens moteurs dans celui-ci.

FluentBooking/Pro 2.4.0, WordPress 7.1, PHP Local 8.2.29. Les écritures des tests sont annulées par transaction. Le test BookingService bloque e-mails et HTTP. Aucun paiement ni réservation de test n’est conservé.

## Vérifications ciblées supplémentaires

16 assertions sur l’événement 2 : limite publique 2, questions natives inchangées, saisie validée avant troncature, création native de 2 places sur 5, refus du troisième participant et des e-mails dupliqués, commande native 20 × 2 = 40, limite 1, restitution exacte des réglages et absence de réservations de test.

La page publique a été lue par HTTP : le champ invités reçoit la limite actuellement enregistrée (1). Une requête HTTP volontairement incomplète, donc incapable de créer une réservation, avec un invité a reçu HTTP 422 et le message de limite. Ce contrôle ne remplace pas une recette visuelle du composant Svelte.

Les tests ne prouvent pas une exclusion atomique entre deux réservations concurrentes : cette version conserve le moteur de capacité natif.

## À vérifier avant production

- Parcours navigateur réel : sélection du contexte, sauvegarde, héritage, clavier, rendu clair/sombre et mobile.
- Ouverture des pages publiques activées, navigation vers les calendriers et comportement lorsque la page publique est désactivée.
- Parcours public réel de réservation et intégrations tierces utilisées par le site.
- Compatibilité avant toute mise à jour de FluentBooking. Les tests locaux ne justifient pas de déclarer toutes les versions 2.4.x certifiées.

Les tests de paiement et de concurrence de capacité des anciennes alphas ne valident pas cette base : ces fonctions sont retirées. La CI existante vérifie syntaxe et tests unitaires sur PHP 8.1–8.4 ; elle n’a pas été relancée à distance pour cette modification locale.
