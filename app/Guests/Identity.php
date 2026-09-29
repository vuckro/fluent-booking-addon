<?php
namespace WaasKit\FluentBooking\Guests;

/** Every custom guest belongs to the holder; identity never determines seat count. */
final class Identity
{
    public static function decode($raw): array
    {
        if(!is_string($raw) || strlen($raw)>30000) {throw new \InvalidArgumentException('Informations invités invalides.');}
        $rows=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($rows) || !array_is_list($rows)) {throw new \InvalidArgumentException('Liste des invités invalide.');}
        return $rows;
    }

    /** Normalize legacy BookingService arrays at the boundary; one representation thereafter. */
    public static function request(array &$data, array $input, array $options): array
    {
        $rows=self::decode($input['_fba_extras']??'[]');
        if(is_array($data['email'])) {
            $emails=array_values($data['email']); $names=array_values((array)$data['first_name']);
            if(count($emails)!==count($names) || !$emails || (int)($input['_fba_requested_count']??count($emails))>count($emails)) {throw new \InvalidArgumentException('Les invités sont incomplets ou dépassent la capacité.');}
            $data['email']=array_pop($emails);$data['first_name']=array_pop($names);$data['last_name']='';
            if(!$rows && !$options['fields']) {$rows=array_fill(0,count($emails),['fields'=>[]]);}
            if(count($rows)!==count($emails)) {throw new \InvalidArgumentException('Complétez les informations de chaque invité.');}
            foreach($emails as $i=>$email) {
                if(isset($rows[$i]['email']) && strcasecmp($rows[$i]['email'],$email)!==0) {throw new \InvalidArgumentException('Les informations des invités ont changé.');}
                $rows[$i]['name']=$names[$i];$rows[$i]['email']=$email;
            }
        }
        return self::clean($rows,$options);
    }
    public static function rows($raw, array $options): array
    {
        return self::clean(self::decode($raw),$options);
    }
    private static function clean(array $rows,array $options): array
    {
        if(!is_array($rows) || !array_is_list($rows) || count($rows)>999) {throw new \InvalidArgumentException('Liste des invités invalide.');}
        $split = !empty($options['split_name']);
        foreach($rows as &$row) {
            if(!is_array($row)) {throw new \InvalidArgumentException('Invité invalide.');}
            if ($split) {
                foreach(['first_name'=>'prénom','last_name'=>'nom'] as $key=>$label) {
                    $v=$row[$key]??'';
                    if(!is_string($v) || strlen($v)>200 || strip_tags($v)!==$v) {throw new \InvalidArgumentException('Identité invalide.');}
                    $v=$options['name_mode']==='hidden'?'':trim($v);
                    if($options['name_mode']==='required' && $v==='') {throw new \InvalidArgumentException('Complétez le '.$label.' de chaque invité.');}
                    $row[$key]=$v;
                }
                $row['name']=trim(($row['first_name']??'').' '.($row['last_name']??''));
            } else {
                $v=$row['name']??'';
                if(!is_string($v) || strlen($v)>200 || strip_tags($v)!==$v) {throw new \InvalidArgumentException('Identité invalide.');}
                $v=$options['name_mode']==='hidden'?'':trim($v);
                if($options['name_mode']==='required' && $v==='') {throw new \InvalidArgumentException('Complétez le name de chaque invité.');}
                $row['name']=$v;$row['first_name']='';$row['last_name']='';
            }
            $email=$row['email']??'';
            if(!is_string($email) || strlen($email)>200 || strip_tags($email)!==$email) {throw new \InvalidArgumentException('Identité invalide.');}
            $email=$options['email_mode']==='hidden'?'':trim($email);
            if($options['email_mode']==='required' && $email==='') {throw new \InvalidArgumentException('Complétez le email de chaque invité.');}
            if($email!=='' && !is_email($email)) {throw new \InvalidArgumentException('Courriel invité invalide.');}
            $row['email']=$email;
        }
        return $rows;
    }
}
