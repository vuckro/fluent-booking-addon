<?php
namespace WaasKit\FluentBooking\Payments;

use FluentBooking\App\Models\Booking;
use FluentBookingPro\App\Models\Order;
use FluentBookingPro\App\Services\Integrations\PaymentMethods\Stripe\StripeSettings;
use WaasKit\FluentBooking\Guests\BookingAdapter;

/** End an unpaid Stripe attempt before allowing a fresh reservation. */
final class EditCheckout
{
    public function register(): void
    {
        add_action('wp_ajax_fba_edit_checkout', [$this, 'handle']);
        add_action('wp_ajax_nopriv_fba_edit_checkout', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(['message'=>'Requête non autorisée.'], 405);
        }
        foreach (['booking_id','intent_id','client_secret','booking_hash'] as $key) {
            if (!isset($_POST[$key]) || !is_scalar($_POST[$key])) wp_send_json_error(['message'=>'Requête non valide.'], 400);
        }
        try {
            $this->cancel((int)($_POST['booking_id'] ?? 0), (string)wp_unslash($_POST['intent_id'] ?? ''), (string)wp_unslash($_POST['client_secret'] ?? ''), (string)wp_unslash($_POST['booking_hash'] ?? ''));
            wp_send_json_success(['message'=>'Paiement annulé. Vous pouvez modifier votre réservation.']);
        } catch (\Exception $error) {
            wp_send_json_error(['message'=>$error->getMessage()], 409);
        }
    }

    public function cancel(int $bookingId, string $intentId, string $clientSecret, string $bookingHash): void
    {
        if (!class_exists(StripeSettings::class) || strlen($bookingHash) > 128 || $bookingId < 1 || !preg_match('/^pi_[A-Za-z0-9]{1,100}$/D', $intentId)
            || strlen($clientSecret) > 256 || !str_starts_with($clientSecret, $intentId.'_secret_')
            || strlen($clientSecret) <= strlen($intentId.'_secret_')) {
            throw new \RuntimeException('Cette session de paiement ne peut pas être modifiée.');
        }
        global $wpdb;
        $lock='fba_checkout_'.$bookingId;
        if ((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) {
            throw new \RuntimeException('Une modification est déjà en cours. Réessayez dans quelques instants.');
        }
        try {
            $booking=Booking::find($bookingId);
            $order=Order::where('parent_id', $bookingId)->first();
            if (!$booking || !$order || !hash_equals((string)$booking->hash, $bookingHash) || (int)$booking->parent_id || $booking->payment_method !== 'stripe'
                || empty($booking->getMeta(BookingAdapter::META, [])['attached'])) {
                throw new \RuntimeException('Cette session de paiement ne peut pas être modifiée.');
            }
            $this->assertUnpaid($booking, $order);
            // The client secret is an unguessable capability held by this checkout.
            // Validate it against Stripe and bind its metadata to the exact booking.
            // No anonymous WordPress nonce or caller-supplied amount is trusted.
            $intent=$this->request($intentId);
            if (($intent['id'] ?? '') !== $intentId || !is_string($intent['client_secret'] ?? null) || !hash_equals($intent['client_secret'], $clientSecret)
                || (string)($intent['metadata']['ref_id'] ?? '') !== (string)$booking->hash
                || (int)($intent['metadata']['booking_id'] ?? 0) !== $bookingId) {
                throw new \RuntimeException('Cette session de paiement ne peut pas être modifiée.');
            }
            if (!in_array($intent['status'] ?? '', ['requires_payment_method','requires_confirmation','requires_action','canceled'], true)
                || !empty($intent['amount_received']) || !empty($intent['amount_capturable'])) {
                throw new \RuntimeException('Le paiement est déjà effectué ou en cours de traitement. La réservation ne peut plus être modifiée ici.');
            }
            if ($intent['status'] !== 'canceled') {
                $intent=$this->request($intentId.'/cancel', ['cancellation_reason'=>'requested_by_customer']);
            }
            if (($intent['id'] ?? '') !== $intentId || ($intent['status'] ?? '') !== 'canceled') {
                throw new \RuntimeException('L’annulation du paiement n’a pas été confirmée. Réessayez avant de modifier la réservation.');
            }
            // Only release seats AFTER Stripe confirms cancellation. A competing
            // confirmation either wins at Stripe (cancel fails) or can never charge.
            $booking=Booking::find($bookingId);
            $order=Order::find($order->id);
            $this->assertUnpaid($booking, $order);
            $booking->status='cancelled';
            $booking->save();
            // Also repairs an interrupted previous attempt after parent save.
            Booking::where('parent_id', $bookingId)->update(['status'=>'cancelled']);
            $order->status='cancelled';
            $order->save();
            $order->transaction()->where('status', 'pending')->update(['status'=>'failed']);
            $booking->updateMeta('fba_checkout_cancelled', $intentId);
            // No cancellation notifications: this was an unpaid draft, not a
            // confirmed appointment. Retain its history instead of deleting it.
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private function assertUnpaid($booking, $order): void
    {
        if (!$booking || !$order || !in_array($booking->status, ['pending','cancelled'], true)
            || $booking->payment_status === 'paid' || (int)$order->total_paid > 0 || $order->status === 'paid') {
            throw new \RuntimeException('Cette réservation n’est plus en attente de paiement. Contactez l’organisateur pour la modifier.');
        }
    }

    private function request(string $path, ?array $body = null): array
    {
        $key=(new StripeSettings())->getApiKey();
        $response=wp_remote_request('https://api.stripe.com/v1/payment_intents/'.$path, [
            'method'=>$body === null ? 'GET' : 'POST', 'timeout'=>15, 'redirection'=>0,
            'headers'=>['Authorization'=>'Bearer '.$key, 'Content-Type'=>'application/x-www-form-urlencoded'],
            'body'=>$body === null ? null : http_build_query($body),
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new \RuntimeException('L’annulation du paiement n’a pas pu être confirmée. Réessayez avant de modifier la réservation.');
        }
        $data=json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['id'])) {
            throw new \RuntimeException('La réponse du service de paiement n’a pas pu être vérifiée. Réessayez.');
        }
        return $data;
    }
}
