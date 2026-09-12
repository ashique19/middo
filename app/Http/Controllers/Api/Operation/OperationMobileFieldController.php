<?php

namespace App\Http\Controllers\Api\Operation;

use App\Http\Controllers\Controller;
use App\Models\CashHandover;
use App\Models\CustomRun;
use App\Models\KitchenBoxRequest;
use App\Models\MiddoBox;
use App\Models\Order;
use App\Models\OrderComplaint;
use App\Models\OrderGroup;
use App\Models\User;
use App\Support\CashHandoverActions;
use App\Support\DeliveryApiPresenter;
use App\Support\KitchenBoxRequestFlow;
use App\Support\KitchenCapacity;
use App\Support\OperationApiPresenter;
use App\Support\OperationMobileActions;
use App\Support\OpsAssignRider;
use App\Support\OpsBoxCustody;
use App\Support\OpsDayChecklist;
use App\Support\OpsRiderBoard;
use App\Support\OpsRiderMidRunReassign;
use App\Support\OpsSlaBoard;
use App\Support\OrderOpsForce;
use App\Support\StaffAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationMobileFieldController extends Controller
{
    public function boxes(Request $request): JsonResponse
    {
        $custody = (string) $request->query('custody', 'warehouse');
        $search = trim((string) $request->query('search', ''));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $query = match ($custody) {
            'to_kitchen' => OpsBoxCustody::toKitchenQuery(),
            'returns' => OpsBoxCustody::returnsQuery(),
            'needs_kitchen_to_ops' => OpsBoxCustody::unassignedKitchenToOpsQuery(),
            'needs_empty' => OpsBoxCustody::unassignedEmptyPickupQuery(),
            'all' => MiddoBox::query()->where('asset_status', '!=', 'retired'),
            default => OpsBoxCustody::warehouseFreeQuery(),
        };

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('qr_code_id', 'like', "%{$search}%");
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
            });
        }

        $page = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'summary' => OpsBoxCustody::summary(),
            'custody' => $custody,
            'boxes' => collect($page->items())
                ->map(fn (MiddoBox $box) => OperationApiPresenter::box($box))
                ->values()
                ->all(),
            'meta' => OperationApiPresenter::paginationMeta($page),
        ]);
    }

    public function boxRequests(): JsonResponse
    {
        $requests = KitchenBoxRequest::query()
            ->open()
            ->with([
                'kitchen:id,first_name,last_name,mobile',
                'requestedBy:id,first_name,last_name',
                'requestBoxes.rider:id,first_name,last_name',
            ])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'requests' => $requests
                ->map(fn (KitchenBoxRequest $request) => OperationApiPresenter::boxRequest($request))
                ->values()
                ->all(),
        ]);
    }

    public function assignBoxRequest(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'box_ids' => ['required', 'array', 'min:1'],
            'box_ids.*' => ['integer', 'exists:middo_boxes,id'],
            'rider_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $boxRequest = KitchenBoxRequest::query()->findOrFail($id);
        if (! $boxRequest->isOpen()) {
            return response()->json(['message' => 'That box request is no longer open.'], 422);
        }

        try {
            $result = KitchenBoxRequestFlow::stageForPickup(
                $data['box_ids'],
                (int) $boxRequest->kitchen_id,
                (int) $data['rider_id'],
                (int) $request->user()->id,
                $boxRequest->id,
            );
            StaffAlerts::notifyOpsToKitchenBoxes(
                $result['rider'],
                $result['kitchen'],
                $result['boxes'],
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not stage boxes.'], 422);
        }

        return response()->json([
            'message' => "Staged {$result['count']} box(es) for pickup.",
            'count' => $result['count'],
            'request_id' => $result['request_id'],
            'boxes' => $result['boxes']
                ->map(fn (MiddoBox $box) => OperationApiPresenter::box($box))
                ->values()
                ->all(),
        ]);
    }

    public function reassignBox(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(['staged_request', 'kitchen_to_ops', 'empty_box'])],
            'rider_id' => ['required', 'integer', 'exists:users,id'],
            'request_id' => ['nullable', 'integer', 'exists:kitchen_box_requests,id'],
            'kitchen_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $actor = $request->user();
        $rider = User::query()->findOrFail((int) $data['rider_id']);

        try {
            if ($data['kind'] === 'staged_request') {
                $requestId = (int) ($data['request_id'] ?? 0);
                if ($requestId < 1) {
                    return response()->json(['message' => 'request_id is required for staged_request reassign.'], 422);
                }

                $updated = KitchenBoxRequestFlow::reassignStagedRider(
                    $requestId,
                    (int) $rider->id,
                    (int) $actor->id,
                );

                return response()->json([
                    'message' => $updated > 0
                        ? "Updated pickup rider on {$updated} staged box(es)."
                        : 'Rider was already assigned to this run.',
                    'updated' => $updated,
                ]);
            }

            $box = MiddoBox::query()->findOrFail($id);

            if ($data['kind'] === 'kitchen_to_ops') {
                $box = OpsAssignRider::kitchenToOps((int) $box->id, $rider, $actor);
            } else {
                $box = OpsAssignRider::emptyBoxPickup(
                    $box,
                    $rider,
                    $actor,
                    isset($data['kitchen_id']) ? (int) $data['kitchen_id'] : null,
                );
            }

            return response()->json([
                'message' => 'Rider assigned.',
                'box' => OperationApiPresenter::box($box),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not reassign rider.'], 422);
        }
    }

    public function ackBoxReturn(Request $request, int $id): JsonResponse
    {
        try {
            $box = OpsBoxCustody::ackReturn(
                MiddoBox::query()->findOrFail($id),
                (int) $request->user()->id,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not acknowledge return.'], 422);
        }

        return response()->json([
            'message' => 'Return acknowledged.',
            'box' => OperationApiPresenter::box($box),
        ]);
    }

    public function ridersBoard(): JsonResponse
    {
        return response()->json([
            'counts' => OpsRiderBoard::counts(),
            'riders' => OpsRiderBoard::riders()->values()->all(),
            'awaiting_accept' => OpsRiderBoard::awaitingAccept()->values()->all(),
            'on_the_way' => OpsRiderBoard::onTheWay()->values()->all(),
            'box_custody' => OpsRiderBoard::boxCustody()->values()->all(),
            'custom_runs' => OpsRiderBoard::customRunsActive()->values()->all(),
        ]);
    }

    public function assignLunchRider(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $order = OpsAssignRider::lunch(
                Order::query()->findOrFail($id),
                User::query()->findOrFail((int) $data['rider_id']),
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not assign rider.'], 422);
        }

        return response()->json([
            'message' => 'Lunch rider assigned.',
            'order' => OperationApiPresenter::orderSummary($order),
        ]);
    }

    public function reassignLunchRider(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'rider_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = OpsRiderMidRunReassign::reassign(
                Order::query()->findOrFail($id),
                User::query()->findOrFail((int) $data['rider_id']),
                $request->user(),
                $data['reason'] ?? null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not reassign rider.'], 422);
        }

        return response()->json([
            'message' => 'Lunch rider reassigned.',
            'order' => OperationApiPresenter::orderSummary($order),
        ]);
    }

    public function cancelCustomRun(Request $request, int $id): JsonResponse
    {
        try {
            $run = OperationMobileActions::cancelCustomRun(
                CustomRun::query()->findOrFail($id),
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not cancel custom run.'], 422);
        }

        return response()->json([
            'message' => 'Custom run cancelled.',
            'custom_run' => DeliveryApiPresenter::customRun($run),
        ]);
    }

    public function createCustomRun(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_label' => ['required', 'string', 'max:120'],
            'to_label' => ['required', 'string', 'max:120'],
            'rider_id' => ['required', 'integer', 'exists:users,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'commission_amount' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $run = OperationMobileActions::createCustomRun($data, $request->user());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not create custom run.'], 422);
        }

        return response()->json([
            'message' => 'Custom run created.',
            'custom_run' => DeliveryApiPresenter::customRun($run),
        ], 201);
    }

    public function cashHandovers(Request $request): JsonResponse
    {
        $status = $request->query('status', CashHandover::STATUS_PENDING);

        $query = CashHandover::query()
            ->with(['rider:id,first_name,last_name,mobile', 'items.order.menuItem'])
            ->where('target', CashHandover::TARGET_MIDDO)
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $rows = $query->limit(50)->get();

        return response()->json([
            'handovers' => $rows
                ->map(fn (CashHandover $handover) => OperationApiPresenter::handover($handover))
                ->values()
                ->all(),
        ]);
    }

    public function acceptCashHandover(Request $request, int $id): JsonResponse
    {
        try {
            $handover = CashHandoverActions::acceptMiddo(
                CashHandover::query()->findOrFail($id),
                (int) $request->user()->id,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not accept handover.'], 422);
        }

        return response()->json([
            'message' => 'Due handover accepted into Middo cash.',
            'handover' => OperationApiPresenter::handover($handover),
        ]);
    }

    public function rejectCashHandover(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $handover = CashHandoverActions::proposeRejectMiddo(
                CashHandover::query()->findOrFail($id),
                (int) $request->user()->id,
                $data['reason'] ?? null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not propose reject.'], 422);
        }

        return response()->json([
            'message' => 'Reject proposed. Accounts must confirm.',
            'handover' => OperationApiPresenter::handover($handover),
        ]);
    }

    public function slaBoard(): JsonResponse
    {
        return response()->json([
            'counts' => OpsSlaBoard::counts(),
            'unassigned_groups' => OpsSlaBoard::unassignedGroups()->values()->all(),
            'late_to_pack' => OpsSlaBoard::lateToPack()->values()->all(),
            'kitchen_hints' => OpsSlaBoard::kitchenCapacityHints(),
            'kitchens' => User::query()
                ->whereHas('role', fn ($q) => $q->where('name', 'kitchen'))
                ->where('status', 'active')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'mobile'])
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'mobile' => $u->mobile,
                    'remaining_slots' => KitchenCapacity::remainingSlots($u),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function assignKitchen(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'kitchen_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $group = OperationMobileActions::assignKitchen(
                OrderGroup::with(['kitchen', 'orders', 'menuItem'])->findOrFail($id),
                User::query()->findOrFail((int) $data['kitchen_id']),
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not assign kitchen.'], 422);
        }

        return response()->json([
            'message' => 'Kitchen assigned.',
            'group' => OperationApiPresenter::orderGroup($group),
        ]);
    }

    public function bulkAssignKitchen(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'exists:order_groups,id'],
            'kitchen_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $result = OperationMobileActions::bulkAssignKitchen(
                array_map('intval', $data['group_ids']),
                User::query()->findOrFail((int) $data['kitchen_id']),
                $request->user(),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not bulk-assign kitchen.'], 422);
        }

        return response()->json($result);
    }

    public function complaints(Request $request): JsonResponse
    {
        $status = $request->query('status', OrderComplaint::STATUS_OPEN);

        $query = OrderComplaint::query()
            ->whereNull('parent_id')
            ->with(['order.menuItem', 'order.user:id,first_name,last_name,mobile,company_name'])
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $rows = $query->limit(50)->get();

        return response()->json([
            'complaints' => $rows
                ->map(fn (OrderComplaint $complaint) => OperationApiPresenter::complaint($complaint))
                ->values()
                ->all(),
        ]);
    }

    public function showComplaint(int $id): JsonResponse
    {
        $complaint = OrderComplaint::query()->findOrFail($id);
        $root = $complaint->parent_id
            ? OrderComplaint::query()->findOrFail($complaint->parent_id)
            : $complaint;

        $root->load([
            'order.menuItem',
            'order.user:id,first_name,last_name,mobile,company_name',
            'order.orderGroup.kitchen:id,first_name,last_name',
        ]);

        return response()->json([
            'complaint' => OperationApiPresenter::complaint($root, withThread: true),
        ]);
    }

    public function replyComplaint(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $complaint = OrderComplaint::query()->findOrFail($id);
        $root = $complaint->parent_id
            ? OrderComplaint::query()->findOrFail($complaint->parent_id)
            : $complaint;

        if ($root->isResolved()) {
            return response()->json(['message' => 'This complaint is marked complete. Replies are closed.'], 422);
        }

        OrderComplaint::create([
            'order_id' => $root->order_id,
            'parent_id' => $root->id,
            'is_reply' => true,
            'status' => OrderComplaint::STATUS_OPEN,
            'category' => $root->category,
            'message' => $data['message'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $root->refresh();

        return response()->json([
            'message' => 'Reply posted.',
            'complaint' => OperationApiPresenter::complaint($root, withThread: true),
        ]);
    }

    public function completeComplaint(Request $request, int $id): JsonResponse
    {
        $complaint = OrderComplaint::query()->findOrFail($id);
        $root = $complaint->parent_id
            ? OrderComplaint::query()->findOrFail($complaint->parent_id)
            : $complaint;

        if ($root->isResolved()) {
            return response()->json([
                'message' => 'Already marked complete.',
                'complaint' => OperationApiPresenter::complaint($root, withThread: true),
            ]);
        }

        $root->markResolved((int) $request->user()->id);
        $root->refresh();

        return response()->json([
            'message' => 'Complaint marked complete.',
            'complaint' => OperationApiPresenter::complaint($root, withThread: true),
        ]);
    }

    public function opsDay(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $data['date'] ?? now('Asia/Dhaka')->toDateString();

        return response()->json(OpsDayChecklist::forDate($date));
    }

    public function searchOrders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
            'package' => ['nullable', 'string', Rule::in(['all', 'package', 'alacarte'])],
        ]);

        $term = trim($data['q']);
        $package = $data['package'] ?? 'all';

        $orders = Order::query()
            ->with([
                'menuItem:id,name',
                'user:id,first_name,last_name,mobile,company_name',
                'packageSubscription.package:id,name',
            ])
            ->where(function ($query) use ($term) {
                if (is_numeric($term)) {
                    $query->where('id', (int) $term);
                }

                $query->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('delivery_date', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('mobile', 'like', "%{$term}%")
                            ->orWhere('company_name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('menuItem', function ($menuQuery) use ($term) {
                        $menuQuery->where('name', 'like', "%{$term}%");
                    });
            })
            ->when($package === 'package', fn ($q) => $q->whereNotNull('package_subscription_id'))
            ->when($package === 'alacarte', fn ($q) => $q->whereNull('package_subscription_id'))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'orders' => $orders
                ->map(fn (Order $order) => OperationApiPresenter::orderSummary($order))
                ->values()
                ->all(),
        ]);
    }

    public function showOrder(int $id): JsonResponse
    {
        $order = Order::query()
            ->with([
                'menuItem',
                'user:id,first_name,last_name,mobile,company_name',
                'deliveryRider:id,first_name,last_name,mobile',
                'orderGroup.kitchen:id,first_name,last_name,mobile',
                'area:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'order' => OperationApiPresenter::orderDetail($order),
        ]);
    }

    public function forceCancelOrder(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = OrderOpsForce::cancelBeforePacked(
                Order::query()->findOrFail($id),
                $request->user(),
                $data['reason'] ?? null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not cancel order.'], 422);
        }

        return response()->json([
            'message' => 'Order cancelled.',
            'refunded_amount' => $result['refunded_amount'] ?? 0,
            'order' => OperationApiPresenter::orderSummary($result['order']),
        ]);
    }

    public function releaseRider(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = OrderOpsForce::releaseRiderToPacked(
                Order::query()->findOrFail($id),
                $request->user(),
                $data['reason'] ?? null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'Could not release rider.'], 422);
        }

        return response()->json([
            'message' => 'Rider released; order back to packed.',
            'order' => OperationApiPresenter::orderSummary($order),
        ]);
    }
}
