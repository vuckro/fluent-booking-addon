<?php
namespace WaasKit\FluentBooking\Rules;
final class Registry
{
    private array $rules = [];
    public function add(Rule $rule): void
    {
        if (isset($this->rules[$rule->id()])) {
            throw new \LogicException('Identifiant de règle déjà enregistré.');
        }
        $this->rules[$rule->id()] = $rule;
    }
    public function validate(array $context, array $settings): ?string
    {
        foreach ($this->rules as $rule) {
            $error = $rule->validate($context, $settings);
            if ($error !== null) { return $error; }
        }
        return null;
    }
}
