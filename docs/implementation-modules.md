# Modules — point d’étape alpha.10

La liste fournie est une cible produit, pas une description de l’alpha.9. Le développement ci-dessous est une première tranche fonctionnelle. **La cible entière n’est pas terminée ; cette version ne constitue pas une livraison de production.**

## Ce qui a été développé

- Profil configurable et héritable au niveau global, calendrier ou événement. Le profil est remplacé en bloc : pas d’héritage implicite entre deux listes de types.
- Types génériques : libellé, tarif, poids en places, âge minimum/maximum, accompagnateur requis, champs texte supplémentaires obligatoires ou facultatifs.
- Formulaire de participants avec ajout/retrait, nom et prénom, e-mail masqué/facultatif/obligatoire, date de naissance si nécessaire. Le réservant conserve son adresse native et doit être ajouté à la liste s’il participe.
- Validation serveur stricte de toute la liste. Les prix et poids envoyés par un client ne sont jamais acceptés.
- Une réservation native principale et une liste de participants en métadonnées. Aucun faux e-mail ni compte/contact artificiel. Les participants ne sont pas des réservations natives individuelles.
- Calcul en centimes, gratuités par type, récapitulatif local, montant canonique de la commande native et correction des arrondis des lignes. Adaptateur Stripe Checkout en centimes ; Stripe embarqué conserve son chemin natif.
- Capacité par événement ou jauge partagée par identifiant, avec poids par type et chevauchement des créneaux. La disponibilité affichée est réduite en conséquence.
- Retenue persistante avant création, protégée par un verrou MySQL. Les annulations et suppressions natives libèrent les places. Une retenue non rattachée ne disparaît pas silencieusement après un incident.
- Prix et participants figés pour la réservation ; un changement de tarif ne modifie pas les anciennes réservations.
- Copie/import de profil, export de configuration et lecture des participants par API REST protégée par les permissions natives.
- Hook `waaskit_fluent_booking/party_created` pour les automatisations. Ce hook ne configure pas automatiquement un scénario FluentCRM ou un webhook distant.
- Export/effacement des données de participants via les outils de confidentialité WordPress, à partir de l’e-mail du réservant. Les éléments nécessaires au montant et à la capacité sont conservés.
- Données conservées à la désinstallation par défaut. Suppression volontaire possible via `FBA_DELETE_DATA_ON_UNINSTALL=true`, avec parcours des sites en activation Multisite.

## Configuration

Dans **Fluent Booking → Modules**, sélectionner le contexte. Pour **Participants, tarifs et capacités**, choisir **Définir ici**, déplier le profil, puis l’activer et enregistrer. Les nouvelles fonctions sont désactivées par défaut.

La limite simple des premières alphas reste indépendante. Si elle est activée, elle s’ajoute au maximum du profil.

Le champ « Jauge partagée » contient un identifiant comme `atelier-samedi`. Tous les événements qui le partagent doivent activer le profil avec une capacité identique. La jauge s’applique aux horaires UTC qui se chevauchent ; le même identifiant peut ainsi être réutilisé d’un samedi à l’autre. Le rattachement d’une configuration déjà réservée à une autre jauge est refusé.

Les limites de disponibilité natives restent applicables : le module réduit une disponibilité, il ne force pas l’ouverture d’un créneau refusé par FluentBooking. Il faut donc configurer la capacité native en cohérence avec celle du profil.

Les tarifs sont saisis dans la devise FluentBooking. Pour un parcours payant, activer les paiements natifs et Stripe ou le paiement hors ligne sur l’événement. Les prix fixes précédents sont remplacés par les lignes calculées lorsque le profil de tarification est actif.

## Limites explicites et travail restant

