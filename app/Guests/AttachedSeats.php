<?php
namespace WaasKit\FluentBooking\Guests;

use FluentBooking\App\Models\Booking;
use FluentBooking\App\Services\Helper;

/** Native seat records owned by the holder. No guest contacts or notification lifecycle. */
final class AttachedSeats
{
    public static function create($holder, array $snapshot): void
    {
        $created=[];
        try {
            foreach($snapshot['guests'] as $index=>$guest) {
                $data=[];
                foreach(['calendar_id','event_id','group_id','host_user_id','person_time_zone','start_time','end_time','slot_minutes','status','event_type','source','location_details'] as $key) {$data[$key]=$holder->$key;}
                $data+=['parent_id'=>$holder->id,'person_user_id'=>0,'first_name'=>$guest['name']?:'Invité '.($index+1),'last_name'=>'','email'=>'','payment_method'=>'','payment_status'=>''];
                $seat=Booking::create($data); $created[]=$seat;
                $seat->hosts()->attach([$holder->host_user_id=>['status'=>'confirmed']]);
                Helper::updateBookingMeta($seat->id,BookingAdapter::META,['seat'=>true,'attached_seat'=>true,'guests'=>[$guest],'fields'=>$snapshot['fields']]);
            }
        } catch(\Throwable $e) {
            foreach($created as $seat) {$seat->hosts()->detach();$seat->delete();}
            $holder->delete();
            throw $e;
        }
    }
    public static function remove($holder): void
    {
        foreach(Booking::where('parent_id',$holder->id)->get() as $seat) {
            if(!empty($seat->getMeta(BookingAdapter::META,[])['attached_seat'])) {$seat->hosts()->detach();$seat->delete();}
        }
    }
    public static function sync($holder): void
    {
        if(!$holder->isDirty('status')) {return;}
        if(empty($holder->getMeta(BookingAdapter::META,[])['attached'])) {return;}
        // The holder owns these seats; children have no separate email or payment flow.
        Booking::where('parent_id',$holder->id)->update(['status'=>$holder->status]);
    }
}
