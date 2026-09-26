<?php

namespace App\Models;

use App\Traits\HasEmployeeOperations;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Traits\BelongsToStore;
use Illuminate\Support\Facades\Auth;

class Accountant extends Authenticatable
{
    use SoftDeletes, BelongsToStore, HasEmployeeOperations;

    protected $fillable = [
        'user_id',
        'store_id',
        'employee_id',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'suspension_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => 'string',
        'role'   => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات (Relationships)
    |--------------------------------------------------------------------------
    */

    // علاقة المحاسب بالمدير/المالك
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // علاقة المحاسب بالموظف المرتبط به
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // علاقة المحاسب بالمتجر (تمت إضافتها للتأكيد بجانب التريت)
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    // سجل الأنشطة (Morph)
    public function logs()
    {
        return $this->morphMany(EmployeeLog::class, 'person');
    }

    /*
    |--------------------------------------------------------------------------
    | السكوبات (Scopes)
    |--------------------------------------------------------------------------
    */

    public function scopeForUserStores($query)
    {
        // تم إضافة فحص للتأكد من وجود مستخدم مسجل دخول لتجنب الخطأ
        if (auth()->check()) {
            return $query->whereIn('store_id', auth()->user()->stores->pluck('id'));
        }
        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Mutations
    |--------------------------------------------------------------------------
    */

    public function setPasswordAttribute($value)
    {
        if ($value && !str_starts_with($value, '$2y$')) {
            $this->attributes['password'] = bcrypt($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

}
