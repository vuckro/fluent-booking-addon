> Mise à jour alpha.10 : voir [le point d’étape des modules](implementation-modules.md). Le contenu ci-dessous décrit le socle antérieur ; les nouveaux modules et leurs limites sont détaillés dans ce document.

# Ajouter une règle

Créer une classe implémentant `WaasKit\FluentBooking\Rules\Rule` : `id()` renvoie un identifiant unique, `validate($context, $settings)` renvoie `null` ou un motif de refus. Le contexte fourni actuellement contient `participant_count`.

Enregistrer depuis un autre plugin, avant `plugins_loaded` priorité 30 :

```php
add_action('waaskit_fluent_booking/register_rules', static function ($registry) {
    $registry->add(new MyProject\Rules\MyRule());
});
```

Les règles ne sont évaluées que lorsque la configuration effective `enabled` est vraie. Elles n'accèdent pas directement à WordPress, FluentBooking ou à une passerelle de paiement. Elles doivent être déterministes, testables et sans effet de bord. Une exception entraîne un refus de la création, pas un passage silencieux.

Le schéma de configuration est volontairement fermé pour cette première version. Ajouter un nouveau champ implique de modifier et tester le schéma ; le registre de règles n'est pas encore un catalogue dynamique de champs. Les imports/export de profils et les extensions du schéma seront versionnés lorsqu'un besoin concret le justifiera.
