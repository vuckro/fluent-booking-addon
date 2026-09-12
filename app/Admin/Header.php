<?php
namespace WaasKit\FluentBooking\Admin;

use FluentBooking\App\Services\PermissionManager;

/** Standalone navigation using FluentBooking assets; no native application mount. */
final class Header
{
    public function render(): void
    {
        $base = admin_url('admin.php?page=fluent-booking#/');
        $items = [
            ['key' => 'dashboard', 'label' => __('Dashboard', 'fluent-booking'), 'permalink' => $base],
            ['key' => 'calendars', 'label' => __('Calendars', 'fluent-booking'), 'permalink' => $base . 'calendars'],
            ['key' => 'scheduled-events', 'label' => __('Bookings', 'fluent-booking'), 'permalink' => $base . 'scheduled-events'],
            ['key' => 'availability', 'label' => __('Availability', 'fluent-booking'), 'permalink' => $base . 'availability'],
        ];
        $assets = \FluentBooking\App\App::getInstance()['url.assets'];
        echo '<nav class="fba-navigation" aria-label="FluentBooking"><a class="fba-logo" href="' . esc_url($base) . '"><img src="' . esc_url($assets . 'images/logo.svg') . '" class="fba-logo-light" alt="FluentBooking"><img src="' . esc_url($assets . 'images/logo_dark.svg') . '" class="fba-logo-dark" alt="FluentBooking"></a><div class="fba-navigation-links">';
        foreach (apply_filters('fluent_booking/admin_menu_items', $items) as $item) {
            $active = $item['key'] === 'waaskit-modules';
            echo '<a href="' . esc_url($item['permalink']) . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($item['label']) . '</a>';
        }
        echo '</div><div class="fba-navigation-actions"><button type="button" class="fba-theme-toggle" aria-label="Activer le mode sombre" aria-pressed="false"><svg class="fcal_light_icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M17.9163 11.7317C16.9166 12.2654 15.7748 12.5681 14.5623 12.5681C10.6239 12.5681 7.43128 9.37543 7.43128 5.43705C7.43128 4.22456 7.73388 3.08274 8.2677 2.08301C4.72272 2.91382 2.08301 6.09562 2.08301 9.89393C2.08301 14.3246 5.67476 17.9163 10.1054 17.9163C13.9038 17.9163 17.0855 15.2767 17.9163 11.7317Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg><svg class="fcal_dark_icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2906_20675)"> <path d="M14.1663 10.0007C14.1663 12.3018 12.3009 14.1673 9.99967 14.1673C7.69849 14.1673 5.83301 12.3018 5.83301 10.0007C5.83301 7.69946 7.69849 5.83398 9.99967 5.83398C12.3009 5.83398 14.1663 7.69946 14.1663 10.0007Z" stroke="currentColor" stroke-width="1.5"/> <path d="M9.99984 1.66699V2.91699M9.99984 17.0837V18.3337M15.8922 15.8931L15.0083 15.0092M4.99089 4.99137L4.107 4.10749M18.3332 10.0003H17.0832M2.9165 10.0003H1.6665M15.8926 4.10758L15.0087 4.99147M4.99129 15.0093L4.10741 15.8932" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></g><defs><clipPath id="clip0_2906_20675"><rect width="20" height="20" fill="currentColor"/></clipPath></defs> </svg></button>';
        if (PermissionManager::userCan('manage_all_data')) {
            echo '<a class="fba-navigation-settings" href="' . esc_url($base . 'settings/general-settings') . '"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024"><path fill="currentColor" d="M600.704 64a32 32 0 0 1 30.464 22.208l35.2 109.376c14.784 7.232 28.928 15.36 42.432 24.512l112.384-24.192a32 32 0 0 1 34.432 15.36L944.32 364.8a32 32 0 0 1-4.032 37.504l-77.12 85.12a357.12 357.12 0 0 1 0 49.024l77.12 85.248a32 32 0 0 1 4.032 37.504l-88.704 153.6a32 32 0 0 1-34.432 15.296L708.8 803.904c-13.44 9.088-27.648 17.28-42.368 24.512l-35.264 109.376A32 32 0 0 1 600.704 960H423.296a32 32 0 0 1-30.464-22.208L357.696 828.48a351.616 351.616 0 0 1-42.56-24.64l-112.32 24.256a32 32 0 0 1-34.432-15.36L79.68 659.2a32 32 0 0 1 4.032-37.504l77.12-85.248a357.12 357.12 0 0 1 0-48.896l-77.12-85.248A32 32 0 0 1 79.68 364.8l88.704-153.6a32 32 0 0 1 34.432-15.296l112.32 24.256c13.568-9.152 27.776-17.408 42.56-24.64l35.2-109.312A32 32 0 0 1 423.232 64H600.64zm-23.424 64H446.72l-36.352 113.088-24.512 11.968a294.113 294.113 0 0 0-34.816 20.096l-22.656 15.36-116.224-25.088-65.28 113.152 79.68 88.192-1.92 27.136a293.12 293.12 0 0 0 0 40.192l1.92 27.136-79.808 88.192 65.344 113.152 116.224-25.024 22.656 15.296a294.113 294.113 0 0 0 34.816 20.096l24.512 11.968L446.72 896h130.688l36.48-113.152 24.448-11.904a288.282 288.282 0 0 0 34.752-20.096l22.592-15.296 116.288 25.024 65.28-113.152-79.744-88.192 1.92-27.136a293.12 293.12 0 0 0 0-40.256l-1.92-27.136 79.808-88.128-65.344-113.152-116.288 24.96-22.592-15.232a287.616 287.616 0 0 0-34.752-20.096l-24.448-11.904L577.344 128zM512 320a192 192 0 1 1 0 384 192 192 0 0 1 0-384zm0 64a128 128 0 1 0 0 256 128 128 0 0 0 0-256z"></path></svg> ' . esc_html__('Settings', 'fluent-booking') . '</a>';
        }
        echo '</div></nav>';
    }

}
