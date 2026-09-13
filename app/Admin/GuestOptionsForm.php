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
        foreach(['enabled','per_person_price'] as $key) {$value[$key]=isset($raw[$key]) && $raw[$key]==='1';}
        foreach(['name_mode','email_mode'] as $key) {$value[$key]=$raw[$key]??'required';}
        $value['fields']=[];
        if(!is_array($raw['fields']??[])) {throw new \InvalidArgumentException('Liste de champs invalide.');}
        foreach($raw['fields']??[] as $field) {
            if(!is_array($field)) {throw new \InvalidArgumentException('Champ invalide.');}
            if(trim((string)($field['label']??''))==='') {continue;}
            $value['fields'][]=['id'=>sanitize_key($field['id']??''),'label'=>trim($field['label']), 'type'=>$field['type']??'', 'required'=>($field['required']??'')==='1',
                'pricing'=>$field['pricing']??'none', 'prices'=>self::prices($field['prices']??''),
                'choices'=>array_values(array_filter(array_map('trim',explode("\n",str_replace("\r",'', $field['choices']??''))),static fn($s)=>$s!==''))];
        }
        return Options::validate($value);
    }
    public static function render(array $options): void
    {
        echo '<fieldset class="fba-policy fba-guest-options"><legend>Réserver avec des invités</legend>';
        foreach(['enabled'=>['Personnaliser la réservation avec invités','Active les options ci-dessous pour cet événement de groupe.'],
            'per_person_price'=>['Multiplier le prix par le nombre de personnes','Coché : chaque personne part du tarif de base. Décoché : seul le réservant paie ce tarif. Les choix tarifaires des invités s’appliquent ensuite.']] as $key=>[$label,$help]) {
            if($key==='per_person_price') {echo '<div class="fba-guest-details"'.(!$options['enabled']?' hidden':'').'>'; }
            echo '<label class="fba-choice"><input type="checkbox" name="guest_options['.esc_attr($key).']" value="1"'.checked($options[$key],true,false).'><span><strong>'.esc_html($label).'</strong><span class="description">'.esc_html($help).'</span></span></label>';
        }
        foreach(['name_mode'=>'Nom de l’invité','email_mode'=>'Courriel de l’invité'] as $key=>$title) {
            echo '<label class="fba-identity-option"><span>'.esc_html($title).'</span><select name="guest_options['.$key.']">';
            foreach(['required'=>'Obligatoire','optional'=>'Facultatif','hidden'=>'Masqué'] as $mode=>$label) {echo '<option value="'.$mode.'"'.selected($options[$key],$mode,false).'>'.$label.'</option>';}
            echo '</select></label>';
        }
        echo '<p class="description">Chaque personne occupe une place, quel que soit son tarif. Les invités sont rattachés au réservant, qui reçoit les communications du groupe.</p>';
        echo '<section class="fba-field-section"><h3>Options et informations par invité</h3><p class="description">Ajoutez un choix de tarif, une option ou une information utile pour chaque invité.</p><div class="fba-guest-fields">';
        foreach($options['fields'] as $index=>$field) {self::row((string)$index,$field);}
        echo '</div><button type="button" class="button fba-add-field">Ajouter un champ</button><template id="fba-field-template">';
        self::row('__INDEX__',['id'=>'','label'=>'','type'=>'select','required'=>false,'choices'=>[]]);
        echo '</template><p class="description">Les réponses sont conservées avec la réservation. Le tarif du réservant reste inchangé. Pour chaque invité : tarif de base (si multiplication cochée), éventuellement remplacé par un choix, puis suppléments ajoutés. Un seul champ peut remplacer le tarif.</p></section></div></fieldset>';
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
        foreach(['select'=>'Liste déroulante','radio'=>'Boutons radio','checkbox'=>'Case à cocher','text'=>'Texte libre','number'=>'Nombre'] as $type=>$title){echo '<option value="'.$type.'"'.selected($type,$field['type'],false).'>'.$title.'</option>';}
        echo '</select></label><label class="fba-field-choices">Choix possibles, un par ligne<textarea name="'.esc_attr($prefix.'[choices]').'" placeholder="Adulte&#10;Enfant">'.esc_textarea(implode("\n",$field['choices'])).'</textarea></label>';
        echo '<div class="fba-field-pricing"><label>Effet sur le tarif de cet invité<select name="'.esc_attr($prefix.'[pricing]').'">';
        foreach(['none'=>'Aucun effet sur le prix','add'=>'Ajouter un supplément','replace'=>'Remplacer le tarif de cet invité'] as $mode=>$label) {echo '<option value="'.$mode.'"'.selected($field['pricing']??'none',$mode,false).'>'.$label.'</option>';}
        echo '</select></label><label class="fba-field-prices">Prix par choix, dans le même ordre (un montant par ligne)<textarea name="'.esc_attr($prefix.'[prices]').'" placeholder="40,00&#10;25,00">'.esc_textarea(implode("\n",array_map(static fn($c)=>number_format($c/100,2,'.',''),$field['prices']??[]))).'</textarea><span class="description">Dans la devise FluentBooking. Pour une case : un seul montant, appliqué uniquement si elle est cochée. 0 est un tarif valide.</span></label></div><label><input type="checkbox" name="'.esc_attr($prefix.'[required]').'" value="1"'.checked($field['required'],true,false).'> Réponse obligatoire</label><button type="button" class="button fba-remove-field">Supprimer ce champ</button></fieldset>';
    }
}
