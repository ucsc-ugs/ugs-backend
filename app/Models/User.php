<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'student_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'id', 'id');
    }

    public function orgAdmin()
    {
        return $this->hasOne(OrgAdmin::class, 'user_id', 'id');
    }

    /**
     * Check if user is a super admin
     * Super admins are identified by having the 'super_admin' role
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Check if user is an organization admin
     */
    public function isOrgAdmin()
    {
        return $this->orgAdmin()->exists();
    }

    /**
     * Check if user is a student
     */
    public function isStudent()
    {
        return $this->student()->exists();
    }

    public function exams()
    {   
        return $this->belongsToMany(Exam::class, 'student_exams', 'student_id', 'exam_id')
                    ->withPivot('payment_id', 'status', 'attended', 'result','date')
                    ->withTimestamps();
    }
}
