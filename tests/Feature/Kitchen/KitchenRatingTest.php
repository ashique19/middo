<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\Profile;
use App\Livewire\Shared\StaffProfileShow;
use App\Models\KitchenRatingLog;
use App\Models\Role;
use App\Models\User;
use App\Support\DeliveryApiPresenter;
use App\Support\KitchenApiPresenter;
use App\Support\KitchenPermissions;
use App\Support\OperationApiPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenRatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'operation']);
        Role::create(['name' => 'delivery']);
        KitchenPermissions::syncKitchenRole($kitchenRole);
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

    public function test_admin_rating_changes_are_stored_with_history(): void
    {
        $admin = $this->user('admin', ['first_name' => 'Ada', 'last_name' => 'Min', 'mobile' => '01310667001']);
        $kitchen = $this->user('kitchen', ['mobile' => '01310667002']);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('kitchen_rating', '8')
            ->set('kitchen_rating_note', 'Strong lunch service')
            ->call('saveKitchenRating')
            ->assertSee('Strong lunch service')
            ->assertSee('Ada Min')
            ->set('kitchen_rating', '5')
            ->set('kitchen_rating_note', 'Slower week')
            ->call('saveKitchenRating')
            ->assertSee('8')
            ->assertSee('5')
            ->assertSee('Slower week')
            ->call('saveKitchenRating');

        $kitchen->refresh();
        $this->assertSame(5, $kitchen->kitchen_rating);
        $this->assertSame('Slower week', $kitchen->kitchen_rating_note);
        $this->assertSame(2, KitchenRatingLog::query()->where('kitchen_id', $kitchen->id)->count());

        $logs = KitchenRatingLog::query()->where('kitchen_id', $kitchen->id)->orderBy('id')->get();
        $this->assertNull($logs[0]->old_rating);
        $this->assertSame(8, $logs[0]->new_rating);
        $this->assertSame('Strong lunch service', $logs[0]->note);
        $this->assertSame($admin->id, $logs[0]->actor_id);
        $this->assertSame(8, $logs[1]->old_rating);
        $this->assertSame(5, $logs[1]->new_rating);
        $this->assertSame('Slower week', $logs[1]->note);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('kitchen_rating', '5')
            ->set('kitchen_rating_note', 'Note only')
            ->call('saveKitchenRating');

        $this->assertSame(3, KitchenRatingLog::query()->where('kitchen_id', $kitchen->id)->count());
        $latest = KitchenRatingLog::query()->where('kitchen_id', $kitchen->id)->orderByDesc('id')->first();
        $this->assertSame(5, $latest->old_rating);
        $this->assertSame(5, $latest->new_rating);
        $this->assertSame('Note only', $latest->note);
    }

    public function test_invalid_rating_is_rejected_and_clearing_is_logged(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310667011']);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310667012',
            'kitchen_rating' => 4,
            'kitchen_rating_note' => 'Earlier',
        ]);

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('kitchen_rating', '11')
            ->call('saveKitchenRating')
            ->assertHasErrors(['kitchen_rating']);

        $this->assertSame(4, $kitchen->fresh()->kitchen_rating);
        $this->assertSame(0, KitchenRatingLog::query()->count());

        Livewire::actingAs($admin)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->set('kitchen_rating', '')
            ->set('kitchen_rating_note', '')
            ->call('saveKitchenRating');

        $kitchen->refresh();
        $this->assertNull($kitchen->kitchen_rating);
        $this->assertNull($kitchen->kitchen_rating_note);
        $log = KitchenRatingLog::query()->first();
        $this->assertSame(4, $log->old_rating);
        $this->assertNull($log->new_rating);
        $this->assertNull($log->note);
    }

    public function test_rating_is_hidden_from_ops_kitchen_and_rider_surfaces(): void
    {
        $admin = $this->user('admin', ['mobile' => '01310667021']);
        $ops = $this->user('operation', ['mobile' => '01310667022']);
        $rider = $this->user('delivery', ['mobile' => '01310667023']);
        $kitchen = $this->user('kitchen', [
            'mobile' => '01310667024',
            'kitchen_rating' => 9,
            'kitchen_rating_note' => 'Confidential score',
        ]);

        Livewire::actingAs($ops)
            ->test(StaffProfileShow::class, ['kitchen' => $kitchen])
            ->assertSet('kitchen_rating', '')
            ->assertSet('kitchen_rating_note', '')
            ->assertDontSee('Confidential score')
            ->assertDontSee('Save rating')
            ->call('saveKitchenRating')
            ->assertForbidden();

        $this->assertSame(9, $kitchen->fresh()->kitchen_rating);

        $this->actingAs($ops)
            ->get(route('operation.kitchens.show', $kitchen))
            ->assertOk()
            ->assertDontSee('Confidential score')
            ->assertDontSee('Save rating');

        $this->actingAs($admin)
            ->get(route('admin.kitchens.show', $kitchen))
            ->assertOk()
            ->assertSee('Save rating')
            ->assertSee('Confidential score');

        Livewire::actingAs($kitchen)
            ->test(Profile::class)
            ->assertDontSee('Confidential score')
            ->assertDontSee('Save rating');

        Sanctum::actingAs($kitchen);
        $me = $this->getJson('/api/kitchen/me')->assertOk();
        $this->assertArrayNotHasKey('kitchen_rating', $me->json('user'));
        $this->assertArrayNotHasKey('kitchen_rating_note', $me->json('user'));

        $this->patchJson('/api/kitchen/profile', [
            'kitchen_rating' => 1,
            'kitchen_rating_note' => 'Nope',
            'email' => 'chef@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_rating', 'kitchen_rating_note']);

        $kitchen->refresh();
        $this->assertSame(9, $kitchen->kitchen_rating);
        $this->assertSame('Confidential score', $kitchen->kitchen_rating_note);
        $this->assertNotSame('chef@example.com', $kitchen->email);

        $this->post('/api/kitchen/verification', [
            'kitchen_rating' => 2,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['kitchen_rating']);

        $presented = KitchenApiPresenter::user($kitchen->fresh());
        $this->assertArrayNotHasKey('kitchen_rating', $presented);
        $this->assertArrayNotHasKey('kitchen_rating_note', $presented);

        $party = OperationApiPresenter::party($kitchen->fresh());
        $this->assertArrayNotHasKey('kitchen_rating', $party);
        $this->assertArrayNotHasKey('kitchen_rating_note', $party);

        $riderPayload = DeliveryApiPresenter::user($rider);
        $this->assertArrayNotHasKey('kitchen_rating', $riderPayload);

        $encoded = $kitchen->fresh()->toArray();
        $this->assertArrayNotHasKey('kitchen_rating', $encoded);
        $this->assertArrayNotHasKey('kitchen_rating_note', $encoded);
    }
}
