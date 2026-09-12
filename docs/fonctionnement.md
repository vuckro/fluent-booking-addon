> Mise à jour alpha.10 : voir [le point d’étape des modules](implementation-modules.md). Le contenu ci-dessous décrit le socle antérieur ; les nouveaux modules et leurs limites sont détaillés dans ce document.

# Fonctionnement et périmètre — alpha.9

## Utilisation

Dans Fluent Booking → Modules, choisir le contexte puis cliquer sur Afficher. « Tous les calendriers » définit les valeurs globales. Un calendrier peut les remplacer ; un événement peut remplacer celles de son calendrier.

Pour chaque réglage, choisir Hériter ou Définir ici. En mode Hériter, la valeur saisie à côté est ignorée. Enregistrer les réglages actualise les valeurs effectives affichées avec leur provenance.

Exemple : maximum global 6, calendrier 4, événement sans surcharge → l’événement utilise 4. Si cet événement définit 2, sa limite devient 2. Le maximum 0 retire seulement la limite supplémentaire de l’add-on. Désactiver les règles sur un événement les neutralise même si elles sont activées globalement.

## Fonction réellement disponible

La règle Participants examine le nombre de personnes représentées dans les données préparées par FluentBooking, au passage dans le hook booking_data. Si les règles sont activées et que le maximum positif est dépassé, elle renvoie un refus avant création de la réservation par le service natif. Une nouvelle installation est désactivée par défaut.

Ce nombre ne garantit pas la prise en charge de personnes que FluentBooking aurait écartées auparavant. Il s’agit d’une limite par demande, pas du nombre total de places restant sur une séance.

## Base technique

PHP avec autoload PSR-4, schéma de configuration strict, stockage versionné dans une option WordPress et les métadonnées FluentBooking. Pas de table supplémentaire. Permissions vérifiées, nonce anti-CSRF, révision et verrou contre l’écrasement de deux sauvegardes concurrentes. Ce verrou protège les réglages, pas les places réservables.

Une seule page d’administration PHP, une feuille CSS isolée et un petit script de thème. La préférence clair/sombre est partagée avec FluentBooking. Le header reprend ses repères visuels mais reste une adaptation distincte, à vérifier après mise à jour du plugin natif.

Les diagnostics montrent les versions et inventorient les anciennes options sans les convertir. Aucun fichier du plugin FluentBooking n’est modifié.

## Limites

- Alpha de fondation, sans parité fonctionnelle avec la version 3.
- Participants sans e-mail, catégories et rôles non implémentés.
- Aucun calcul de prix, traitement de paiement, remboursement ou coupon ajouté.
- Pas de gestion atomique de capacité ni de retenue temporaire de places.
- Modifications, reports, imports directs et toutes les voies API/MCP non couverts par une validation exhaustive.
- Compatibilité testée sur FluentBooking 2.4.0 ; plage acceptée 2.4.x. Pro non requis pour le socle. Autres versions et Multisite non validés.
- Les tests techniques ne remplacent pas une recette de réservation réelle et une vérification visuelle dans le navigateur.

## Évolutions possibles

Avancer besoin par besoin : préciser d’abord comment représenter les participants supplémentaires, puis prototyper le parcours natif et ses validations. Ajouter la tarification seulement lorsque les quantités, commandes et paiements sont cohérents de bout en bout. Traiter ensuite concurrence des places, annulations et reports selon les besoins réels.

Le registre accepte de nouvelles règles PHP. Les champs de configuration restent volontairement explicites : ajouter une règle configurable demande aussi de faire évoluer le schéma et le formulaire. Aucun catalogue dynamique de modules n’est livré.

La migration historique devra être explicite, vérifiable et réversible. L’interface actuelle peut rester simple pendant ces évolutions.
