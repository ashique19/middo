<?php

namespace Tests\Feature\Kitchen;

use App\Livewire\Kitchen\Complaints;
use App\Livewire\Kitchen\ComplaintShow;
use App\Livewire\Kitchen\Profile;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenHour;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderGroup;
use App\Models\OrderGroupEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\KitchenPermissions;
use App\Support\OrderGroupKitchenAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenTrustK6Test extends TestCase
{
    use RefreshDatabase;

    protected User $kitchen;

    protected User $otherKitchen;

    protected User $corporate;

    protected MenuItem $menu;

    protected City $city;

    protected Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $kitchenRole = Role::create(['name' => 'kitchen']);
        $corporateRole = Role::create(['name' => 'corporate']);
        Role::create(['name' => 'admin']);

        $this->kitchen = User::create([
            'first_name' => 'Gulshan',
            'last_name' => 'Kitchen',
            'mobile' => '01770000001',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'gold',
            'allowed_open_groups' => 3,
        ]);

        $this->otherKitchen = User::create([
            'first_name' => 'Other',
            'last_name' => 'Kitchen',
            'mobile' => '01770000002',
            'password' => 'password',
            'role_id' => $kitchenRole->id,
            'status' => 'active',
            'kitchen_tier' => 'silver',
            'allowed_open_groups' => 2,
        ]);

        $this->corporate = User::create([
            'first_name' => 'Corp',
            'last_name' => 'User',
            'mobile' => '01770000003',
            'password' => 'password',
            'role_id' => $corporateRole->id,
            'status' => 'active',
        ]);

        $this->menu = MenuItem::create([
            'name' => 'Thali',
            'price' => 250,
            'kitchen_commission' => 40,
        ]);

        $this->city = City::create(['name' => 'Dhaka']);
        $this->area = Area::create(['name' => 'Gulshan', 'city_id' => $this->city->id]);
    }

    public function test_kitchen_sees_only_complaints_for_assigned_orders(): void
    {
        $own = $this->assignedComplaint($this->kitchen->id, 'Too spicy');
        $other = $this->assignedComplaint($this->otherKitchen->id, 'Late delivery');

        Livewire::actingAs($this->kitchen)
            ->test(Complaints::class)
            ->assertSee('Too spicy')
            ->assertDontSee('Late delivery');

        Livewire::actingAs($this->kitchen)
            ->test(ComplaintShow::class, ['complaint' => $own])
            ->assertSee('Too spicy')
            ->assertStatus(200);

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.complaints.show', $other))
            ->assertForbidden();
    }

    public function test_complaint_disappears_after_release(): void
    {
        $complaint = $this->assignedComplaint($this->kitchen->id, 'Cold food');
        $group = $complaint->order->orderGroup;

        OrderGroupKitchenAssignment::release($group->fresh('orders'), $this->kitchen, OrderGroupEvent::TYPE_RELEASE, 'Cannot finish');

        Livewire::actingAs($this->kitchen)
            ->test(Complaints::class)
            ->assertDontSee('Cold food');
    }

    public function test_kitchen_can_save_profile_and_hours_but_not_tier(): void
    {
        $originalTier = $this->kitchen->kitchen_tier;
        $originalSlots = $this->kitchen->allowed_open_groups;

        Livewire::actingAs($this->kitchen)
            ->test(Profile::class)
            ->set('first_name', 'Updated')
            ->set('last_name', 'Kitchen')
            ->set('mobile', '01770000001')
            ->set('address', 'New Road')
            ->set('city_id', (string) $this->city->id)
            ->set('area_id', (string) $this->area->id)
            ->set('hours.0.is_closed', true)
            ->set('hours.1.opens_at', '09:00')
            ->set('hours.1.closes_at', '21:00')
            ->call('save')
            ->assertSet('statusMessage', 'Profile and hours saved.');

        $this->kitchen->refresh();
        $this->assertSame('Updated', $this->kitchen->first_name);
        $this->assertSame('New Road', $this->kitchen->address);
        $this->assertSame($originalTier, $this->kitchen->kitchen_tier);
        $this->assertSame($originalSlots, $this->kitchen->allowed_open_groups);

        $sunday = KitchenHour::query()
            ->where('user_id', $this->kitchen->id)
            ->where('day_of_week', 0)
            ->first();
        $this->assertTrue((bool) $sunday?->is_closed);

        $monday = KitchenHour::query()
            ->where('user_id', $this->kitchen->id)
            ->where('day_of_week', 1)
            ->first();
        $this->assertTrue(str_starts_with((string) $monday?->opens_at, '09:00'));
    }

    public function test_kitchen_permission_matrix_sync_and_revoke(): void
    {
        $role = Role::query()->where('name', 'kitchen')->firstOrFail();
        KitchenPermissions::syncKitchenRole($role);

        foreach (KitchenPermissions::all() as $name) {
            $this->assertTrue(
                $role->permissions()->where('name', $name)->exists(),
                "Missing permission {$name}"
            );
        }

        $this->assertFalse($role->permissions()->where('name', 'edit-menu')->exists());

        $this->assertTrue($this->kitchen->fresh()->hasPermission(KitchenPermissions::COMPLAINTS));

        $perm = Permission::query()->where('name', KitchenPermissions::COMPLAINTS)->firstOrFail();
        $role->permissions()->detach($perm->id);

        $this->assertFalse($this->kitchen->fresh()->hasPermission(KitchenPermissions::COMPLAINTS));

        $this->actingAs($this->kitchen)
            ->get(route('kitchen.complaints'))
            ->assertForbidden();
    }

    protected function assignedComplaint(int $kitchenId, string $message): OrderComplaint
    {
        $group = OrderGroup::create([
            'name' => 'GRP-'.uniqid(),
            'menu_id' => $this->menu->id,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'kitchen_id' => $kitchenId,
        ]);

        $order = Order::create([
            'user_id' => $this->corporate->id,
            'menu_item_id' => $this->menu->id,
            'quantity' => 1,
            'delivery_date' => now('Asia/Dhaka')->toDateString(),
            'delivery_time' => '12:00 PM',
            'total_amount' => 250,
            'address' => 'Office',
            'order_status' => 'processing',
            'payment_status' => 'pending',
            'created_by' => $this->corporate->id,
        ]);
        $group->orders()->attach($order->id);

        return OrderComplaint::create([
            'order_id' => $order->id,
            'parent_id' => null,
            'is_reply' => false,
            'category' => 'food_quality',
            'message' => $message,
            'created_by' => $this->corporate->id,
        ]);
    }
}
