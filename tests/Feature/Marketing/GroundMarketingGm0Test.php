<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Marketing\Companies;
use App\Livewire\Marketing\CompanyShow;
use App\Models\Area;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanyAppointment;
use App\Models\Role;
use App\Models\User;
use App\Support\SignupOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroundMarketingGm0Test extends TestCase
{
    use RefreshDatabase;

    protected User $marketer;

    protected City $city;

    protected Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->firstOrCreate(['name' => 'ground_marketing']);
        Role::query()->firstOrCreate(['name' => 'corporate']);

        $this->city = City::create(['name' => 'Dhaka']);
        $this->area = Area::create(['name' => 'Gulshan', 'city_id' => $this->city->id]);

        $this->marketer = User::create([
            'first_name' => 'Ground',
            'last_name' => 'Marketer',
            'mobile' => '01310123463',
            'password' => 'password',
            'role_id' => Role::where('name', 'ground_marketing')->value('id'),
            'status' => 'active',
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
        ]);
    }

    public function test_marketer_creates_company_lead_and_books_appointment(): void
    {
        Livewire::actingAs($this->marketer)
            ->test(Companies::class)
            ->call('openCreate')
            ->set('name', 'Acme Soft Ltd')
            ->set('address', 'House 12, Road 5, Gulshan')
            ->set('cityId', $this->city->id)
            ->set('areaId', $this->area->id)
            ->set('hrName', 'Sara HR')
            ->set('hrMobile', '01720000001')
            ->call('createCompany');

        $company = Company::query()->firstOrFail();
        $this->assertSame('Acme Soft Ltd', $company->name);
        $this->assertSame(Company::STATUS_LEAD, $company->status);
        $this->assertSame($this->marketer->id, (int) $company->created_by);

        Livewire::actingAs($this->marketer)
            ->test(CompanyShow::class, ['company' => $company])
            ->set('appointmentAt', now('Asia/Dhaka')->addDay()->format('Y-m-d\\TH:i'))
            ->set('appointmentHrName', 'Sara HR')
            ->call('scheduleAppointment')
            ->assertSet('errorMessage', '');

        $this->assertSame(Company::STATUS_APPOINTMENT_SET, $company->fresh()->status);
        $this->assertSame(1, CompanyAppointment::query()->count());
    }

    public function test_field_signup_requires_otp_then_creates_corporate_under_company(): void
    {
        $company = Company::create([
            'name' => 'Acme Soft Ltd',
            'address' => 'House 12, Road 5, Gulshan',
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
            'status' => Company::STATUS_VISITED,
            'created_by' => $this->marketer->id,
        ]);

        $mobile = '01730000099';

        Livewire::actingAs($this->marketer)
            ->test(CompanyShow::class, ['company' => $company])
            ->set('signupFirstName', 'Employee')
            ->set('signupLastName', 'One')
            ->set('signupMobile', $mobile)
            ->call('sendSignupOtp')
            ->assertSet('otpSent', true)
            ->assertSet('errorMessage', '');

        $otp = cache()->get(SignupOtp::cacheKey($mobile));
        $this->assertNotEmpty($otp);

        Livewire::actingAs($this->marketer)
            ->test(CompanyShow::class, ['company' => $company])
            ->set('signupFirstName', 'Employee')
            ->set('signupLastName', 'One')
            ->set('signupMobile', $mobile)
            ->set('otpSent', true)
            ->set('signupOtp', $otp)
            ->set('signupPassword', 'password123')
            ->call('completeSignup')
            ->assertSet('errorMessage', '');

        $employee = User::query()->where('mobile', $mobile)->firstOrFail();
        $this->assertSame('corporate', $employee->role->name);
        $this->assertSame($company->id, (int) $employee->company_id);
        $this->assertSame('Acme Soft Ltd', $employee->company_name);
        $this->assertSame($company->address, $employee->address);
        $this->assertSame($company->area_id, $employee->area_id);
        $this->assertTrue((bool) $employee->is_mobile_verified);
        $this->assertSame(Company::STATUS_ACTIVE, $company->fresh()->status);
    }

    public function test_dashboard_redirects_ground_marketing(): void
    {
        $this->actingAs($this->marketer)
            ->get(route('dashboard.redirect'))
            ->assertRedirect(route('marketing.dashboard'));
    }
}
