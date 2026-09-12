# Comprendre les réglages

1. **Réglages de** : choisir tous les calendriers, un calendrier ou un événement.
2. **Appliquer cette limite** : oui ajoute le contrôle ; non laisse uniquement les règles FluentBooking ; utiliser le niveau supérieur reprend son choix.
3. **Personnes maximum par demande** : choisir un nombre ici ou reprendre celui du niveau supérieur. Le total comprend le réservant et ses invités. `0` n’ajoute aucune limite.
4. **Enregistrer** : le résumé affiche alors le résultat réellement enregistré.

Exemple : le global active une limite de 6. Un calendrier peut choisir 4 tout en reprenant l’activation globale. Un événement peut désactiver uniquement cette limite supplémentaire ; les restrictions natives restent applicables.

L’héritage suit : valeurs par défaut → global → calendrier → événement. Une valeur explicite `0` ou « Non » remplace celle du niveau supérieur. Changer le global modifie les contextes qui en héritent, pas ceux qui ont une valeur personnalisée.

Le lien à côté d’« Afficher les réglages » ouvre la page publique du calendrier ou de l’événement affiché dans un nouvel onglet. Si celle-ci est désactivée dans FluentBooking, aucun faux lien n’est proposé.

Les diagnostics sont destinés aux administrateurs. L’inventaire des anciennes options est informatif et n’effectue aucune conversion.
