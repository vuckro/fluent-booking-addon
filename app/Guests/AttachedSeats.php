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
            // The main native booking represents the first participant's seat when
            // its contact does not attend. Keep contact identity for native messages.
            $skipFirst=($snapshot['holder_participates']??true)===false;
            foreach($snapshot['guests'] as $index=>$guest) {
                if ($skipFirst && $index===0) {continue;}
                $data=[];
                foreach(['calendar_id','event_id','group_id','host_user_id','person_time_zone','start_time','end_time','slot_minutes','status','event_type','source','location_details'] as $key) {$data[$key]=$holder->$key;}
                $firstName=!empty($guest['first_name'])?$guest['first_name']:($guest['name']?:'Participant '.($index+1));
                $lastName=$guest['last_name']??'';
                $data+=['parent_id'=>$holder->id,'person_user_id'=>0,'first_name'=>$firstName,'last_name'=>$lastName,'email'=>'','payment_method'=>'','payment_status'=>''];
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
        $statusDirty=$holder->isDirty('status');
        $scheduleKeys=array_values(array_filter(['start_time','end_time','person_time_zone','group_id','host_user_id','slot_minutes'],static fn(string $key)=>$holder->isDirty($key)));
        if(!$statusDirty && !$scheduleKeys) {return;}
        $cancelled=$statusDirty && in_array($holder->status,['cancelled','rejected'],true);
        if ($cancelled && empty($holder->parent_id)) {
            self::syncCancelledOrder($holder);
        }
        if(empty($holder->getMeta(BookingAdapter::META,[])['attached'])) {return;}
        // The holder owns these seats; children have no separate email or payment flow.
        $update=[];
        if ($statusDirty) {
            $update['status']=$holder->status;
            if ($cancelled) {$update['payment_status']='';}
        }
        foreach ($scheduleKeys as $key) {$update[$key]=$holder->$key;}
        $update['slot_minutes'] = $holder->slot_minutes;
        Booking::where('parent_id',$holder->id)->update($update);
        if (!$scheduleKeys) {return;}
        $hostChanged=in_array('host_user_id',$scheduleKeys,true);
        $slotChanged=in_array('start_time',$scheduleKeys,true) || in_array('group_id',$scheduleKeys,true);
        if (!$hostChanged && !$slotChanged) {return;}
        foreach (Booking::where('parent_id',$holder->id)->get() as $seat) {
            if (empty($seat->getMeta(BookingAdapter::META,[])['attached_seat'])) {continue;}
            if ($hostChanged) {$seat->hosts()->sync([$holder->host_user_id=>['status'=>'confirmed']]);}
            if ($slotChanged) {
                foreach (['google','outlook','apple_calendar','next_cloud_calendar'] as $driver) {
                    $metaKey='__'.$driver.'_calendar_event';
                    if ($seat->getMeta($metaKey,[])) {$seat->updateMeta($metaKey,[]);}
                }
            }
        }
    }

    private static function syncCancelledOrder($holder): void
    {
        $order=$holder->payment_order ?? null;
        if (!$order) {return;}
        if ($order->status==='draft' && (int)$order->total_paid===0) {
            $order->status='failed';
            $order->save();
            if (method_exists($order,'transaction') && $order->transaction && $order->transaction->status==='pending') {
                $order->transaction->status='failed';
                $order->transaction->save();
            }
            if ($holder->payment_status==='pending') {
                Booking::where('id',$holder->id)->update(['payment_status'=>'']);
                $holder->payment_status='';
            }
            return;
        }
        if ($order->status==='paid') {
            $order->status='canceled';
            $order->save();
        }
    }
}
