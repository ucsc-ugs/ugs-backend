<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'logo',
        'status',
        'contact_email',
        'phone_number',
        'website',
        'address',
    ];

    /**
     * An organization belongs to an admin (user)
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * An organization can have many organizational admins
     */
    public function orgAdmins()
    {
        return $this->hasMany(OrgAdmin::class);
    }

    /**
     * An organization can have many exams
     */
    public function exams()
    {
        return $this->hasMany(Exam::class);
    }

    public function revenueTransactions()
    {
        return $this->hasMany(RevenueTransaction::class);
    }

    /**
     * An organization can have many locations
     */
    public function locations()
    {
        return $this->hasMany(Location::class);
    }
}
