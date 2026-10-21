<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function pay(Request $req, $id)
    {
        $ship = $req->user()->shipments()->findOrFail($id);

        if ($ship->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Payment can only be submitted for confirmed shipments.',
                'errors' => [['field' => 'status', 'message' => 'Shipping fee is not confirmed yet.']],
            ], 409);
        }

        $v = $req->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:50',
            'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($req->hasFile('payment_proof')) {
            $v['payment_proof'] = $req->file('payment_proof')->store('payment_proofs', 'local');
        }

        $v['shipment_id'] = $ship->id;
        $v['status'] = 'pending';
        $v['paid_at'] = now();

        $payment = Payment::create($v);

        return response()->json([
            'success' => true,
            'message' => 'Payment submitted successfully.',
            'data' => $payment,
        ], 201);
    }

    public function me(Request $req)
    {
        $req->validate([
            'per_page' => 'nullable|integer|between:1,100',
        ]);

        $payments = Payment::whereHas('shipment', fn ($q) => $q->where('customer_id', $req->user()->id))
            ->with('shipment')
            ->latest()
            ->paginate($req->query('per_page', 10));

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully.',
            'data' => $payments->items(),
            'meta' => [
                'pagination' => [
                    'page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                ],
            ],
        ]);
    }

    public function verify(Request $req, $id)
    {
        $v = $req->validate([
            'status' => 'required|in:completed,rejected',
            'notes' => 'nullable|string|max:500',
        ]);

        $payment = Payment::with('shipment')->find($id);
        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.', 'errors' => []], 404);
        }

        if ($payment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Payment status cannot be changed from its current state.',
                'errors' => [],
            ], 409);
        }

        if ($v['status'] === 'completed') {
            if ((float) $payment->amount !== (float) $payment->shipment->shipping_fee) {
                return response()->json([
                    'success' => false,
                    'message' => 'The payment cannot be completed.',
                    'errors' => [['field' => 'amount', 'message' => 'Must equal the confirmed shipping fee.']],
                ], 422);
            }
        }

        return DB::transaction(function () use ($payment, $v) {
            $payment->update([
                'status' => $v['status'],
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully.',
                'data' => $payment,
            ]);
        });
    }

    public function proof(Request $req, $id)
    {
        $payment = Payment::with('shipment')->find($id);
        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found.', 'errors' => []], 404);
        }

        $user = $req->user();
        if ($user->role === 'customer' && $payment->shipment->customer_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden.', 'errors' => []], 403);
        }

        if (! $payment->payment_proof || ! Storage::disk('local')->exists($payment->payment_proof)) {
            return response()->json(['success' => false, 'message' => 'Payment proof not found.', 'errors' => []], 404);
        }

        return Storage::disk('local')->response($payment->payment_proof);
    }
}
