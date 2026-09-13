<?php
namespace WaasKit\FluentBooking\Infrastructure;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Integrations\FluentBooking\RetiredProfiles;

final class Privacy
{
    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
            $exporters['fba-participants']=['exporter_friendly_name'=>'Fluent Booking Addon', 'callback'=>[$this,'export']]; return $exporters;
        });
        add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
            $erasers['fba-participants']=['eraser_friendly_name'=>'Fluent Booking Addon', 'callback'=>[$this,'erase']]; return $erasers;
        });
    }
    public function export(string $email, int $page = 1): array
    {
        $rows=$this->rows($page); $data=[];
        foreach($rows as $booking) {
            $owner=$this->owned($booking,$email);
            $q=$owner?$booking->getMeta(RetiredProfiles::META,[]):[];
            $new=$booking->getMeta(\WaasKit\FluentBooking\Guests\BookingAdapter::META,[]);
            $guests=array_values(array_filter($new['guests']??[],static fn($guest)=>$owner || strcasecmp($guest['email']??'',$email)===0));
            if($guests) {$q['participants']=array_merge($q['participants']??[],$guests);}
            if(!$q) {continue;}
            $data[]=['group_id'=>'fba-participants','group_label'=>'Participants','item_id'=>'fba-booking-'.$booking->id,
                'data'=>[['name'=>'Participants','value'=>wp_json_encode($q['participants'],JSON_UNESCAPED_UNICODE)]]];
        }
        return ['data'=>$data,'done'=>count($rows)<50];
    }
    public function erase(string $email, int $page = 1): array
    {
        $rows=$this->rows($page); $removed=false;
        foreach($rows as $booking) {
            $new=$booking->getMeta(\WaasKit\FluentBooking\Guests\BookingAdapter::META,[]);
            if(!empty($new['guests'])) {
                $changed=false;$owner=$this->owned($booking,$email);
                foreach($new['guests'] as &$guest) {if($owner || strcasecmp($guest['email']??'',$email)===0) {$guest['name']='';$guest['email']='';$guest['fields']=[];$changed=true;}} unset($guest);
                if($changed) {Helper::updateBookingMeta($booking->id,\WaasKit\FluentBooking\Guests\BookingAdapter::META,$new);$removed=true;}
            }
            $q=$this->owned($booking,$email)?$booking->getMeta(RetiredProfiles::META,[]):[];if(!$q) {continue;}
            foreach($q['participants'] as &$person) {$person['name']='';$person['email']='';$person['birth_date']='';$person['fields']=[];}
            unset($person);
            Helper::updateBookingMeta($booking->id,RetiredProfiles::META,$q);$removed=true;
        }
        return ['items_removed'=>$removed,'items_retained'=>false,'messages'=>[],'done'=>count($rows)<50];
    }
    private function rows(int $page)
    {
        // Stable pagination: metadata keys remain after erasure, even when emails are removed.
        return Booking::whereHas('booking_meta',static function($query) {
            $query->whereIn('meta_key',[RetiredProfiles::META,\WaasKit\FluentBooking\Guests\BookingAdapter::META]);
        })->orderBy('id')->offset((max(1,$page)-1)*50)->limit(50)->get();
    }
    private function owned($booking,string $email): bool
    {
        if(strcasecmp((string)$booking->email,$email)===0) {return true;}
        if(!$booking->parent_id) {return false;}
        $parent=Booking::find($booking->parent_id);
        return $parent && strcasecmp((string)$parent->email,$email)===0;
    }
}
