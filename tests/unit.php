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
