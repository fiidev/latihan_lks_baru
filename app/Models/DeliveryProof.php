<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryProof extends Model
{
    protected $guarded = ['id'];

    public function shipment()
    {
        return $this->belongsTo(Shipments::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
