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

## Présentation alpha.2

Après la réorganisation : 12 assertions unitaires, 16 d’intégration et 14 contrôles HTTP réussis. Les contrôles HTTP couvrent les quatre contextes d’écran, le chargement CSS/JS et leur absence du tableau de bord général WordPress, ainsi que les protections de sauvegarde. Les données temporaires et la session de test ont été retirées.

Les règles, le stockage et les permissions sont inchangés. La structure responsive est fournie ; aucune inspection visuelle par navigateur n’a été réalisée pour cette mise à jour.

## Paramètres natifs alpha.3

- 12 assertions unitaires et 25 assertions d'intégration réussies sur PHP 8.2.29, WordPress 7.1, FluentBooking et Pro 2.4.0.
- Le menu envoyé à l'application native contient Modules et la route existante `configure-integrations` avec `settings_key=waaskit_addon` ; Pro est actif.
- GET/POST des endpoints REST natifs vérifiés : schéma et valeurs, écriture/relecture, refus de révision obsolète, limite invalide, accès anonyme et nonce invalide.
- L'écran FluentBooking ne charge aucun CSS/JavaScript de présentation de l'add-on.
- Le raccourci Modules redirige vers l'URL native. Les accès explicites au global contextuel, au calendrier et aux diagnostics restent disponibles.
- Données de test retirées et sessions temporaires révoquées.

Le composant Vue générique, sa route, ses champs supportés et ses appels REST ont été vérifiés dans le bundle installé. Le rendu effectif dans un navigateur, les transitions entre rubriques et le mobile restent à confirmer visuellement. Aucun résultat de capture visuelle n'est revendiqué. L'intégration concerne les réglages globaux ; les réglages contextuels utilisent toujours la page WordPress.

## Retour minimal alpha.4

Les sections précédentes décrivent les versions historiques ; l'intégration native alpha.3 est retirée.

- 12 assertions unitaires et 18 assertions d'intégration locale réussies.
- 7 contrôles HTTP : affichage authentifié sans redirection native, sauvegarde, relecture, refus de révision obsolète, nonce invalide, cible inexistante et accès anonyme.
- Syntaxe PHP validée. Configuration HTTP restaurée et session temporaire révoquée.
- Aucune recette visuelle par navigateur effectuée ; rendu basé uniquement sur les composants standards WordPress.

## Présentation alpha.5

18 assertions d’intégration et 7 contrôles HTTP relancés avec succès. Syntaxe PHP et diff validés. La configuration HTTP est restaurée et la session révoquée. La présentation repose sur les captures fournies ; pas d’inspection visuelle par navigateur.

## Header et thème alpha.7

Syntaxe PHP et JS, 18 assertions d’intégration, contrôles HTTP des deux headers et 7 contrôles de sauvegarde réussis. Le script de thème est testé dans un environnement simulé : préférence existante, changement clair/sombre, clés partagées, événements storage, mode système et stockage indisponible. Ces contrôles ne constituent pas une recette visuelle ni un essai réel multi-onglets dans un navigateur.
