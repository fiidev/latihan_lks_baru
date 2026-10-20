<?php

namespace App\Http\Controllers;

use App\Models\Shipments;
use Illuminate\Http\Request;

class ShipmentsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $v = $req->validate([
            'shipping_service_id' => 'required|exists:shipping_services,id,is_active,1',
            'sender_name' => 'required|string|max:255',
            'sender_phone' => 'required|digits_between:10,13',
            'sender_address' => 'required|string',
            'recipient_name' => 'required|string',
            'recipient_phone' => 'required|digits_between:10,13',
            'recipient_address' => 'required|string',
            'package_description' => 'required|string',
            'weight' => 'required|numeric|between:0.01,999999.99',
            'notes' => 'required|string',
        ]);

        $v['customer_id'] = $req->user()->id;
        $v['tracking_number'] = 'LOG-'.date('Y').'-'.mt_rand(10000, 99999);

        $ship = Shipments::create($v);

        return response()->json([
            'success' => true,
            'message' => 'Shipment created successfully.',
            'data' => [
                'id' => $ship->id,
                'tracking_number' => $ship->tracking_number,
                'shipping_service_id' => (int) $ship->shipping_service_id,
                'weight' => (float) $ship->weight,
                'shipping_fee' => $ship->shipping_fee,
                'status' => $ship->status ?? 'pending',
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Shipments $shipments)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Shipments $shipments)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function me(Request $request)
    {
        $validate = $request->validate([
            'status' => 'nullable|in:pending,confirmed,assigned,picked_up,in_transit,out_for_delivery,failed_delivery,delivered,cancelled',
        ]);

        $shipments = $request->user()->shipments()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Shipments retrieved successfully.',
            'data' => array_map(fn ($shipment) => [
                'id' => $shipment->id,
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status,
                'shipping_fee' => $shipment->shipping_fee,
            ], $shipments->items()),
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
