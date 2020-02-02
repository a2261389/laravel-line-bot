<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantTag extends Model
{
    protected $guarded = [];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
