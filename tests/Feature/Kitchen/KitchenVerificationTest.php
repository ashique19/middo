<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Shared\StaffProfileShow;
use App\Models\Area;
use App\Models\City;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderGroup;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryApiPresenter;
use App\Support\KitchenPermissions;
use App\Support\KitchenVerification;
use App\Support\OperationApiPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $kitchenRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kitchenRole = Role::create(['name' => 'kitchen']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'operation']);
        Role::create(['name' => 'delivery']);
        Role::create(['name' => 'corporate']);
        KitchenPermissions::syncKitchenRole($this->kitchenRole);
        Storage::fake('public');
    }

    private function user(string $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'mobile' => '01310'.random_int(100000, 999999),
            'password' => '12345678',
            'role_id' => Role::query()->where('name', $role)->firstOrFail()->id,
            'status' => 'active',
            'is_mobile_verified' => true,
            'address' => 'Road 1',
        ], $overrides));
    }

    public function test_kitchen_api_rejects_identity_changes_and_accepts_email(): void
    {
        $kitchen = $this->user('kitchen', [
            'first_name' => 'Gulshan',
            'mobile' => '01310123453',
            'address' => 'Road 45',
        ]);
        Sanctum::actingAs($kitchen);

        $this->patchJson('/api/kitchen/profile', [
            'first_name' => 'Hacked',
            'last_name' => 'Chef',
            'mobile' => '01710000000',
            'address' => 'Somewhere else',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'mobile', 'address']);

        $kitchen->refresh();
        $this->assertSame('Gulshan', $kitchen->first_name);
        $this->assertSame('01310123453', $kitchen->mobile);
        $this->assertSame('Road 45', $kitchen->address);

        $this->patchJson('/api/kitchen/profile', [
            'email' => 'chef@example.com',
            'first_name' => 'Gulshan',
            'mobile' => '01310123453',
            'address' => 'Road 45',
        ])->assertOk()
            ->assertJsonPath('user.email', 'chef@example.com')
            ->assertJsonPath('user.first_name', 'Gulshan')
            ->assertJsonPath('user.profile_photo_url', null);

        $this->assertSame('chef@example.com', $kitchen->fresh()->email);
    }

    public function test_kitchen_can_upload_compressed_verification_images(): void
    {
        $kitchen = $this->user('kitchen', ['mobile' => '01310123453']);
        Sanctum::actingAs($kitchen);

        $uploaded = $this->post('/api/kitchen/verification', [
            'nid_number' => '1234567890123',
            'nid_front' => UploadedFile::fake()->image('front.png', 1800, 1200),
            'nid_back' => UploadedFile::fake()->image('back.png', 1600, 1000),
            'selfie' => UploadedFile::fake()->image('selfie.png', 2000, 2000),
        ])->assertOk()
            ->assertJsonPath('user.nid_number', '1234567890123');

        $this->assertStringContainsString('nid-front.jpg', (string) $uploaded->json('user.nid_front_url'));
        $this->assertStringContainsString('selfie.jpg', (string) $uploaded->json('user.profile_photo_url'));

        $kitchen->refresh();
        $this->assertSame('kitchen-verification/'.$kitchen->id.'/selfie.jpg', $kitchen->profile_photo_path);
        Storage::disk('public')->assertExists($kitchen->profile_photo_path);
        Storage::disk('public')->assertExists($kitchen->nid_front_path);
        Storage::disk('public')->assertExists($kitchen->nid_back_path);

        $stored = Storage::disk('public')->get($kitchen->profile_photo_path);
        $this->assertStringStartsWith("\xFF\xD8", $stored);

        $image = (new ImageManager(new Driver))->decodeBinary($stored);
        $this->assertLessThanOrEqual(KitchenVerification::MAX_EDGE, $image->width());
        $this->assertLessThanOrEqual(KitchenVerification::MAX_EDGE, $image->height());

        $me = $this->getJson('/api/kitchen/me')->assertOk()
            ->assertJsonPath('user.nid_number', '1234567890123');
        $this->assertStringContainsString('selfie.jpg', (string) $me->json('user.profile_photo_url'));
    }

    public function test_admin_can_edit_identity_and_verification_ops_cannot(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310666001']);
        $ops = $this->user('operation', ['mobile' => '01310666002']);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310666003',
            'first_name' => 'Old',
            'address' => 'Old road',
        ]);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('edit_first_name', 'New')
            ->set('edit_last_name', 'Chef')
            ->set('edit_mobile', '01310666009')
            ->set('edit_address', 'New road')
            ->call('saveKitchenIdentity');

        $kitchen->refresh();
        $this->assertSame('New', $kitchen->first_name);
        $this->assertSame('01310666009', $kitchen->mobile);
        $this->assertSame('New road', $kitchen->address);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('nid_number', '1098765432109')
            ->set('nid_front', UploadedFile::fake()->image('front.jpg', 1200, 800))
            ->set('nid_back', UploadedFile::fake()->image('back.jpg', 1200, 800))
            ->set('selfie', UploadedFile::fake()->image('selfie.jpg', 1400, 1400))
            ->call('saveKitchenVerification');

        $kitchen->refresh();
        $this->assertSame('1098765432109', $kitchen->nid_number);
        $this->assertNotNull($kitchen->profile_photo_path);
        Storage::disk('public')->assertExists($kitchen->profile_photo_path);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->call('deleteKitchenVerificationImage', 'selfie')
            ->call('clearKitchenNidNumber');

        $kitchen->refresh();
        $this->assertNull($kitchen->profile_photo_path);
        $this->assertNull($kitchen->nid_number);
        $this->assertNotNull($kitchen->nid_front_path);

        Livewire::actingAs($ops)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->call('saveKitchenIdentity')
            ->assertForbidden();

        Livewire::actingAs($ops)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->call('deleteKitchenVerificationImage', 'nid_front')
            ->assertForbidden();

        $this->assertNotNull($kitchen->fresh()->nid_front_path);
    }

    public function test_ops_and_rider_payloads_expose_selfie_without_nid(): void
    {
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310777001',
            'nid_number' => '1234567890',
            'nid_front_path' => 'kitchen-verification/1/nid-front.jpg',
            'profile_photo_path' => 'kitchen-verification/1/selfie.jpg',
        ]);
        Storage::disk('public')->put($kitchen->profile_photo_path, 'jpeg');

        $party = OperationApiPresenter::party($kitchen);
        $this->assertStringContainsString('selfie.jpg', (string) $party['profile_photo_url']);
        $this->assertArrayNotHasKey('nid_number', $party);
        $this->assertArrayNotHasKey('nid_front_url', $party);

        $rider = $this->user('delivery', ['mobile' => '01310777002']);
        $corporate = $this->user('corporate', ['mobile' => '01310777003']);
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Gulshan', 'city_id' => $city->id]);
        $menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 200,
        ]);
        $order = Order::create([
            'user_id' => $corporate->id,
            'menu_item_id' => $menu->id,
            'quantity' => 1,
            'delivery_date' => now()->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 200,
            'address' => 'Customer road',
            'area_id' => $area->id,
            'order_status' => 'packed',
            'payment_status' => 'paid',
            'delivery_rider_id' => $rider->id,
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);
        $group = OrderGroup::create([
            'name' => 'Lunch',
            'menu_id' => $menu->id,
            'area_id' => $area->id,
            'delivery_date' => now()->toDateString(),
            'kitchen_id' => $kitchen->id,
            'created_by' => $corporate->id,
            'updated_by' => $corporate->id,
        ]);
        $group->orders()->attach($order->id);

        $run = DeliveryApiPresenter::run($order->fresh(), $rider);
        $this->assertStringContainsString('selfie.jpg', (string) $run['kitchen_profile_photo_url']);
        $this->assertArrayNotHasKey('nid_number', $run);

        $groupPayload = OperationApiPresenter::orderGroup($group->fresh());
        $this->assertStringContainsString('selfie.jpg', (string) $groupPayload['kitchen_profile_photo_url']);
        $this->assertArrayNotHasKey('nid_number', $groupPayload);
    }

    public function test_kitchen_signup_stores_optional_verification(): void
    {
        $city = City::create(['name' => 'Dhaka']);
        $area = Area::create(['name' => 'Banani', 'city_id' => $city->id]);

        $this->post('/kitchen-signup', [
            'first_name' => 'Signup',
            'last_name' => 'Chef',
            'mobile' => '01310888001',
            'password' => '12345678',
            'address' => 'Lane 4',
            'city_id' => $city->id,
            'area_id' => $area->id,
            'nid_number' => '1234567890123',
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 1300, 900),
        ])->assertOk();

        $kitchen = User::query()->where('mobile', '01310888001')->firstOrFail();
        $this->assertSame('pending', $kitchen->status);
        $this->assertSame('1234567890123', $kitchen->nid_number);
        $this->assertNotNull($kitchen->profile_photo_path);
        Storage::disk('public')->assertExists($kitchen->profile_photo_path);
    }
}
