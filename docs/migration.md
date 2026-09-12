# Migration et exploitation

La version actuelle inventorie `fbgrp_one_per_spot`, `fbgrp_price_per_guest` et `fbgrp_hide_guest_email` sans modifier ces données. L'inventaire est visible uniquement aux administrateurs dans les diagnostics. Il n'exécute aucune migration et ne touche pas aux réservations ni aux commandes.

La facturation par personne ne peut pas être déduite d'un ancien prix seulement visuel. Une migration future devra produire des correspondances explicites et conserver les instantanés appliqués aux réservations.

## Retour à la version précédente

1. Mettre en pause les événements utilisant des règles nouvelles si leur retrait pouvait modifier les admissions.
2. Désactiver la version alpha.
3. Restaurer le code du tag `archive/pre-rewrite-2026-09-12` dans le dossier de plugin approprié, puis activer l'ancienne version si nécessaire.
4. Vérifier ses options et les parcours avant réouverture.

Ne pas restaurer aveuglément une ancienne base si des réservations ont été créées entre-temps. Le socle conserve ses réglages après désactivation ou suppression et n'ajoute aucun traitement de suppression automatique.

## Verrou de configuration

En cas d'arrêt brutal de PHP pendant une écriture, un verrou peut persister. Vérifier qu'aucune écriture n'est encore en cours, sauvegarder puis supprimer uniquement l'option `waaskit_fluent_booking_config_lock_<scope>_<id>` concernée. Aucun déverrouillage automatique par expiration n'est utilisé, pour éviter qu'un processus lent continue à écrire après perte de son verrou.

## Distribution

Le ZIP doit contenir le point d'entrée, `autoload.php`, `app/`, README et documentation. Exclure `.git`, les fichiers de tests, les sauvegardes et les secrets. L'identité WordPress comprend le dossier et le fichier principal : conserver le fichier historique ne règle pas à lui seul un changement de dossier.

L'interface de cette alpha est en français. Le domaine de traduction est déclaré ; la couverture gettext complète et les catalogues de traduction restent à finaliser avant distribution multilingue.

## Profils alpha.10–14 retirés

Les profils sont conservés et non modifiables dans cette base. Un profil activé suspend les nouvelles réservations concernées. Sauvegarder les données et vérifier les réservations, paiements et retenues historiques avant toute suppression manuelle de cette clé. Aucune suppression de profil ni réouverture automatique n’est effectuée. Les outils de confidentialité restent accessibles pour les données liées à l’e-mail du réservant.
