<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipments extends Model
{
    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function shipping()
    {
        return $this->belongsTo(ShippingService::class);
    }

    public function payment()
    {
        return $this->hasMany(Payment::class);
    }

    public function histories()
    {
        return $this->hasMany(ShipmentStatusHistory::class);
    }

    public function delivery()
    {
        return $this->hasMany(DeliveryProof::class);
    }
}
