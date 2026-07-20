<?php

namespace App\Support\Payments;

use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Support\Payments\Concerns\StoresCheckoutSessions;
use App\Support\Payments\Eps\EpsClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class EpsPaymentGateway implements PaymentGateway
{
    use StoresCheckoutSessions;

    public const PURPOSE_ORDER_RESIDUAL = 'order_residual';

    public function __construct(
        protected ?EpsClient $client = null
    ) {
        $this->client ??= EpsClient::fromConfig();
    }

    public function driver(): string
    {
        return 'eps';
    }

    public function createCheckout(int $userId, int $amount, array $metadata = []): array
    {
        if ($amount < 1) {
            throw new RuntimeException('Payment amount must be at least ৳1.');
        }

        $token = Str::random(40);
        $merchantTransactionId = $this->makeMerchantTransactionId();
        $customer = $this->resolveCustomer($userId, $metadata);
        $productName = $this->resolveProductName($metadata);

        $init = $this->client->initializePayment([
            'merchantId' => config('payments.eps.merchant_id'),
            'storeId' => config('payments.eps.store_id'),
            'CustomerOrderId' => 'MIDDO-'.$token,
            'merchantTransactionId' => $merchantTransactionId,
            'transactionTypeId' => 1,
            'totalAmount' => (float) $amount,
            'successUrl' => route('payments.eps.success', ['token' => $token]),
            'failUrl' => route('payments.eps.fail', ['token' => $token]),
            'cancelUrl' => route('payments.eps.cancel', ['token' => $token]),
            'customerName' => $customer['name'],
            'customerEmail' => $customer['email'],
            'CustomerAddress' => $customer['address'],
            'CustomerAddress2' => '',
            'CustomerCity' => $customer['city'],
            'CustomerState' => $customer['state'],
            'CustomerPostcode' => $customer['postcode'],
            'CustomerCountry' => 'BD',
            'CustomerPhone' => $customer['phone'],
            'productName' => $productName,
            'productProfile' => 'general',
            'productCategory' => 'food',
            'ipAddress' => request()->ip() ?: '127.0.0.1',
            'version' => '1',
            'financialEntityId' => 0,
            'transitionStatusId' => 0,
            'ShippingMethod' => 'NO',
            'NoOfItem' => '1',
            'ValueA' => $token,
            'ValueB' => (string) ($metadata['purpose'] ?? 'order_prepay'),
            'ProductList' => [[
                'ProductName' => $productName,
                'NoOfItem' => '1',
                'ProductProfile' => 'general',
                'ProductCategory' => 'food',
                'ProductPrice' => (string) $amount,
            ]],
        ]);

        $payload = [
            'driver' => $this->driver(),
            'user_id' => $userId,
            'amount' => $amount,
            'fingerprint' => $this->fingerprint($metadata),
            'metadata' => $metadata,
            'paid' => false,
            'merchant_transaction_id' => $merchantTransactionId,
            'eps_transaction_id' => $init['TransactionId'],
            'redirect_url' => $init['RedirectURL'],
            'created_at' => now()->toIso8601String(),
        ];

        $this->storeSession($token, $payload, $merchantTransactionId);

        return [
            'token' => $token,
            'amount' => $amount,
            'paid' => false,
            'payment_url' => $init['RedirectURL'],
            'merchant_transaction_id' => $merchantTransactionId,
        ];
    }

    public function paymentUrl(string $token): string
    {
        $payload = $this->find($token);

        if (is_array($payload) && ! ($payload['paid'] ?? false) && filled($payload['redirect_url'] ?? null)) {
            return (string) $payload['redirect_url'];
        }

        return URL::temporarySignedRoute(
            'corporate.gateway-prepay.show',
            now()->addMinutes(45),
            ['token' => $token]
        );
    }

    /**
     * Confirm payment with EPS verification API, then mark the local session paid.
     *
     * @return array{ok: bool, message?: string, payload?: array}
     */
    public function confirmFromCallback(string $token, ?string $merchantTransactionId = null): array
    {
        $payload = $this->find($token);

        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Payment session expired or invalid.'];
        }

        if ($payload['paid'] ?? false) {
            return ['ok' => true, 'payload' => $payload, 'message' => 'Payment already recorded.'];
        }

        $txnId = $merchantTransactionId
            ?: (string) ($payload['merchant_transaction_id'] ?? '');

        if ($txnId === '') {
            return ['ok' => false, 'message' => 'Missing EPS merchant transaction id.'];
        }

        $verification = $this->client->verifyTransaction($txnId);

        if (! ($verification['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => $verification['message'] ?? 'EPS payment was not successful.',
                'payload' => $payload,
            ];
        }

        $paidAmount = $verification['total_amount'];
        if ($paidAmount !== null && (int) round($paidAmount) !== (int) ($payload['amount'] ?? 0)) {
            return [
                'ok' => false,
                'message' => 'Paid amount does not match the checkout amount.',
                'payload' => $payload,
            ];
        }

        $this->markPaid($token);
        $fresh = $this->find($token) ?? $payload;
        $fresh['eps_status'] = $verification['status'];
        $fresh['eps_verified_at'] = now()->toIso8601String();
        $fresh['eps_raw'] = $verification['raw'] ?? [];
        $this->refreshSession($token, $fresh);

        return ['ok' => true, 'payload' => $fresh, 'message' => 'Payment successful.'];
    }

    protected function makeMerchantTransactionId(): string
    {
        // EPS requires a unique id with minimum length 10.
        return 'MDD'.now()->format('YmdHis').Str::upper(Str::random(6));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{name: string, email: string, phone: string, address: string, city: string, state: string, postcode: string}
     */
    protected function resolveCustomer(int $userId, array $metadata): array
    {
        $user = User::query()->with(['city'])->find($userId);

        $name = $metadata['receiver_name']
            ?? $metadata['customer_name']
            ?? $user?->full_name
            ?? trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));
        $name = filled($name) ? (string) $name : 'Middo Customer';

        $phone = (string) ($metadata['mobile']
            ?? $metadata['customer_phone']
            ?? $user?->mobile
            ?? '01700000000');

        $address = $metadata['address']
            ?? $metadata['customer_address']
            ?? $user?->address;
        $address = filled($address) ? (string) $address : 'Dhaka';

        $city = (string) ($metadata['customer_city']
            ?? $user?->city_name
            ?? 'Dhaka');

        $email = (string) ($metadata['customer_email'] ?? '');
        if ($email === '') {
            $digits = preg_replace('/\D+/', '', $phone) ?: (string) $userId;
            $email = 'customer'.$digits.'@customers.middo.app';
        }

        return [
            'name' => mb_substr($name, 0, 120) ?: 'Middo Customer',
            'email' => $email,
            'phone' => mb_substr($phone, 0, 20),
            'address' => mb_substr($address, 0, 200),
            'city' => mb_substr($city, 0, 80),
            'state' => mb_substr((string) ($metadata['customer_state'] ?? $city), 0, 80),
            'postcode' => (string) ($metadata['customer_postcode'] ?? '1200'),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function resolveProductName(array $metadata): string
    {
        return match ($metadata['purpose'] ?? null) {
            'wallet_top_up' => 'Middo Balance Top-up',
            self::PURPOSE_ORDER_RESIDUAL => 'Middo Order Payment',
            default => 'Middo Meal Order',
        };
    }
}
