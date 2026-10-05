<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesCostUsedDate extends Model
{
    protected $fillable = [
        'store_id',
        'user_id',
        'business_date',
        'search_term',
        'sales_total',
        'products_cost',
        'labor_total',
        'labor_cost',
        'operations_count',
    ];

    protected $casts = [
        'business_date' => 'date',
        'sales_total' => 'float',
        'products_cost' => 'float',
        'labor_total' => 'float',
        'labor_cost' => 'float',
        'operations_count' => 'integer',
    ];
}
