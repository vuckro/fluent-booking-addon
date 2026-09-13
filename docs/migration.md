# Migration vers la nouvelle base

## Depuis la branche main v3.3.6

La v4 est une réécriture en alpha, pas une mise à jour transparente de la v3. Les anciennes clés `fbgrp_one_per_spot`, `fbgrp_price_per_guest` et `fbgrp_hide_guest_email` ne sont pas automatiquement converties. Elles restent dans les données mais ne pilotent plus le plugin.

Effectuez la transition sur une copie de test : sauvegardez les données et le code v3, vérifiez PHP 8.1+ et FluentBooking 2.4.x, puis configurez explicitement les événements dans Modules. Vérifiez les réservations et paiements historiques avant remplacement en production. Les coupons pris en charge par la v3 ne sont pas pris en charge par les personnalisations de cette alpha.

Le script ci-dessous concerne uniquement les anciennes configurations des alphas v4. Il ne convertit pas les options v3. Un push GitHub ne déploie ni le plugin ni des réglages sur un autre site.

## Migration des anciennes limites des alphas vers FluentBooking

## Site local traité le 13 septembre 2026

La migration vers le schéma 2 a été effectuée avec sauvegarde préalable. Aucune ancienne limite supplémentaire n’était active : les questions et limites natives des événements 1 et 2 sont restées identiques. Les options de l’événement 2 ont été conservées, notamment ses choix Adulte/Enfant et leurs tarifs. La seule clé invitée retirée est `per_person_seats`, devenue inutile : une personne occupe toujours une place.

Sauvegarde locale, hors plugin : `../.local-backups/native-settings-2026-09-13.json`. Elle n’est pas incluse dans le ZIP ou Git.

## Autres installations provenant des alphas précédentes

Effectuer une sauvegarde de base, utiliser des tables transactionnelles InnoDB et suspendre les modifications des réglages pendant la migration. Le script ne modifie aucune réservation ni commande.

Simulation :

```sh
WAASKIT_WP_PATH=/chemin/wordpress php scripts/migrate-native-limits.php
```

Application avec fichier de sauvegarde neuf, situé hors répertoire public :

```sh
WAASKIT_WP_PATH=/chemin/wordpress \
FBA_MIGRATE_APPLY=1 \
FBA_MIGRATE_BACKUP=/chemin/prive/sauvegarde.json \
php scripts/migrate-native-limits.php
```

Le script résout une dernière fois les anciennes valeurs site → calendrier → événement. Une restriction active est transférée vers la question native d’invités en conservant la valeur la plus restrictive. Pour les événements individuels, la limite native porte sur les invités supplémentaires ; pour les groupes, elle inclut le réservant. Une limite d’une personne désactive les invités. Le script n’active jamais les invités précédemment désactivés.

Ensuite, il conserve les options invités par événement, retire les configurations globales/calendriers et écrit le schéma 2. Les écritures sont relues et regroupées dans une transaction. Les changements détectés pendant le traitement provoquent un arrêt. Un profil expérimental actif nécessite un examen manuel et bloque la conversion.

Le script est idempotent : une installation déjà migrée n’est pas retraitée. Il ne crée aucune règle pour les futurs événements : ceux-ci suivent exclusivement FluentBooking.

## Retour arrière

Revenir au code alpha.20 puis restaurer les métadonnées de configuration et de questions contenues dans la sauvegarde, ainsi que l’ancienne option globale ; retirer le marqueur `fba_native_settings_migrated`. Ne pas restaurer une base complète plus ancienne après de nouvelles réservations. Il n’existe pas de restauration automatique dans l’interface.
