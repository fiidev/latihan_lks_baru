<?php

namespace App\Http\Controllers;

use App\Models\ShippingService;
use Illuminate\Http\Request;

class ShippingServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $req)
    {
        $perPage = $req->query('per_page', 5);
        $services = ShippingService::paginate($perPage);

        return response()->json([
            'success' => true,
            'messages' => 'Shipping service updated successfully',
            'data' => $services->items(),
            'meta' => [
                'pagination' => [
                    'page' => $services->currentPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $req)
    {
        $v = $req->validate([
            'service_name' => 'required|string',
            'description' => 'sometimes|string',
            'is_active' => 'required|boolean',
        ]);

        $services = ShippingService::create($v);

        return response()->json([
            'success' => true,
            'messages' => 'Shipping service created successfully',
            'data' => $services,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $req, string $id)
    {

        $services = ShippingService::findOrFail($id);

        $v = $req->validate([
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        return response()->json([
            'success' => true,
            'messages' => 'Shipping service updated successfully',
            'data' => $services,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(string $id)
    {

        $services = ShippingService::findOrFail($id);

        return response()->json([
            'success' => true,
            'messages' => 'Shipping service created successfully',
            'data' => $services,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ShippingService $shippingService)
    {
        //
    }
}
