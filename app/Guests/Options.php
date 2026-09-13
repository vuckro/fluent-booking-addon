<?php
namespace WaasKit\FluentBooking\Guests;

/** Event-only settings: no second hierarchy for the guest form. */
final class Options
{
    public static function defaults(): array
    {
        return ['enabled'=>false, 'allow_nonparticipating'=>false, 'customize_guests'=>null, 'native_tariffs'=>true, 'per_person_price'=>true, 'fields'=>[], 'name_mode'=>'required', 'email_mode'=>'required'];
    }
    public static function validate(array $input): array
    {
        if (array_diff(array_keys($input), array_keys(self::defaults()))) { throw new \InvalidArgumentException('Option invités inconnue.'); }
        if ($input && !array_key_exists('native_tariffs',$input)) {$input['native_tariffs']=false;}
        $value = array_replace(self::defaults(), $input);
        // Existing configurations customized identity and pricing with one switch.
        if ($value['customize_guests'] === null) {$value['customize_guests']=$value['enabled'];}
        foreach (['enabled','allow_nonparticipating','customize_guests','native_tariffs','per_person_price'] as $key) {
            if (!is_bool($value[$key])) { throw new \InvalidArgumentException('Option invités invalide.'); }
        }
        if ($value['allow_nonparticipating'] && $value['enabled'] && !$value['native_tariffs']) {throw new \InvalidArgumentException('La réservation pour autrui nécessite les tarifs natifs par personne, ou le calcul natif sans personnalisation du prix.');}
        if (!is_array($value['fields']) || !array_is_list($value['fields']) || count($value['fields']) > 8) { throw new \InvalidArgumentException('Maximum : 8 champs par invité.'); }
        foreach (['name_mode','email_mode'] as $key) { if (!in_array($value[$key], ['required','optional','hidden'],true)) {throw new \InvalidArgumentException('Affichage de l’identité invalide.');} }
        $ids=[]; $replacements=0;
        foreach ($value['fields'] as $field) {
            if (!is_array($field) || array_diff(array_keys($field), ['id','label','type','required','choices','pricing','prices','min','max'])) { throw new \InvalidArgumentException('Champ invité invalide.'); }
            $id=$field['id']??'';
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_]{0,31}$/D',$id) || in_array($id,$ids,true)) { throw new \InvalidArgumentException('Identifiant de champ invalide ou dupliqué.'); }
            $ids[]=$id;
            if (!is_string($field['label']??null) || trim($field['label'])==='' || strlen($field['label'])>160 || strip_tags($field['label'])!==$field['label']) { throw new \InvalidArgumentException('Libellé de champ invalide.'); }
            if (!in_array($field['type']??'', ['text','number','select','radio','checkbox'],true) || !is_bool($field['required']??null)) { throw new \InvalidArgumentException('Type de champ invalide.'); }
            foreach (['min','max'] as $bound) {
                $number=$field[$bound]??null;
                if ($number!==null && (!is_string($number) || !preg_match('/^-?[0-9]{1,9}(\.[0-9]{1,4})?$/D',$number))) {throw new \InvalidArgumentException('Borne numérique invalide : '.$field['label']);}
            }
            if (isset($field['min'],$field['max']) && (float)$field['min']>(float)$field['max']) {throw new \InvalidArgumentException('Le minimum doit être inférieur ou égal au maximum : '.$field['label']);}
            $choices=$field['choices']??[];
            if (!is_array($choices) || !array_is_list($choices) || count($choices)>20 || (in_array($field['type'],['select','radio'],true) && !$choices)) { throw new \InvalidArgumentException('Ajoutez les choix de la liste.'); }
            foreach ($choices as $choice) { if(!is_string($choice) || trim($choice)==='' || strlen($choice)>100 || strip_tags($choice)!==$choice) {throw new \InvalidArgumentException('Choix invalide.');} }
            $pricing=$field['pricing']??'none';
            if(!in_array($pricing,['none','add','replace'],true) || ($pricing!=='none' && !in_array($field['type'],['select','radio','checkbox'],true))) {throw new \InvalidArgumentException('Tarification du choix invalide.');}
            if($value['native_tariffs'] && $pricing!=='none') {throw new \InvalidArgumentException('Les tarifs par personne viennent de FluentBooking. Retirez les prix des champs supplémentaires pour éviter un double calcul.');}
            if($pricing==='replace' && ++$replacements>1) {throw new \InvalidArgumentException('Un seul choix peut remplacer le tarif de l’invité ; les autres peuvent ajouter un supplément.');}
            $prices=$field['prices']??[];
            if(!is_array($prices) || !array_is_list($prices) || ($pricing!=='none' && count($prices)!==($field['type']==='checkbox'?1:count($choices)))) {throw new \InvalidArgumentException('Indiquez un prix pour chaque choix.');}
            foreach($prices as $price) {if(!is_int($price) || $price<0 || $price>100000000) {throw new \InvalidArgumentException('Prix invalide.');}}
            if(count(array_unique($choices))!==count($choices)) {throw new \InvalidArgumentException('Choix dupliqué.');}
        }
        return $value;
    }

    /** Runtime settings: independent switches, saved values remain intact. */
    public static function effective(array $value): array
    {
        $value=self::validate($value);
        $value['pricing_enabled']=$value['enabled'];
        $value['native_tariffs']=$value['native_tariffs'] && $value['enabled'];
        if (!$value['enabled']) {$value['per_person_price']=false;}
        if (!$value['customize_guests']) {
            $value['name_mode']='required';$value['email_mode']='required';$value['fields']=[];
        } elseif (!$value['enabled']) {
            foreach($value['fields'] as &$field) {$field['pricing']='none';$field['prices']=[];} unset($field);
        }
        $value['enabled']=$value['enabled'] || $value['customize_guests'] || $value['allow_nonparticipating'];
        return $value;
    }

    public static function answers(array $rows, array $guests, array $fields): array
    {
        if (count($rows)!==count($guests) || !array_is_list($rows)) { throw new \InvalidArgumentException('Complétez les informations de chaque invité.'); }
        $result=[];
        foreach (array_values($guests) as $i=>$guest) {
            $row=$rows[$i];
            if (!is_array($row) || strtolower((string)($row['email']??''))!==strtolower($guest['email'])) { throw new \InvalidArgumentException('Les informations des invités ont changé. Vérifiez le formulaire.'); }
            $answers=$row['fields']??[];
            if(!is_array($answers) || array_diff(array_keys($answers),array_column($fields,'id'))) {throw new \InvalidArgumentException('Réponse de champ inconnue.');}
            $clean=[];
            foreach($fields as $field) {
                $v=$answers[$field['id']]??'';
                if(!is_string($v) || strlen($v)>1000 || strip_tags($v)!==$v) {throw new \InvalidArgumentException('Réponse invalide : '.$field['label']);}
                $v=trim($v);
                if($field['required'] && $v==='') {throw new \InvalidArgumentException('Champ obligatoire : '.$field['label']);}
                if($v!=='' && in_array($field['type'],['select','radio'],true) && !in_array($v,$field['choices'],true)) {throw new \InvalidArgumentException('Choix invalide : '.$field['label']);}
                if($v!=='' && $field['type']==='number' && !preg_match('/^-?[0-9]{1,9}(\.[0-9]{1,4})?$/D',$v)) {throw new \InvalidArgumentException('Nombre attendu : '.$field['label']);}
                if($v!=='' && $field['type']==='number') {
                    if(isset($field['min']) && (float)$v<(float)$field['min']) {throw new \InvalidArgumentException('Minimum '.$field['min'].' : '.$field['label']);}
                    if(isset($field['max']) && (float)$v>(float)$field['max']) {throw new \InvalidArgumentException('Maximum '.$field['max'].' : '.$field['label']);}
                }
                if($field['type']==='checkbox' && !in_array($v,['','1'],true)) {throw new \InvalidArgumentException('Case invalide.');}
                $clean[$field['id']]=$v;
            }
            $result[]=['name'=>$guest['name'],'email'=>$guest['email'],'fields'=>$clean];
        }
        return $result;
    }
}
