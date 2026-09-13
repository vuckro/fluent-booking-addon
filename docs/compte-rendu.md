# Compte rendu — alpha.21

## Retiré

- Bloc et fonctionnalité « Limite de participants » propres à l’add-on.
- Réglages globaux, réglages de calendrier et héritage.
- Registre de règles, schéma de résolution, validateur de limite et adaptation de la limite publique.
- Case « Décompter une place par personne » et sa clé de stockage.
- API REST de configuration dédiée, non utilisée par l’interface.
- CSS, JavaScript, tests et documents devenus obsolètes.

## Conservé

- Invités rattachés au réservant avec identité obligatoire, facultative ou masquée.
- Champs supplémentaires, prix par personne/forfait, suppléments et remplacements de tarif.
- Contrôles de sécurité, permissions, révisions, validation serveur et protection des anciennes réservations.
- Capacité et limite natives FluentBooking comme sources uniques.

## Interface

Titre « Modules », sélection d’événement de groupe uniquement, bloc de fonctionnalité indépendant et « Résumé des réglages » séparé. Les valeurs enregistrées figurent dans le résumé. Les liens permettent d’ouvrir la réservation, les questions et les paiements natifs.

## Données

Anciennes configurations sauvegardées puis migrées. Limites natives inchangées sur ce site ; choix tarifaires conservés. Aucune réservation ou commande modifiée. Voir [migration](migration.md).

La version est locale ; aucun push GitHub n’a été effectué. Voir [validation](validation.md) pour les contrôles réalisés et les limites de preuve.
