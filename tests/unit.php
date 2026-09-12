<?php
require dirname(__DIR__) . '/autoload.php';
use WaasKit\FluentBooking\Configuration\Schema;
use WaasKit\FluentBooking\Rules\Registry;
use WaasKit\FluentBooking\Rules\ParticipantLimit;
function check($ok, $name) { if (!$ok) { throw new RuntimeException($name); } echo "PASS $name\n"; }
function rejects(callable $test, $name) { try { $test(); } catch (InvalidArgumentException | LogicException $e) { check(true, $name); return; } check(false, $name); }
$resolved = Schema::resolve(['site' => ['enabled' => true, 'max_participants' => 6], 'agenda' => ['max_participants' => 8], 'événement' => ['enabled' => false, 'max_participants' => 0]]);
check($resolved['enabled'] === ['value' => false, 'source' => 'événement'], 'explicit false wins');
check($resolved['max_participants']['value'] === 0, 'zero wins');
check(Schema::resolve(['site' => ['max_participants' => 6], 'agenda' => []])['max_participants']['source'] === 'site', 'empty override inherits');
check(Schema::resolve([])['enabled']['value'] === false, 'neutral defaults');
rejects(fn() => Schema::validate(['unknown' => 3]), 'unknown setting rejected');
rejects(fn() => Schema::validate(['enabled' => 'false']), 'string boolean rejected');
rejects(fn() => Schema::validate(['max_participants' => -1]), 'negative rejected');
rejects(fn() => Schema::validate(['max_participants' => 1001]), 'large count rejected');
$registry = new Registry(); $registry->add(new ParticipantLimit());
check($registry->validate(['participant_count' => 3], ['max_participants' => 2]) !== null, 'over limit refused');
check($registry->validate(['participant_count' => 2], ['max_participants' => 2]) === null, 'exact limit accepted');
check($registry->validate(['participant_count' => 999], ['max_participants' => 0]) === null, 'zero adds no constraint');
rejects(fn() => $registry->add(new ParticipantLimit()), 'duplicate rule rejected');

use WaasKit\FluentBooking\Domain\BookingProfile;
use WaasKit\FluentBooking\Domain\Party;
use WaasKit\FluentBooking\Domain\Capacity;
$p = BookingProfile::defaults();
$p['types'] = [
 ['id'=>'adult','label'=>'Adulte','price'=>2000,'units'=>1,'min_age'=>18,'max_age'=>120,'requires'=>'','fields'=>[]],
 ['id'=>'child','label'=>'Enfant','price'=>1500,'units'=>1,'min_age'=>10,'max_age'=>17,'requires'=>'adult','fields'=>[]]
];
$at = new DateTimeImmutable('2026-09-12');
$adult = ['type'=>'adult','name'=>'Parent','birth_date'=>'1990-01-01'];
$child = ['type'=>'child','name'=>'Enfant','birth_date'=>'2016-09-12'];
$q = Party::evaluate([$adult, $adult, $child], $p, $at);
check($q['subtotal'] === 5500 && $q['units'] === 3, '2 adults and child charge 5500 cents and consume 3 places');
rejects(fn()=>Party::evaluate([$child], $p, $at), 'child requires adult');
rejects(fn()=>Party::evaluate([array_replace($child,['birth_date'=>'2016-09-13'])],$p,$at), 'age uses birthday boundary');
rejects(fn()=>Party::evaluate([array_replace($adult,['birth_date'=>'1990-02-30'])],$p,$at), 'impossible date rejected');
rejects(fn()=>Party::evaluate([array_replace($adult,['price'=>1])],$p,$at), 'client cannot submit prices');
rejects(fn()=>Party::evaluate([array_replace($adult,['type'=>'unknown'])],$p,$at), 'unknown type rejected');
rejects(fn()=>Party::evaluate([array_replace($adult,['name'=>'<script>x</script>'])],$p,$at), 'markup rejected');
$p['types'][1]['price'] = 0; $p['types'][1]['units'] = 0;
check(Party::evaluate([$adult,$child],$p,$at)['subtotal'] === 2000, 'free category supported');
check(Party::evaluate([$adult,$child],$p,$at)['units'] === 1, 'zero capacity weight supported');
$p['email'] = 'required';
rejects(fn()=>Party::evaluate([$adult],$p,$at), 'required participant email checked');
$p['email'] = 'optional';
check(Party::evaluate([$adult],$p,$at)['participants'][0]['email'] === '', 'optional email stays empty without synthetic address');
rejects(fn()=>BookingProfile::validate(['types'=>[$p['types'][0],$p['types'][0]]]), 'duplicate type configuration rejected');
check(Capacity::peak([['start'=>'10:00','end'=>'11:00','units'=>4],['start'=>'11:00','end'=>'12:00','units'=>5]],'10:00','12:00') === 5, 'adjacent intervals do not overlap');
check(Capacity::peak([['start'=>'10:00','end'=>'11:30','units'=>4],['start'=>'11:00','end'=>'12:00','units'=>5]],'10:00','12:00') === 9, 'overlapping intervals sum weights');
