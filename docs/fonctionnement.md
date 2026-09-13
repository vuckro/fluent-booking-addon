> **Alpha.22 :** le mode recommandé est désormais [Un tarif FluentBooking par personne](tarifs-par-personne.md). Les passages ci-dessous sur le multiplicateur et les champs tarifaires décrivent le mode antérieur, conservé pour les configurations existantes.

# Utiliser Modules

L’add-on personnalise uniquement les invités des événements de groupe. Il ne possède plus de module « Limite de participants » ni de réglages globaux ou par calendrier.

## Où régler quoi ?

| Besoin | Emplacement |
| --- | --- |
| Nombre de places du créneau | Événement FluentBooking |
| Autorisation des invités et maximum par réservation | Questions FluentBooking → Invités supplémentaires |
| Disponibilités, durée, lieu | FluentBooking |
| Tarif de base, devise et moyen de paiement | Paiements FluentBooking |
| Nom/courriel obligatoire, facultatif ou masqué | Modules → événement |
| Champs et choix tarifaires des invités | Modules → événement |

## Configuration

1. Sélectionner un événement de groupe dans **Modules**, puis **Afficher les réglages**. Le lien de réservation est placé à côté.
2. Dans les questions FluentBooking, activer **Invités supplémentaires** et régler le maximum souhaité. Le lien figure dans **Résumé des réglages**.
3. Cocher **Personnaliser la réservation avec invités** : les options apparaissent. Décocher masque les options sans effacer les valeurs affichées ; enregistrer pour appliquer.
4. Choisir le tarif de base **par personne** ou **une fois pour la réservation**.
5. Choisir pour le nom et le courriel : obligatoire, facultatif ou masqué.
6. Ajouter uniquement les champs nécessaires : liste, radios, case, texte ou nombre.
7. Enregistrer. Le résumé inférieur décrit les réglages enregistrés, pas les modifications encore non sauvegardées.

## Calcul des prix

Chaque choix de liste, radio ou case peut n’avoir aucun effet, ajouter un supplément ou remplacer le tarif de cet invité. Pour une liste : les montants sont saisis dans le même ordre que les choix, un par ligne. Pour une case : un seul montant s’applique lorsqu’elle est cochée. Les montants sont dans la devise FluentBooking.

Le réservant paie le tarif de base. Chaque invité part de ce même tarif si le mode par personne est coché, sinon de 0. Un choix peut remplacer ce montant ; les suppléments s’ajoutent ensuite. Un seul champ peut remplacer le tarif pour éviter les contradictions. Les suppléments ne sont pas multipliés par la taille du groupe.

Exemple : base 50 €, adulte 50 €, enfant 25 €, supplément 10 €. Réservant + adulte avec supplément + enfant = **135 €**, pour **3 places**. Un invité gratuit consomme aussi une place.

## Informations et réservations

Les invités personnalisés sont toujours rattachés au réservant, qui reçoit les communications du groupe. Aucun faux courriel ni contact individuel n’est créé. Un courriel facultatif est validé lorsqu’il est renseigné. Les réponses et montants sont conservés avec la réservation ; modifier un tarif ultérieurement ne change pas les anciennes commandes.

L’annulation ou la suppression du réservant libère également les places rattachées. Les outils de confidentialité WordPress couvrent les réponses et noms conservés dans ces places.

## Limites actuelles

- FluentBooking 2.4.x ; événements de groupe sur un créneau unique.
- Choix payants : tarif de base unique, paiement natif activé, devise à deux décimales, Stripe ou paiement hors ligne.
- Coupons, WooCommerce, autres passerelles, récurrence, reports et réactivations non pris en charge dans le mode personnalisé.
- Pas de calcul automatique selon l’âge ni de règles conditionnelles entre champs.
- Tests serveur et DOM effectués ; recette du véritable formulaire et de Stripe en mode test encore nécessaire avant production.
