# Invités : logique livrée et évolution à concevoir

## Livré en alpha.17

La limite par demande s’applique au total réservant + invités. Elle est transmise au formulaire public natif et contrôlée côté serveur. Le contrôle public intervient avant que FluentBooking réduise la liste aux places restantes ; une autre protection repère une liste tronquée dans BookingService. On refuse la demande au lieu d’enregistrer moins de personnes que demandé.

Les questions natives ne sont pas réenregistrées par l’extension. L’aide de l’événement donne un lien vers `question-settings`, indique l’état d’Invités supplémentaires et un lien vers `payment-settings`.

Sur un événement de groupe, FluentBooking crée déjà une réservation par personne, puis rattache les réservations. Son prix natif utilise cette quantité. Aucune option de débit additionnel n’est ajoutée : elle compterait les invités deux fois. Sur un événement individuel, l’aide explique que les invités ne constituent pas des places individuelles.

## Non livré : identité facultative et champs par invité

Le groupe natif transforme la liste en réservations indexées par e-mail. Un e-mail manquant fait disparaître le participant lors de sa préparation ; un e-mail dupliqué peut fusionner deux entrées. Les deux situations sont désormais refusées quand la limite est active.

Pour masquer le nom/l’e-mail ou les rendre facultatifs sans créer de faux e-mails, il faut un modèle de participants distinct du réservant. Il ne suffit pas d’ajouter une option CSS. Cette partie n’est pas implémentée dans l’alpha.17.

L’interface cible devrait rester limitée à trois groupes :

1. **Participants** : maximum par réservation et règles de consommation des places uniquement si le moteur distinct devient nécessaire.
2. **Informations des invités** : une ligne par champ, avec Nom / E-mail / Âge, et un choix Masqué / Facultatif / Obligatoire. Ajouter d’autres champs seulement sur besoin concret. Le réservant garde son identité et son e-mail natifs.
3. **Tarification** : tarif commun natif au départ ; règles différenciées seulement lorsque le parcours de commande correspondant est validé.

Avant de livrer ce modèle distinct : compter les personnes sans doubler les réservations natives, sécuriser les derniers sièges en concurrence, gérer annulation/report/réactivation, fixer prix et données au moment de la réservation, traiter les remboursements et les outils de confidentialité, puis valider le formulaire et un paiement de test. Les moteurs expérimentaux retirés ne sont pas réactivés.

## Limites de la preuve actuelle

Le parcours natif local, le rendu des données publiques et un refus HTTP sont vérifiés. Le clic réel dans le navigateur et un paiement Stripe ne le sont pas. Le comptage natif est conservé ; aucune garantie nouvelle de concurrence n’est annoncée.