| Cible | État |
| --- | --- |
| Participants, types, champs texte, âge, min/max, accompagnateur | Implémentés ; recette navigateur restante |
| Jauge et concurrence | Implémentées ; tests DB locaux réussis |
| Tarifs par type et montants natifs | Implémentés ; tests des commandes/arguments Stripe réussis, paiement Stripe de test restant |
| Coupons fixes/pourcentage avec tarifs par type | Refusés avant création pour cette tranche ; intégration et recette à faire |
| Acompte, solde et remboursements partiels | Non implémentés |
| Taxes | Aucun moteur fiscal ajouté ; prix du profil traités comme montants finaux, compatibilité fiscale à préciser et tester |
| Modification de participants, report, réactivation après libération | Bloqués sur les réservations enrichies jusqu’à un parcours de modification atomique |
| Administration détaillée des participants | API et résumé de confirmation présents ; écran dédié restant |
| Messages métier personnalisables, ratios d’accompagnateurs | Non implémentés ; un type requis doit seulement être présent |
| FluentCRM, webhooks | Point d’extension fourni ; mappings et scénarios prêts à l’emploi restants |
| WooCommerce / FluentCart | Non pris en charge pour les tarifs du profil |
| Multisite | Stockage séparé par préfixe, contexte de prix séparé par blog ; recette réseau complète restante |
| Migration depuis 3.x | Inventaire uniquement, aucune conversion automatique |
| Frontend | Adaptateur léger du champ texte natif Svelte 2.4 ; shortcode/page native à valider visuellement, intégrations tierces non garanties |
| Traductions | Domaine WordPress utilisé pour certains textes ; couverture complète et catalogues restants |

FluentBooking et Pro 2.4.0 sont les versions de recette. Les modifications directes en SQL et les insertions qui contournent le modèle natif sont hors contrat. Les événements multiples/récurrents ne sont pas pris en charge par cette tranche.

La réservation principale est comptée comme une réservation par les statistiques natives. Le nombre de personnes se trouve dans `fba_party_v1.count` et le stock additionnel dans la table `fba_capacity`.

## Vérifications exécutées

- Tests PHP de schéma, types, âge, falsification du prix, gratuité, champs et chevauchement.
- Recette de configuration existante, permissions et révisions.
- Création avec le véritable BookingService dans une transaction annulée : persistance, places, annulation, refus des contournements, montant de commande, lignes et arguments Stripe.
- Deux processus PHP/MySQL concurrents sur une seule place : une seule retenue acceptée.
- Aucun appel Stripe, aucun e-mail de test envoyé et aucune réservation de test conservée. La nouvelle table vide est installée sur le site local.
- Aucune validation visuelle dans Chrome, aucun vrai paiement : ces preuves restent à obtenir avant activation sur un événement utilisateur.

## Stockage et incidents

`wp_fba_capacity` (préfixe propre au site) stocke une retenue et son rattachement à la réservation. La migration est idempotente et s’exécute à l’ouverture de l’administration par un administrateur. Les tables natives ne sont pas modifiées.

Les diagnostics affichent le nombre de retenues non rattachées. Ne pas les effacer automatiquement : comparer la date, l’événement et les réservations créées autour de l’incident, puis rattacher ou libérer uniquement après vérification. Cette stratégie privilégie un blocage temporaire explicite à une surréservation silencieuse. L’interface de résolution assistée reste à développer.

L’effacement WordPress couvre les demandes associées à l’e-mail du réservant. La recherche d’un participant secondaire par son propre e-mail reste à compléter.

## API

- `POST /wp-json/fluent-booking-addon/v1/quote/{event_id}` : `participants` et `start_time` UTC (`YYYY-MM-DD HH:mm:ss`). Lecture/calcul uniquement, aucune retenue. Le serveur de réservation recalcule tout.
- `GET /wp-json/fluent-booking-addon/v1/bookings/{id}/participants` : réservé aux personnes autorisées à gérer l’événement.
- `GET /wp-json/fluent-booking-addon/v1/configuration/{scope}/{id}` : export réservé aux personnes autorisées à gérer le contexte. `scope` : `site`, `calendar` ou `calendar_event`.

Pour les accès REST authentifiés, utiliser le mécanisme standard WordPress : session avec nonce REST ou mot de passe d’application autorisé. Aucune donnée personnelle n’est exposée par la route de devis.
