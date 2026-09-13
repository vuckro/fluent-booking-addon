# Recette finale — 13 septembre 2026 — alpha.23

**Prêt pour la recette fonctionnelle locale**, dans le périmètre décrit ci-dessous. Ce bilan ne vaut pas validation d’un encaissement réel ou de tous les environnements WordPress.

## Résultats

| Domaine | Vérification | Résultat |
| --- | --- | --- |
| Serveur | 94 contrôles : droits, réglages, tarifs, commandes, places, annulation, migrations et anciens modes | Réussis |
| Formulaires | 5 scénarios JavaScript : identité, champs, tarifs, suppression, repli et parcours natif | Réussis |
| Parcours FluentBooking réel sous jsdom | Date → créneau → formulaire → retour → formulaire ; invité conservé, absence de doublon, choix transmis à une requête simulée | Réussi |
| Chargement public | HTML courant, ressources alpha.23, tarif initial adulte 70 € | Vérifié sur localhost:10038 |
| Accès admin | Lien Réglages dans la ligne de la liste WordPress des extensions | Vérifié |
| Code | Syntaxes PHP/JavaScript, espaces du diff, archive de distribution | Vérifiés |

Les tests WordPress utilisent des transactions annulées et bloquent les envois et HTTP externes. Aucun enregistrement de test n’a été conservé. Le parcours JS utilise les fichiers réels de FluentBooking et des réponses réseau simulées : il ne soumet aucune réservation au site.

## Dernières corrections

- Le moteur de prix rejette explicitement un invité sans tarif, même si ce moteur est appelé directement.
- Les champs d’identité obligatoires portent un astérisque.
- Le résumé précise qu’il montre les valeurs enregistrées et se met à jour après enregistrement.
- Le test du parcours natif est conservé dans `tests/native-page-dom.cjs` pour les prochaines évolutions.

## Recette à faire dans le navigateur

Sur l’événement collectif configuré avec Adulte 70 €, Enfant 55 €, capacité 5 :

1. Sans invité : sélectionner adulte → **70 €**, puis enfant → **55 €**.
2. Choisir adulte pour soi, ajouter un invité nommé et choisir enfant → **125 €**, **2 places**. Le courriel de cet invité doit être masqué.
3. Supprimer l’invité → **70 €**. Revenir au calendrier puis au formulaire : vérifier la conservation des données et l’absence de doublons.
4. Ajouter des invités jusqu’à cinq personnes au total : l’ajout supplémentaire est bloqué. Une disponibilité devenue insuffisante doit être refusée par le serveur.
5. Ajouter temporairement une question obligatoire par invité dans Modules, enregistrer et vérifier le champ public ainsi que son message de validation.
6. Contrôler visuellement desktop/mobile, clair/sombre, navigation clavier et les liens admin.
7. Faire une réservation de recette hors ligne, puis vérifier la commande, les participants, les places restantes et les notifications reçues. Tester ensuite Stripe en mode test avant tout paiement réel.

Les points 6 et 7 ne sont pas validés par les tests DOM/serveur exécutés ici : aucun navigateur piloté, envoi reçu ni encaissement réel n’a été utilisé pour cette recette finale.

## Périmètre conservé

FluentBooking 2.4.x, événement de groupe, durée unique, devise à deux décimales, paiement natif Stripe ou hors ligne. Les coupons, WooCommerce, le multi-durée, le report et la réactivation automatique restent hors périmètre. Les anciens montants enregistrés sont conservés.

Pour reproduire le test du vrai formulaire sous jsdom, fournir `WAASKIT_WP_PATH`, `FBA_PAGE_HTML` (HTML de la page publique du scénario Adulte 70 / Enfant 55) et `FBA_SLOTS_JSON` (réponse publique de disponibilités). Exécuter `node tests/native-page-dom.cjs` avec jsdom accessible. Les fixtures locales et le code natif ne sont pas embarqués dans l’extension.

Voir le [guide de configuration des tarifs](tarifs-par-personne.md).
