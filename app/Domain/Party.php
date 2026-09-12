<?php
namespace WaasKit\FluentBooking\Domain;

/** Validates the entire party, including the booking holder if they attend. */
final class Party
{
    public static function evaluate(array $rows, array $profile, \DateTimeImmutable $at): array
    {
        $p = BookingProfile::validate($profile);
        if (!array_is_list($rows) || count($rows) < $p['min'] || count($rows) > $p['max']) {
            throw new \InvalidArgumentException(sprintf('Choisissez entre %d et %d participants.', $p['min'], $p['max']));
        }
        $types = array_column($p['types'], null, 'id'); $participants = []; $counts = []; $lines = []; $units = 0; $subtotal = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || array_diff_key($row, array_flip(['type','name','email','birth_date','fields'])) || !is_string($row['type'] ?? null) || !isset($types[$row['type']])) { throw new \InvalidArgumentException('Type de participant invalide.'); }
            $type = $types[$row['type']];
            $name = self::text($row['name'] ?? '', 160);
            if ($p['names'] && $name === '') { throw new \InvalidArgumentException('Indiquez le nom de chaque participant.'); }
            $email = $p['email'] === 'hidden' ? '' : self::text($row['email'] ?? '', 254);
            if (($p['email'] === 'required' && $email === '') || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) { throw new \InvalidArgumentException('Adresse e-mail du participant invalide.'); }
            $birth = self::text($row['birth_date'] ?? '', 10);
            if ($birth !== '' || $type['min_age'] > 0 || $type['max_age'] < 120) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birth, $at->getTimezone());
                if (!$date || $date->format('Y-m-d') !== $birth || $date > $at) { throw new \InvalidArgumentException('Date de naissance invalide.'); }
                $age = $date->diff($at)->y;
                if ($age < $type['min_age'] || $age > $type['max_age']) { throw new \InvalidArgumentException('Âge incompatible avec le type : ' . $type['label']); }
            }
            $input = $row['fields'] ?? []; $fields = [];
            if (!is_array($input) || array_diff_key($input, array_column($type['fields'], null, 'id'))) { throw new \InvalidArgumentException('Champ participant inconnu.'); }
            foreach ($type['fields'] as $field) {
                $fields[$field['id']] = self::text($input[$field['id']] ?? '', 500);
                if ($field['required'] && $fields[$field['id']] === '') { throw new \InvalidArgumentException('Champ requis : ' . $field['label']); }
            }
            $participants[] = ['type'=>$type['id'], 'name'=>$name, 'email'=>$email, 'birth_date'=>$birth, 'fields'=>$fields];
            $counts[$type['id']] = ($counts[$type['id']] ?? 0) + 1;
            $units += $type['units'];
        }
        foreach ($p['types'] as $type) {
            $quantity = $counts[$type['id']] ?? 0;
            if (!$quantity) { continue; }
            if ($type['requires'] !== '' && empty($counts[$type['requires']])) { throw new \InvalidArgumentException('Un accompagnateur est requis : ' . $types[$type['requires']]['label']); }
            $total = $quantity * $type['price']; $subtotal += $total;
            $lines[] = ['type'=>$type['id'], 'label'=>$type['label'], 'quantity'=>$quantity, 'unit_price'=>$type['price'], 'total'=>$total];
        }
        return ['schema'=>1, 'participants'=>$participants, 'count'=>count($participants), 'units'=>$units, 'lines'=>$lines, 'subtotal'=>$subtotal];
    }
    private static function text($value, int $max): string
    {
        if (!is_string($value) || strlen($value) > $max || strip_tags($value) !== $value || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) { throw new \InvalidArgumentException('Texte invalide.'); }
        return trim($value);
    }
}
