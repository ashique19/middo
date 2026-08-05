<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ground marketing field signup: OTP-confirm mobile, then create corporate under a company.
 */
class GroundMarketingSignup
{
    /**
     * @return array{ok: bool, message: string, debug_otp?: string}
     */
    public static function sendOtp(string $mobile): array
    {
        $mobile = trim($mobile);
        if (User::query()->where('mobile', $mobile)->exists()) {
            return [
                'ok' => false,
                'message' => 'This mobile number is already registered.',
            ];
        }

        return SignupOtp::send($mobile);
    }

    /**
     * @param  array{
     *   first_name:string,
     *   last_name:string,
     *   mobile:string,
     *   otp:string,
     *   password:string
     * }  $payload
     */
    public static function complete(Company $company, array $payload, int $actorId): User
    {
        $mobile = trim($payload['mobile']);
        if (! SignupOtp::verify($mobile, (string) $payload['otp'])) {
            throw new \InvalidArgumentException('Invalid or expired verification code.');
        }

        if (User::query()->where('mobile', $mobile)->exists()) {
            throw new \RuntimeException('This mobile number is already registered.');
        }

        $roleId = Role::query()->where('name', 'corporate')->value('id');
        if (! $roleId) {
            throw new \RuntimeException('Corporate role is missing.');
        }

        return DB::transaction(function () use ($company, $payload, $mobile, $roleId, $actorId) {
            $user = User::query()->create([
                'first_name' => trim($payload['first_name']),
                'last_name' => trim($payload['last_name']),
                'mobile' => $mobile,
                'password' => $payload['password'],
                'company_id' => $company->id,
                'company_name' => $company->name,
                'address' => $company->address,
                'city_id' => $company->city_id,
                'area_id' => $company->area_id,
                'role_id' => $roleId,
                'status' => 'active',
                'is_mobile_verified' => true,
            ]);

            $company->markActiveIfHasEmployees();

            // Touch status to visited/active trail without inventing Middo login.
            if (in_array($company->status, [Company::STATUS_LEAD, Company::STATUS_APPOINTMENT_SET], true)) {
                $company->update(['status' => Company::STATUS_VISITED]);
                $company->markActiveIfHasEmployees();
            }

            unset($actorId); // reserved for future audit log

            return $user;
        });
    }
}
