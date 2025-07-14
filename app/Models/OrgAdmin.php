<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrgAdmin extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'name'
    ];
    
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
