<?php
namespace WaasKit\FluentBooking\Guests;

/** Guests without mandatory identities belong to the holder, not to invented contacts. */
final class Identity
{
    public static function attached(array $options): bool
    {
        return $options['enabled'] && ($options['name_mode']!=='required' || $options['email_mode']!=='required');
    }
    public static function rows($raw, array $options): array
    {
        if(!is_string($raw) || strlen($raw)>30000) {throw new \InvalidArgumentException('Informations invités invalides.');}
        $rows=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($rows) || !array_is_list($rows) || count($rows)>999) {throw new \InvalidArgumentException('Liste des invités invalide.');}
        foreach($rows as &$row) {
            if(!is_array($row)) {throw new \InvalidArgumentException('Invité invalide.');}
            foreach(['name','email'] as $key) {
                $v=$row[$key]??'';
                if(!is_string($v) || strlen($v)>200 || strip_tags($v)!==$v) {throw new \InvalidArgumentException('Identité invalide.');}
                $v=$options[$key.'_mode']==='hidden'?'':trim($v);
                if($options[$key.'_mode']==='required' && $v==='') {throw new \InvalidArgumentException('Complétez le '.$key.' de chaque invité.');}
                if($key==='email' && $v!=='' && !is_email($v)) {throw new \InvalidArgumentException('Courriel invité invalide.');}
                $row[$key]=$v;
            }
        }
        return $rows;
    }
}
