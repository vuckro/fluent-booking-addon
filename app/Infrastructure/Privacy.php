<?php
namespace WaasKit\FluentBooking\Infrastructure;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\Helper;
use WaasKit\FluentBooking\Integrations\FluentBooking\BookingModules;

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
        $rows=Booking::where('email',$email)->orderBy('id')->offset((max(1,$page)-1)*50)->limit(50)->get(); $data=[];
        foreach($rows as $booking) {
            $q=$booking->getMeta(BookingModules::META,[]); if(!$q) {continue;}
            $data[]=['group_id'=>'fba-participants','group_label'=>'Participants','item_id'=>'fba-booking-'.$booking->id,
                'data'=>[['name'=>'Participants','value'=>wp_json_encode($q['participants'],JSON_UNESCAPED_UNICODE)]]];
        }
        return ['data'=>$data,'done'=>count($rows)<50];
    }
    public function erase(string $email, int $page = 1): array
    {
        $rows=Booking::where('email',$email)->orderBy('id')->offset((max(1,$page)-1)*50)->limit(50)->get(); $removed=false;
        foreach($rows as $booking) {
            $q=$booking->getMeta(BookingModules::META,[]);if(!$q) {continue;}
            foreach($q['participants'] as &$person) {$person['name']='';$person['email']='';$person['birth_date']='';$person['fields']=[];}
            unset($person);
            Helper::updateBookingMeta($booking->id,BookingModules::META,$q);$removed=true;
        }
        return ['items_removed'=>$removed,'items_retained'=>false,'messages'=>[],'done'=>count($rows)<50];
    }
}
