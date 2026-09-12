<?php
namespace WaasKit\FluentBooking\Admin;
use WaasKit\FluentBooking\Guests\Options;

final class GuestOptionsForm
{
    public static function parse(array $post): array
    {
        $raw=$post['guest_options']??[];
        if(!is_array($raw)) {throw new \InvalidArgumentException('Réglages invités invalides.');}
        $value=[];
        foreach(['enabled','per_person_seats','per_person_price'] as $key) {$value[$key]=isset($raw[$key]) && $raw[$key]==='1';}
        $value['fields']=[];
        if(!is_array($raw['fields']??[])) {throw new \InvalidArgumentException('Liste de champs invalide.');}
        foreach($raw['fields']??[] as $field) {
            if(!is_array($field)) {throw new \InvalidArgumentException('Champ invalide.');}
            if(trim((string)($field['label']??''))==='') {continue;}
            $value['fields'][]=['id'=>sanitize_key($field['id']??''),'label'=>trim($field['label']), 'type'=>$field['type']??'', 'required'=>($field['required']??'')==='1',
                'choices'=>array_values(array_filter(array_map('trim',explode("\n",str_replace("\r",'', $field['choices']??''))),static fn($s)=>$s!==''))];
        }
        return Options::validate($value);
    }
    public static function render(array $options): void
    {
        echo '<fieldset class="fba-policy fba-guest-options"><legend>Réserver avec des invités</legend>';
        foreach(['enabled'=>['Personnaliser la réservation avec invités','Active les options ci-dessous pour cet événement de groupe.'],
            'per_person_seats'=>['Décompter une place par personne','Vérifie que vous + 1 invité disposent de 2 places avant de réserver. Décoché : seul le contrôle natif FluentBooking est utilisé.'],
            'per_person_price'=>['Multiplier le prix par le nombre de personnes','Coché : 100 × 3 personnes = 300. Décoché : un seul tarif pour la réservation.']] as $key=>[$label,$help]) {
            echo '<label class="fba-choice"><input type="checkbox" name="guest_options['.esc_attr($key).']" value="1"'.checked($options[$key],true,false).'><span><strong>'.esc_html($label).'</strong><span class="description">'.esc_html($help).'</span></span></label>';
        }
        echo '<h3>Questions pour chaque invité</h3><p class="description">Le nom et l’e-mail natifs restent obligatoires. Ajoutez uniquement les informations utiles : une catégorie, un âge ou un texte libre.</p><div class="fba-guest-fields">';
        foreach($options['fields'] as $index=>$field) {self::row((string)$index,$field);}
        echo '</div><button type="button" class="button fba-add-field">Ajouter une question</button><template id="fba-field-template">';
        self::row('__INDEX__',['id'=>'','label'=>'','type'=>'select','required'=>false,'choices'=>[]]);
        echo '</template><p class="description">Les réponses sont conservées avec la réservation. Elles ne changent pas encore le prix : les règles par catégorie pourront être ajoutées ensuite.</p></fieldset>';
    }
    private static function row(string $index,array $field): void
    {
        $prefix='guest_options[fields]['.$index.']';
        echo '<fieldset class="fba-extra-field"><legend>Question invité</legend><input type="hidden" data-field-id name="'.esc_attr($prefix.'[id]').'" value="'.esc_attr($field['id']).'">';
        echo '<label>Question<input type="text" maxlength="160" name="'.esc_attr($prefix.'[label]').'" value="'.esc_attr($field['label']).'" placeholder="Ex. Catégorie du participant"></label><label>Réponse<select name="'.esc_attr($prefix.'[type]').'">';
        foreach(['select'=>'Liste de choix','text'=>'Texte libre','number'=>'Nombre'] as $type=>$title){echo '<option value="'.$type.'"'.selected($type,$field['type'],false).'>'.$title.'</option>';}
        echo '</select></label><label class="fba-field-choices">Choix possibles, un par ligne<textarea name="'.esc_attr($prefix.'[choices]').'" placeholder="Adulte&#10;Enfant">'.esc_textarea(implode("\n",$field['choices'])).'</textarea></label><label><input type="checkbox" name="'.esc_attr($prefix.'[required]').'" value="1"'.checked($field['required'],true,false).'> Réponse obligatoire</label><button type="button" class="button fba-remove-field">Supprimer cette question</button></fieldset>';
    }
}
