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
        foreach(['enabled','allow_nonparticipating','customize_guests','native_tariffs','per_person_price'] as $key) {$value[$key]=isset($raw[$key]) && $raw[$key]==='1';}
        foreach(['name_mode','email_mode'] as $key) {$value[$key]=$raw[$key]??'required';}
        $value['fields']=[];
        if(!is_array($raw['fields']??[])) {throw new \InvalidArgumentException('Liste de champs invalide.');}
        foreach($raw['fields']??[] as $field) {
            if(!is_array($field)) {throw new \InvalidArgumentException('Champ invalide.');}
            if(trim((string)($field['label']??''))==='') {continue;}
            $value['fields'][]=['id'=>sanitize_key($field['id']??''),'label'=>trim($field['label']), 'type'=>$field['type']??'', 'required'=>($field['required']??'')==='1',
                'min'=>($field['type']??'')==='number' && trim($field['min']??'')!=='' ? str_replace(',','.',trim($field['min'])) : null, 'max'=>($field['type']??'')==='number' && trim($field['max']??'')!=='' ? str_replace(',','.',trim($field['max'])) : null,
                'pricing'=>$field['pricing']??'none', 'prices'=>self::prices($field['prices']??''),
                'choices'=>array_values(array_filter(array_map('trim',explode("\n",str_replace("\r",'', $field['choices']??''))),static fn($s)=>$s!==''))];
        }
        return Options::validate($value);
    }
    public static function render(array $options): void
    {
        echo '<fieldset class="fba-policy fba-guest-options"><legend>Réserver avec des invités</legend>';
        echo '<label class="fba-choice"><input type="checkbox" name="guest_options[enabled]" value="1"'.checked($options['enabled'],true,false).'><span><strong>Un tarif par personne</strong><span class="description">Chaque personne choisit son tarif FluentBooking. Décoché : le calcul du prix reste natif.</span></span></label>';
        if ($options['native_tariffs']) {
            echo '<input type="hidden" name="guest_options[native_tariffs]" value="1">';
        } else {
            echo '<label class="fba-identity-option"><span>Tarification</span><select name="guest_options[native_tariffs]"><option value="1">Un tarif par personne</option><option value="0" selected>Ancien calcul personnalisé</option></select></label>';
            echo '<label class="fba-choice fba-legacy-pricing"><input type="checkbox" name="guest_options[per_person_price]" value="1"'.checked($options['per_person_price'],true,false).'><span>Multiplier le tarif de base par le nombre de personnes</span></label>';
        }
        echo '<label class="fba-choice"><input type="checkbox" name="guest_options[allow_nonparticipating]" value="1"'.checked($options['allow_nonparticipating'],true,false).'><span><strong>Autoriser la réservation pour d’autres personnes</strong><span class="description">Le réservant peut choisir de ne pas participer. Il reste le contact qui paie et reçoit les messages ; seuls les participants occupent des places.</span></span></label>';
        echo '<label class="fba-choice"><input type="checkbox" name="guest_options[customize_guests]" value="1"'.checked($options['customize_guests'],true,false).'><span><strong>Personnaliser les informations des invités</strong><span class="description">Choisissez les informations à demander à chaque invité, indépendamment du tarif.</span></span></label>';
        echo '<div class="fba-guest-details"'.(!$options['customize_guests']?' hidden':'').'>';
        foreach(['name_mode'=>'Nom de l’invité','email_mode'=>'Courriel de l’invité'] as $key=>$title) {
            echo '<label class="fba-identity-option"><span>'.esc_html($title).'</span><select name="guest_options['.$key.']">';
            foreach(['required'=>'Obligatoire','optional'=>'Facultatif','hidden'=>'Masqué'] as $mode=>$label) {echo '<option value="'.$mode.'"'.selected($options[$key],$mode,false).'>'.$label.'</option>';}
            echo '</select></label>';
        }
        echo '<p class="description">Chaque participant occupe une place, quel que soit son tarif. Les invités sont rattachés au réservant, qui reçoit les communications du groupe.</p>';
        echo '<section class="fba-field-section"><h3>Options et informations par invité</h3><p class="description">Ajoutez uniquement les informations complémentaires utiles : âge, préférence ou remarque. Les tarifs se configurent dans les paiements FluentBooking.</p><div class="fba-guest-fields">';
        foreach($options['fields'] as $index=>$field) {self::row((string)$index,$field);}
        echo '</div><button type="button" class="button fba-add-field">Ajouter un champ</button><template id="fba-field-template">';
        self::row('__INDEX__',['id'=>'','label'=>'','type'=>'select','required'=>false,'choices'=>[]]);
        echo '</template><p class="description">Les réponses sont conservées avec la réservation. En mode « Un tarif par personne », ces champs ne modifient pas le prix.</p></section></div></fieldset>';
    }
    private static function prices($text): array
    {
        if(!is_string($text)) {throw new \InvalidArgumentException('Prix invalides.');}
        $prices=[];
        foreach(preg_split('/\R/',trim($text)) as $line) {
            $line=str_replace(',','.',trim($line)); if($line==='') {continue;}
            if(!preg_match('/^[0-9]{1,7}(\.[0-9]{1,2})?$/D',$line)) {throw new \InvalidArgumentException('Utilisez des montants positifs avec deux décimales maximum.');}
            $prices[]=(int)round((float)$line*100);
        }
        return $prices;
    }
    private static function row(string $index,array $field): void
    {
        $prefix='guest_options[fields]['.$index.']';
        echo '<fieldset class="fba-extra-field"><legend>Champ invité</legend><input type="hidden" data-field-id name="'.esc_attr($prefix.'[id]').'" value="'.esc_attr($field['id']).'">';
        echo '<label>Libellé<input type="text" maxlength="160" name="'.esc_attr($prefix.'[label]').'" value="'.esc_attr($field['label']).'" placeholder="Ex. Catégorie du participant"></label><label>Affichage<select name="'.esc_attr($prefix.'[type]').'">';
        // Preserve existing saved fields without offering this type for new fields.
        if ($field['type']==='checkbox') {echo '<option value="checkbox" selected hidden>Champ existant</option>';}
        foreach(['select'=>'Liste déroulante','radio'=>'Boutons radio','text'=>'Texte libre','number'=>'Nombre'] as $type=>$title){echo '<option value="'.$type.'"'.selected($type,$field['type'],false).'>'.$title.'</option>';}
        echo '</select></label><label class="fba-field-choices">Choix possibles, un par ligne<textarea name="'.esc_attr($prefix.'[choices]').'" placeholder="Option 1&#10;Option 2">'.esc_textarea(implode("\n",$field['choices'])).'</textarea></label>';
        echo '<div class="fba-field-bounds"'.($field['type']!=='number'?' hidden':'').'>';
        foreach(['min'=>'Minimum','max'=>'Maximum'] as $key=>$title) {echo '<label>'.$title.'<input type="number" step="any" name="'.esc_attr($prefix.'['.$key.']').'" value="'.esc_attr($field[$key]??'').'" placeholder="Sans limite"></label>';}
        echo '</div>';
        echo '<div class="fba-field-pricing"><label>Effet sur le tarif de cet invité<select name="'.esc_attr($prefix.'[pricing]').'">';
        foreach(['none'=>'Aucun effet sur le prix','add'=>'Ajouter un supplément','replace'=>'Remplacer le tarif de cet invité'] as $mode=>$label) {echo '<option value="'.$mode.'"'.selected($field['pricing']??'none',$mode,false).'>'.$label.'</option>';}
        echo '</select></label><label class="fba-field-prices">Prix par choix, dans le même ordre (un montant par ligne)<textarea name="'.esc_attr($prefix.'[prices]').'" placeholder="40,00&#10;25,00">'.esc_textarea(implode("\n",array_map(static fn($c)=>number_format($c/100,2,'.',''),$field['prices']??[]))).'</textarea><span class="description">Dans la devise FluentBooking. Pour une case : un seul montant, appliqué uniquement si elle est cochée. 0 est un tarif valide.</span></label></div><label><input type="checkbox" name="'.esc_attr($prefix.'[required]').'" value="1"'.checked($field['required'],true,false).'> Réponse obligatoire</label><button type="button" class="button fba-remove-field">Supprimer ce champ</button></fieldset>';
    }
}
