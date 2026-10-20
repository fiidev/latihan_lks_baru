<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingService extends Model
{
    protected $guarded = ['id'];

    public function shipments()
    {
        return $this->hasMany(Shipments::class);
    }
}
