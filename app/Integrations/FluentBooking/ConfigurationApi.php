<?php
namespace WaasKit\FluentBooking\Integrations\FluentBooking;

use WaasKit\FluentBooking\Admin\SettingsPage;
use WaasKit\FluentBooking\Configuration\Schema;

final class ConfigurationApi
{
    public function __construct(private ConfigurationStore $store) {}
    public function register(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('fluent-booking-addon/v1', '/configuration/(?P<scope>site|calendar|calendar_event)/(?P<id>\d+)', [
                'methods'=>'GET',
                'permission_callback'=>static fn($r)=>SettingsPage::allowed($r['scope'], (int)$r['id']),
                'callback'=>fn($r)=>['format'=>'fluent-booking-addon', 'schema'=>Schema::VERSION, 'configuration'=>$this->store->read($r['scope'],(int)$r['id'])]
            ]);
        });
    }
}
