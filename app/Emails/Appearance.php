<?php
namespace WaasKit\FluentBooking\Emails;

/** Global appearance, independent of event and guest settings. */
final class Appearance
{
    public const OPTION='waaskit_fb_email_border_color';
    public const ENABLED_OPTION='waaskit_fb_email_appearance_enabled';
    public const DEFAULT_COLOR='#111111';

    public function register(): void
    {
        add_filter('wp_mail', [$this, 'filterMail'], 999);
        add_action('admin_post_waaskit_fb_email_appearance', [$this, 'save']);
    }

    public static function enabled(): bool
    {
        return (bool) get_option(self::ENABLED_OPTION, false);
    }

    public static function color(): string
    {
        $color=get_option(self::OPTION, self::DEFAULT_COLOR);
        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/iD', $color) ? strtolower($color) : self::DEFAULT_COLOR;
    }

    public function filterMail(array $mail): array
    {
        if (!self::enabled() || !isset($mail['message']) || !is_string($mail['message'])) {return $mail;}
        // The native mailer has no dedicated body filter. Check the sender call
        // stack, not a word or URL in the body that another plugin could also use.
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
            if (($frame['class']??'')==='FluentBooking\\App\\Services\\Mailer' && ($frame['function']??'')==='send') {
                $mail['message']=preg_replace('/(border-top:\s*4px\s+solid\s+)#0069ff\b/i', '${1}'.self::color(), $mail['message'], 1);
                break;
            }
        }
        return $mail;
    }

    public function save(): void
    {
        if (!current_user_can('manage_options')) {wp_die('Accès refusé.', '', ['response'=>403]);}
        check_admin_referer('waaskit_fb_email_appearance');
        $enabled=($_POST['email_appearance_enabled']??'')==='1';
        $color=$enabled?wp_unslash($_POST['email_border_color']??''):self::color();
        if (!is_string($color) || !preg_match('/^#[0-9a-f]{6}$/iD', $color)) {
            wp_die('Choisissez une couleur au format #123456.', '', ['response'=>400, 'back_link'=>true]);
        }
        update_option(self::OPTION, strtolower($color), false);
        update_option(self::ENABLED_OPTION, $enabled, false);
        wp_safe_redirect(admin_url('admin.php?page=waaskit-fluent-booking&email_saved=1#fba-email-appearance'));
        exit;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {return;}
        echo '<section class="fba-card" id="fba-email-appearance"><header class="fba-card-header"><div><h3>Apparence des emails</h3><p>Un réglage commun à tous les événements FluentBooking.</p></div></header>';
        echo '<form class="fba-form fba-email-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('waaskit_fb_email_appearance');
        echo '<input type="hidden" name="action" value="waaskit_fb_email_appearance">';
        if (isset($_GET['email_saved'])) {echo '<p role="status">Couleur enregistrée.</p>';}
        $enabled=self::enabled();
        echo '<label class="fba-email-toggle"><input type="checkbox" name="email_appearance_enabled" value="1"'.checked($enabled,true,false).' aria-controls="fba-email-options" aria-expanded="'.($enabled?'true':'false').'"> <span>Personnaliser la couleur des emails</span></label>';
        echo '<div id="fba-email-options"'.($enabled?'':' hidden').'><div class="fba-email-color-row"><label for="fba-email-color">Couleur de la bordure</label><input type="color" id="fba-email-color" name="email_border_color" value="'.esc_attr(self::color()).'"></div><p class="description">La couleur choisie remplace la bordure en haut des prochains emails FluentBooking.</p></div>';
        echo '<p class="description">Décochez pour conserver l’apparence native FluentBooking. Enregistrez pour appliquer votre choix.</p>';
        submit_button('Enregistrer l’apparence');
        echo '</form></section>';
    }
}
