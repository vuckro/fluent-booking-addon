# Rapport de validation — 12 septembre 2026

## Environnement observé

- Site Local accessible sur localhost, WordPress 7.1.
- FluentBooking 2.4.0 et Pro 2.4.0 actifs.
- PHP du site : 8.2.29. CLI complémentaire : 8.4.10.
- Deux événements préexistants, zéro réservation ; état final identique pour ces données.
- Fondation active, aucune configuration activée à l'issue des tests.

## Tests exécutés et réussis

- 12 assertions unitaires sur PHP 8.2.29 et 8.4.10 : héritage, valeurs explicites, types invalides, limites, registre et doublons.
- 15 assertions d'intégration sur les deux versions PHP : permissions, persistance via les métadonnées natives, révisions, retour à l'héritage, neutralité, hook natif, erreur préexistante, rendu HTML et cache obsolète.
- 7 contrôles HTTP sur le serveur Local : administration authentifiée 200, sauvegarde 302, relecture, sauvegarde obsolète 409, nonce invalide 403, cible inexistante 403 et redirection de l'utilisateur anonyme.
- Deux processus simultanés de sauvegarde de configuration : exactement une écriture acceptée, l'autre refusée. Ce test concerne la configuration, pas l'attribution de places.
- Vérification syntaxique PHP et `git diff --check`.

Les tests d'intégration annulent leurs écritures par transaction. Les contrôles HTTP et de concurrence ont été suivis d'une restauration de la configuration d'origine. La session temporaire a été révoquée. Aucun paiement, e-mail ou réservation de test n'a été créé.

## Non validé et non livré

- Participants sans e-mail, catégories et rôles métier.
- Paiement réel ou test auprès d'une passerelle ; coupons et montants des commandes.
- Attribution concurrente de places, annulation, report, retenues et remboursements.
- Couverture exhaustive des entrées admin/API/MCP/import et modifications de réservations.
- Panneau monté dans l'application JavaScript native ; la version livrée utilise une page WordPress contextuelle.
- Recette visuelle par navigateur, mobile et accessibilité complète.
- Matrice WordPress étendue, Multisite, versions FluentBooking autres que 2.4.0.
- Migration appliquée et parité avec l'ancienne extension.

Le workflow CI exécute la syntaxe et les tests unitaires PHP 8.1–8.4. Les intégrations WordPress locales ne sont pas exécutées automatiquement sur GitHub.
