# Validation alpha.15

## Exécuté localement

- 19 assertions unitaires : valeurs strictes, héritage, faux/0 explicites, limites, registre et validation des deux options du formulaire.
- 23 assertions d’intégration WordPress : permissions, stockage, révisions concurrentes, refus natif, rendu HTML, retrait du runtime expérimental, conservation et protection des profils historiques.
- 7 assertions avec BookingService natif : les événements individuel et de groupe acceptent deux personnes, refusent une troisième et n’insèrent rien en cas de refus.
- Syntaxe PHP et JavaScript, intégrité du ZIP et absence des anciens moteurs dans celui-ci.

FluentBooking/Pro 2.4.0, WordPress 7.1, PHP Local 8.2.29. Les écritures des tests sont annulées par transaction. Le test BookingService bloque e-mails et HTTP. Aucun paiement ni réservation de test n’est conservé.

## À vérifier avant production

- Parcours navigateur réel : sélection du contexte, sauvegarde, héritage, clavier, rendu clair/sombre et mobile.
- Ouverture des pages publiques activées, navigation vers les calendriers et comportement lorsque la page publique est désactivée.
- Parcours public réel de réservation et intégrations tierces utilisées par le site.
- Compatibilité avant toute mise à jour de FluentBooking. Les tests locaux ne justifient pas de déclarer toutes les versions 2.4.x certifiées.

Les tests de paiement et de concurrence de capacité des anciennes alphas ne valident pas cette base : ces fonctions sont retirées. La CI existante vérifie syntaxe et tests unitaires sur PHP 8.1–8.4 ; elle n’a pas été relancée à distance pour cette modification locale.
