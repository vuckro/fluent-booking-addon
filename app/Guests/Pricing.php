<?php
namespace WaasKit\FluentBooking\Guests;

/** Amounts are integer minor units; replacement is resolved before supplements. */
final class Pricing
{
    public static function quote(int $base, array $options, array $guests): array
    {
        $total=$base; $breakdown=[];
        foreach($guests as $guest) {
            $amount=$options['per_person_price']?$base:0;
            $supplements=0;
            foreach($options['fields'] as $field) {
                $mode=$field['pricing']??'none';
                $answer=$guest['fields'][$field['id']]??'';
                if($mode==='none' || $answer==='') {continue;}
                $index=$field['type']==='checkbox'?0:array_search($answer,$field['choices'],true);
                if($index===false || !isset($field['prices'][$index])) {throw new \InvalidArgumentException('Tarif du choix introuvable.');}
                if($mode==='replace') {$amount=$field['prices'][$index];} else {$supplements+=$field['prices'][$index];}
            }
            $amount+=$supplements; $total+=$amount; $breakdown[]=$amount;
        }
        if($total>99999999) {throw new \InvalidArgumentException('Le montant total dépasse le maximum autorisé.');}
        return ['total'=>$total,'guests'=>$breakdown];
    }
}
