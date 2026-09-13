# Invités — alpha.19

## Utilisation

Dans Modules, sélectionner l’événement de groupe, puis cocher **Personnaliser la réservation avec invités**. Les options apparaissent ; les décocher masque les réglages sans effacer les choix enregistrés. Enregistrer pour appliquer.

1. Activer aussi **Invités supplémentaires** dans les questions FluentBooking, via le lien proposé.
2. Choisir éventuellement un maximum par réservation : réservant compris. Cette limite reste indépendante de la capacité du créneau.
3. Garder **Décompter une place par personne** coché. Les réservations natives représentent les places, sans double stock. Décoché, le fonctionnement natif demeure ; les invités sans identité gardent obligatoirement un contrôle de capacité pour éviter la surréservation.
4. Choisir si le tarif de base s’applique à chaque personne ou une seule fois à la réservation.
5. Choisir séparément pour le nom et le courriel : **Obligatoire**, **Facultatif** ou **Masqué**.
6. Ajouter si nécessaire un champ : liste déroulante, boutons radio, case à cocher, texte ou nombre. Chaque champ possède un libellé et peut être obligatoire.

## Prix par invité

Les listes, radios et cases peuvent avoir trois comportements : aucun effet, **ajouter un supplément**, **remplacer le tarif de cet invité**. Pour une liste ou des radios, saisir les choix puis leurs montants dans le même ordre, un par ligne. Pour une case, saisir un seul montant, appliqué si elle est cochée. Les montants utilisent la devise FluentBooking.

Le réservant paie toujours le tarif de base. Pour chaque invité :

- Tarif de départ = tarif de base si la multiplication est cochée, sinon 0.
- Un choix peut remplacer ce tarif de départ, y compris par 0.
- Tous les suppléments sélectionnés s’ajoutent ensuite, une seule fois.

Un seul champ peut remplacer le tarif afin d’éviter deux remplacements contradictoires. Les suppléments ne sont jamais multipliés par la taille du groupe. Les champs texte et nombre collectent des informations et ne déclenchent pas de tarif automatique.

**Exemple :** base 50 ; choix Adulte = 50, Enfant = 25, supplément = 10. Réservant + adulte avec supplément + enfant = **50 + 50 + 10 + 25 = 135**. Les places consommées restent **3**.

## Identités et places

Si nom et courriel sont obligatoires, le parcours natif des invités est conservé. Si l’un devient facultatif ou masqué, le formulaire utilise des invités rattachés au réservant : aucun faux e-mail, aucun contact automatique, aucune notification individuelle. Le courriel facultatif est validé lorsqu’il est renseigné et conservé avec les réponses. Le réservant reçoit les communications du groupe.

Chaque invité rattaché dispose d’une ligne de place native liée au réservant. Annuler ou supprimer le réservant libère aussi ces places. Une catégorie ou un prix nul ne change jamais le nombre de places. Le contrôle serveur utilise un verrou par événement et début de créneau. Il ne constitue pas une transaction globale couvrant toutes les intégrations externes et les paiements.

Les réponses, les tarifs en centimes et le total sont validés côté serveur et conservés avec la réservation. Un changement ultérieur de tarif ne recalcule pas une ancienne réservation. Les commandes et paiements utilisent le montant conservé, jamais un montant fourni par le navigateur. Les outils de confidentialité couvrent les copies rattachées et les courriels facultatifs.

## Limites avant production

- FluentBooking 2.4.x, événement de groupe et créneau unique ; pas de séries récurrentes.
- Choix payants : un seul tarif de base natif et paiement activé, devise à deux décimales, Stripe ou paiement hors ligne. Coupons, WooCommerce, tarifs alternatifs et autres passerelles exclus de ce mode.
- Les reports et réactivations sont bloqués tant qu’un parcours adapté n’est pas disponible. Les annulations restent possibles.
- La saisie de l’âge ne modifie pas automatiquement les tarifs. Pas de règles conditionnelles entre champs ni de réduction de places selon une catégorie.
- Tests serveur et DOM effectués. Une recette du vrai formulaire Svelte, des intégrations tierces et de Stripe en mode test reste nécessaire avant production. Aucun paiement réel n’a été effectué.
