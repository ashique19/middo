<?php

namespace App\Livewire\Kitchen;

use App\Livewire\Concerns\ManagesProfilePayoutMethods;
use App\Models\Area;
use App\Models\City;
use App\Models\KitchenHour;
use App\Support\KitchenIdentity;
use App\Support\KitchenVerification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use ManagesProfilePayoutMethods;
    use WithFileUploads;

    public string $first_name = '';

    public string $last_name = '';

    public string $mobile = '';

    public ?string $email = null;

    public ?string $address = null;

    public string $nid_number = '';

    public $nid_front = null;

    public $nid_back = null;

    public $selfie = null;

    public ?string $city_id = null;

    public ?string $area_id = null;

    /** @var array<int, array{is_closed: bool, opens_at: string, closes_at: string}> */
    public array $hours = [];

    public string $statusMessage = '';

    public string $errorMessage = '';

    public Collection $cities;

    public Collection $areas;

    public function mount(): void
    {
        $this->cities = City::query()->orderBy('name')->get();
        $this->areas = collect();
        $this->loadUser();
        $this->loadHours();
    }

    public function updatedCityId($value): void
    {
        $this->refreshAreas($value);
        $this->area_id = null;
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $user = Auth::user();

        try {
            $identityErrors = KitchenIdentity::changeErrors($user, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'mobile' => $this->mobile,
                'address' => $this->address,
            ]);
            if ($identityErrors !== []) {
                foreach ($identityErrors as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }
                $this->errorMessage = 'Name, address, and phone can only be changed by an admin.';

                return;
            }

            $validated = $this->validate(array_merge([
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
                'city_id' => 'required|exists:cities,id',
                'area_id' => 'required|exists:areas,id',
                'hours' => 'required|array|size:7',
                'hours.*.is_closed' => 'boolean',
                'hours.*.opens_at' => 'nullable|date_format:H:i',
                'hours.*.closes_at' => 'nullable|date_format:H:i',
            ], $this->payoutMethodValidationRules()), $this->payoutMethodValidationMessages());

            foreach ($this->hours as $day => $row) {
                if (! empty($row['is_closed'])) {
                    continue;
                }
                if (empty($row['opens_at']) || empty($row['closes_at'])) {
                    throw new \RuntimeException(KitchenHour::DAYS[(int) $day].': set open and close times, or mark closed.');
                }
                if ($row['opens_at'] >= $row['closes_at']) {
                    throw new \RuntimeException(KitchenHour::DAYS[(int) $day].': open time must be before close time.');
                }
            }

            DB::transaction(function () use ($user, $validated) {
                $user->email = $validated['email'] ?: null;
                $user->city_id = $validated['city_id'];
                $user->area_id = $validated['area_id'];
                $this->savePayoutMethodsToUser($user);
                $user->save();

                foreach ($this->hours as $day => $row) {
                    $closed = (bool) ($row['is_closed'] ?? false);
                    KitchenHour::query()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'day_of_week' => (int) $day,
                        ],
                        [
                            'is_closed' => $closed,
                            'opens_at' => $closed ? null : ($row['opens_at'] ?: null),
                            'closes_at' => $closed ? null : ($row['closes_at'] ?: null),
                        ]
                    );
                }
            });

            $this->loadUser();
            $this->loadHours();
            $this->statusMessage = 'Profile and hours saved.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not save profile.';
        }
    }

    protected function loadUser(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->email = $user->email;
        $this->address = $user->address;
        $this->nid_number = $user->nid_number ?? '';
        $this->city_id = $user->city_id ? (string) $user->city_id : null;
        $this->area_id = $user->area_id ? (string) $user->area_id : null;
        $this->loadPayoutMethodsFromUser($user);

        if ($this->city_id) {
            $this->refreshAreas($this->city_id);
        } else {
            $this->areas = collect();
        }
    }

    protected function loadHours(): void
    {
        $existing = KitchenHour::query()
            ->where('user_id', Auth::id())
            ->get()
            ->keyBy('day_of_week');

        $this->hours = [];
        foreach (KitchenHour::DAYS as $day => $_label) {
            $row = $existing->get($day);
            if ($row) {
                $this->hours[$day] = [
                    'is_closed' => (bool) $row->is_closed,
                    'opens_at' => $row->opens_at ? substr((string) $row->opens_at, 0, 5) : '10:00',
                    'closes_at' => $row->closes_at ? substr((string) $row->closes_at, 0, 5) : '22:00',
                ];
            } else {
                $this->hours[$day] = [
                    'is_closed' => false,
                    'opens_at' => '10:00',
                    'closes_at' => '22:00',
                ];
            }
        }
    }

    public function saveVerification(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $this->validate([
                'nid_number' => KitchenVerification::rules()['nid_number'],
                'nid_front' => KitchenVerification::rules()['nid_front'],
                'nid_back' => KitchenVerification::rules()['nid_back'],
                'selfie' => KitchenVerification::rules()['selfie'],
            ], KitchenVerification::messages());

            KitchenVerification::apply($user, [
                'nid_number' => $this->nid_number,
            ], [
                'nid_front' => $this->nid_front,
                'nid_back' => $this->nid_back,
                'selfie' => $this->selfie,
            ]);

            $this->reset('nid_front', 'nid_back', 'selfie');
            $this->loadUser();
            $this->statusMessage = 'Verification photos saved.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage() ?: 'Could not save verification photos.';
        }
    }

    protected function refreshAreas($cityId): void
    {
        $this->areas = $cityId
            ? Area::query()->where('city_id', $cityId)->orderBy('name')->get()
            : collect();
    }

    public function render()
    {
        return view('livewire.kitchen.profile', [
            'dayLabels' => KitchenHour::DAYS,
            'tier' => Auth::user()?->kitchen_tier,
            'allowedOpenGroups' => Auth::user()?->allowed_open_groups,
            'status' => Auth::user()?->status,
        ])->layout('kitchen.layout.app', ['title' => 'Kitchen profile']);
    }
}
