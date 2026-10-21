<?php

namespace App\Http\Controllers;

use App\Models\Shipments;
use App\Models\ShippingService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentsController extends Controller
{
    public function store(Request $req)
    {
        $v = $req->validate([
            'shipping_service_id' => 'required|integer',
            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|string',
            'sender_address' => 'required|string',
            'recipient_name' => 'required|string',
            'recipient_phone' => 'required|string',
            'recipient_address' => 'required|string',
            'package_description' => 'required|string',
            'weight' => 'required|numeric|between:0.01,999999.99',
            'notes' => 'nullable|string',
        ]);

        $service = ShippingService::find($v['shipping_service_id']);
        if (! $service || ! $service->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping service unavailable.',
                'errors' => [],
            ], 404);
        }

        $v['customer_id'] = $req->user()->id;
        $v['tracking_number'] = 'LOG-'.date('Y').'-'.mt_rand(10000, 99999);
        $v['status'] = 'pending';

        return DB::transaction(function () use ($v) {
            $ship = Shipments::create($v);

            $ship->histories()->create([
                'changed_by' => auth()->id(),
                'new_status' => 'pending',
                'notes' => 'Shipment created by customer.',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipment created successfully.',
                'data' => $ship,
            ], 201);
        });
    }

    public function me(Request $request)
    {
        $validate = $request->validate([
            'status' => 'nullable|in:pending,confirmed,assigned,picked_up,in_transit,out_for_delivery,failed_delivery,delivered,cancelled',
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $shipments = $request->user()->shipments()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->select('id', 'tracking_number', 'status', 'shipping_fee')
            ->paginate($request->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Shipments retrieved successfully.',
            'data' => $shipments->items(),
            'meta' => [
                'pagination' => [
                    'page' => $shipments->currentPage(),
                    'per_page' => $shipments->perPage(),
                    'total' => $shipments->total(),
                    'last_page' => $shipments->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $req, $id)
    {
        $ship = Shipments::find($id);

        if (! $ship) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
                'errors' => [],
            ], 404);
        }

        $user = $req->user();
        if (
            ($user->role === 'customer' && $ship->customer_id !== $user->id) ||
            ($user->role === 'courier' && $ship->courier_id !== $user->id)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Shipment not found.',
                'errors' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment retrieved successfully.',
            'data' => $ship,
        ]);
    }

    public function cancel(Request $req, $id)
    {
        $ship = $req->user()->shipments()->findOrFail($id);

        if (! in_array($ship->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'This shipment can no longer be cancelled.',
                'errors' => [
                    [
                        'field' => 'status',
                        'message' => 'Only eligible early-state shipments can be cancelled.',
                    ],
                ],
            ], 409);
        }

        return DB::transaction(function () use ($ship, $req) {
            $old = $ship->status;
            $ship->update(['status' => 'cancelled']);

            $ship->histories()->create([
                'changed_by' => auth()->id(),
                'old_status' => $old,
                'new_status' => 'cancelled',
                'notes' => $req->input('reason', 'Shipment cancelled by customer.'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipment cancelled successfully.',
                'data' => $ship,
            ]);
        });
    }

    public function staff(Request $req)
    {
        $req->validate([
            'status' => 'nullable|in:pending,confirmed,assigned,picked_up,in_transit,out_for_delivery,failed_delivery,delivered,cancelled',
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $shipments = Shipments::with(['customer', 'shipping', 'courier'])
            ->when($req->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($req->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Operational shipments retrieved successfully.',
            'data' => $shipments->items(),
            'meta' => [
                'pagination' => [
                    'page' => $shipments->currentPage(),
                    'per_page' => $shipments->perPage(),
                    'total' => $shipments->total(),
                    'last_page' => $shipments->lastPage(),
                ],
            ],
        ]);
    }

    public function confirm(Request $req, $id)
    {
        $v = $req->validate(['shipping_fee' => 'required|numeric|min:0.01']);

        $ship = Shipments::find($id);
        if (! $ship) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        if ($ship->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Shipment status cannot be confirmed from its current state.',
                'errors' => [['field' => 'status', 'message' => 'Only pending shipments can be confirmed.']],
            ], 409);
        }

        return DB::transaction(function () use ($ship, $v) {
            $ship->update([
                'shipping_fee' => $v['shipping_fee'],
                'status' => 'confirmed',
            ]);

            $ship->histories()->create([
                'changed_by' => auth()->id(),
                'old_status' => 'pending',
                'new_status' => 'confirmed',
                'notes' => 'Shipping fee confirmed by staff.',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipment confirmed successfully.',
                'data' => $ship,
            ]);
        });
    }

    public function assign(Request $req, $id)
    {
        $v = $req->validate(['courier_id' => 'required|integer|exists:users,id']);

        $ship = Shipments::with('payment')->find($id);
        if (! $ship) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        $courier = User::where('id', $v['courier_id'])->where('role', 'courier')->first();
        if (! $courier) {
            return response()->json([
                'success' => false,
                'message' => 'The selected user is not an eligible courier.',
                'errors' => [['field' => 'courier_id', 'message' => 'User must have courier role.']],
            ], 422);
        }

        if ($ship->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Shipment cannot be assigned in its current state.',
                'errors' => [['field' => 'status', 'message' => 'Only confirmed shipments can be assigned to a courier.']],
            ], 409);
        }

        if (! $ship->payment()->where('status', 'completed')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment must be completed before assigning courier.',
                'errors' => [['field' => 'payment', 'message' => 'Shipment has no verified completed payment.']],
            ], 409);
        }

        return DB::transaction(function () use ($ship, $courier) {
            $ship->update([
                'courier_id' => $courier->id,
                'status' => 'assigned',
            ]);

            $ship->histories()->create([
                'changed_by' => auth()->id(),
                'old_status' => 'confirmed',
                'new_status' => 'assigned',
                'notes' => 'Courier assigned by admin.',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Courier assigned successfully.',
                'data' => $ship,
            ]);
        });
    }

    public function tracking(Request $req, $id)
    {
        $ship = Shipments::with('histories')->find($id);
        if (! $ship) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        if ($req->user()->role === 'customer' && $ship->customer_id !== $req->user()->id) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tracking history retrieved successfully.',
            'data' => [
                'tracking_number' => $ship->tracking_number,
                'status' => $ship->status,
                'histories' => $ship->histories,
            ],
        ]);
    }

    public function track(Request $req, $tracking_number)
    {
        $ship = Shipments::with('histories')->where('tracking_number', $tracking_number)->first();
        if (! $ship) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        if ($req->user()->role === 'customer' && $ship->customer_id !== $req->user()->id) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shipment tracked successfully.',
            'data' => [
                'tracking_number' => $ship->tracking_number,
                'status' => $ship->status,
                'histories' => $ship->histories,
            ],
        ]);
    }

    public function admin(Request $req)
    {
        $req->validate([
            'status' => 'nullable|in:pending,confirmed,assigned,picked_up,in_transit,out_for_delivery,failed_delivery,delivered,cancelled',
            'courier_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $shipments = Shipments::with(['customer', 'shipping', 'courier', 'payment'])
            ->when($req->status, fn ($q, $status) => $q->where('status', $status))
            ->when($req->courier_id, fn ($q, $id) => $q->where('courier_id', $id))
            ->latest()
            ->paginate($req->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'All shipments retrieved successfully.',
            'data' => $shipments->items(),
            'meta' => [
                'pagination' => [
                    'page' => $shipments->currentPage(),
                    'per_page' => $shipments->perPage(),
                    'total' => $shipments->total(),
                    'last_page' => $shipments->lastPage(),
                ],
            ],
        ]);
    }
}
