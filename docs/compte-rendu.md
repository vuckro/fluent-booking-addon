# Compte rendu — base minimale

12 septembre 2026 · 4.0.0-alpha.4

## Décision

Revenir à une seule page **Fluent Booking → Modules**, utilisant la présentation standard WordPress. L'intégration dans les paramètres internes de FluentBooking a été supprimée, ainsi que le prototype contextuel commencé puis interrompu.

## Ce qui a été retiré

- Adaptateur NativeSettings, hooks de formulaire natif, entrée dans les paramètres FluentBooking et redirections.
- Navigation de tableau de bord, panneaux personnalisés, onglets et liens entre deux interfaces.
- CSS et JavaScript de présentation propres à l'extension.
- Dépendance à Pro pour afficher les réglages.

## Ce qui reste

Un sélecteur de contexte (global, calendrier, événement), deux réglages (activation et maximum de participants), un bouton Enregistrer et des diagnostics repliés.

Le socle PHP conserve le stockage versionné, l'héritage, la validation des valeurs, les permissions, les nonces et la protection contre les sauvegardes concurrentes. Les valeurs false et 0 restent explicites. Aucune configuration existante n'a été effacée ou migrée.

L'extension dépend toujours de FluentBooking pour les calendriers, événements, métadonnées, permissions et le contrôle expérimental des nouvelles demandes. Aucun fichier du plugin FluentBooking n'a été modifié.

## Ce qu'il faut savoir

Il s'agit d'une fondation alpha, pas d'une réécriture fonctionnellement complète de la version 3. La limite de participants est expérimentale et ne garantit pas la capacité disponible d'une séance. Les participants sans e-mail, prix, paiements, reports et migration ne sont pas livrés.

Les noms des calendriers et événements existants sont des contenus utilisateurs : ils n'ont pas été renommés.

## Vérifications

12 assertions unitaires, 18 assertions d'intégration et 7 contrôles HTTP réussis sur le site Local. Les données temporaires ont été restaurées et la session révoquée. Syntaxe PHP vérifiée. Aucun paiement ni réservation de test créé. Pas de contrôle visuel dans un navigateur.

## Suite proposée

Conserver cette interface minimale. Définir ensuite un seul besoin métier prioritaire, ses règles et ses cas de test avant d'ajouter du code. La priorité est de valider le comportement de réservation attendu ; aucun nouveau tableau de bord ou moteur de modules n'est nécessaire à ce stade.
