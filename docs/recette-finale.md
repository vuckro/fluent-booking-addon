# Recette avant main — 13 septembre 2026 — alpha.26

La publication sur main reste une **alpha prête aux essais**, dans le périmètre ci-dessous.

## Vérifications exécutées

- **111 contrôles PHP** réussis : unité 21, administration 15, invités 24, ancien moteur de prix 19, tarifs natifs 26, migration 6.
- **6 scénarios DOM** réussis : administration, invités, identités et anciens prix, tarifs natifs, informations seules, parcours du vrai bundle FluentBooking.
- Syntaxe de tous les fichiers PHP et JavaScript, vérification du diff et archive ZIP.
- Page publique localhost HTTP 200 ; JavaScript servi identique au fichier du dépôt.
- Réglages et réservations de test restaurés. Tests transactionnels locaux avec neutralisation des emails et appels externes.

Les scénarios couvrent notamment 70/55/125 €, rejet des tarifs falsifiés, cinq personnes pour cinq places, refus si capacité insuffisante, suppression et annulation des places rattachées, stabilité des montants historiques, export/effacement, champs obligatoires et bornes numériques. Les informations peuvent être activées sans tarification ; le paiement natif reste alors intact, y compris dans sa préparation.

Le parcours du bundle natif se déroule sous jsdom, sur une fixture HTML locale avec configuration temporaire injectée hors site : date → créneau → formulaire → retour → formulaire → soumission simulée. Aucune réservation ni aucun paiement réel n’est envoyé. Il vérifie la conservation des invités, l’absence de doublons et la transmission du tarif choisi.

## Correction issue de l’audit

Le démarrage d’un paiement en mode informations seules pouvait réinjecter les tarifs du snapshot. Le contexte de tarification est désormais réinitialisé et ce mode laisse les paramètres natifs intacts. Un test de régression couvre le callback, sans exécuter le prestataire de paiement.

## Limites de validation

Environnement testé : WordPress 7.1, FluentBooking/Pro 2.4.0 installés localement, PHP 8.2.29. La plage PHP 8.1+ n’a pas été testée sur chaque version. Compatibilité annoncée limitée à FluentBooking 2.4.x.

Pas de validation universelle des thèmes/extensions, de contrôle visuel navigateur dans cette recette, de confirmation de réception des emails ou de paiement Stripe de bout en bout. Ces points restent à vérifier sur le site cible avant production :

1. Interface desktop/mobile, clair/sombre, navigation clavier.
2. Réservation hors ligne : commande, participants, places restantes et communications.
3. Stripe en mode test : paiement réussi/refusé, retour et confirmation.
4. Migration sur une copie du site si départ depuis v3.3.6.

Les réservations de groupe sur créneau unique sont couvertes. Coupons, WooCommerce, multi-durée, reports et réactivation automatique restent exclus des personnalisations.

Voir [les tests reproductibles](validation.md), [le guide](tarifs-par-personne.md) et [la migration](migration.md).
