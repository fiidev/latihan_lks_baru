<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProof;
use App\Models\Shipments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierController extends Controller
{
    public function me(Request $req)
    {
        $req->validate([
            'status' => 'nullable|in:pending,confirmed,assigned,picked_up,in_transit,out_for_delivery,failed_delivery,delivered,cancelled',
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $shipments = Shipments::where('courier_id', $req->user()->id)
            ->when($req->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($req->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Assigned shipments retrieved successfully.',
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

    public function updateStatus(Request $req, $id)
    {
        $v = $req->validate([
            'status' => 'required|in:picked_up,in_transit,out_for_delivery',
            'location' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $ship = $req->user()->assignedShip()->findOrFail($id);

        $allowed = [
            'assigned' => ['picked_up'],
            'picked_up' => ['in_transit'],
            'in_transit' => ['out_for_delivery'],
            'failed_delivery' => ['out_for_delivery'],
        ];

        $current = $ship->status;
        $next = $v['status'];

        if (! isset($allowed[$current]) || ! in_array($next, $allowed[$current])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status transition.',
                'errors' => [['field' => 'status', 'message' => "Cannot transition status from '{$current}' to '{$next}'."]],
            ], 422);
        }

        return DB::transaction(function () use ($ship, $current, $next, $v) {
            $ship->update(['status' => $next]);

            $ship->histories()->create([
                'changed_by' => auth()->id(),
                'old_status' => $current,
                'new_status' => $next,
                'location' => $v['location'] ?? null,
                'notes' => $v['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shipment status updated successfully.',
                'data' => $ship,
            ]);
        });
    }

    public function proof(Request $req, $id)
    {
        $ship = $req->user()->assignedShip()->findOrFail($id);

        if ($ship->status !== 'out_for_delivery') {
            return response()->json([
                'success' => false,
                'message' => 'Delivery proof can only be recorded when shipment is out for delivery.',
                'errors' => [['field' => 'status', 'message' => 'Shipment is not currently out for delivery.']],
            ], 409);
        }

        $v = $req->validate([
            'delivery_result' => 'required|in:delivered,failed',
            'recipient_name' => 'required_if:delivery_result,delivered|nullable|string',
            'delivery_notes' => 'nullable|string',
            'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($req->hasFile('proof_file')) {
            $v['proof_file'] = $req->file('proof_file')->store('delivery_proofs', 'local');
        }

        return DB::transaction(function () use ($ship, $v) {
            $targetStatus = $v['delivery_result'] === 'delivered' ? 'delivered' : 'failed_delivery';
            $oldStatus = $ship->status;

            $v['shipment_id'] = $ship->id;
            $v['courier_id'] = auth()->id();
            $proof = DeliveryProof::create($v);

            $ship->update(['status' => $targetStatus]);

            $ship->histories()->create([
                'changed_by' => auth()->id(),
                'old_status' => $oldStatus,
                'new_status' => $targetStatus,
                'notes' => $v['delivery_notes'] ?? ($targetStatus === 'delivered' ? 'Package delivered.' : 'Delivery attempt failed.'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery result recorded successfully.',
                'data' => [
                    'id' => $proof->id,
                    'shipment_id' => $ship->id,
                    'delivery_result' => $proof->delivery_result,
                    'shipment_status' => $targetStatus,
                ],
            ], 201);
        });
    }

    public function proofs(Request $req, $id)
    {
        $ship = Shipments::with('delivery')->find($id);
        if (! $ship) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        $user = $req->user();
        if ($user->role === 'customer' && $ship->customer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        if ($user->role === 'courier' && $ship->courier_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Shipment not found.', 'errors' => []], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Delivery proofs retrieved successfully.',
            'data' => $ship->delivery,
        ]);
    }
}
