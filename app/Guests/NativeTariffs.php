<?php
namespace WaasKit\FluentBooking\Guests;

/** Native payment rows become exclusive choices, never an additive basket. */
final class NativeTariffs
{
    public static function catalogue($event): array
    {
        if ($event->isMultiDurationEnabled()) {
            throw new \InvalidArgumentException('Choisissez une durée unique pour les tarifs par personne.');
        }
        if (!$event->isPaymentEnabled()) { return []; }
        $result=[];
        foreach ($event->getPaymentItems() as $item) {
            $title=trim((string)($item['title']??''));
            $value=$item['value']??null;
            if ($title==='' || !is_numeric($value) || (float)$value<0 || (float)$value>1000000) {
                throw new \InvalidArgumentException('Vérifiez les tarifs dans les paiements FluentBooking.');
            }
            $cents=(int)round((float)$value*100);
            $id=substr(hash('sha256',$title.'|'.$cents),0,24);
            if (isset($result[$id])) { throw new \InvalidArgumentException('Deux tarifs FluentBooking sont identiques.'); }
            $result[$id]=['id'=>$id,'title'=>$title,'cents'=>$cents];
        }
        if (!$result) { throw new \InvalidArgumentException('Ajoutez au moins un tarif FluentBooking.'); }
        return array_values($result);
    }

    public static function quote(array $catalogue, $holder, array $guests): array
    {
        $lookup=array_column($catalogue,null,'id');
        $people=[];$total=0;
        foreach (array_merge([$holder],array_map(static fn($guest)=>is_array($guest)?($guest['tariff']??null):null,$guests)) as $id) {
            if (!is_string($id) || !isset($lookup[$id])) {
                throw new \InvalidArgumentException('Choisissez un tarif valide pour chaque personne. Si les tarifs ont changé, rechargez la page.');
            }
            $people[]=$lookup[$id];$total+=$lookup[$id]['cents'];
        }
        if ($total>100000000) { throw new \InvalidArgumentException('Le montant total est trop élevé.'); }
        return ['people'=>$people,'total'=>$total];
    }
}
