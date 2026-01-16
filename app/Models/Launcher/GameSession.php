<?php

namespace App\Models\Launcher;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    use HasFactory;

    protected $connection = 'rohan_manage';
    protected $table = 'game_sessions';
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'ip_address',
        'hwid',
        'client_hash',
        'launched_at',
        'last_heartbeat',
        'status',
    ];

    protected $casts = [
        'launched_at' => 'datetime',
        'last_heartbeat' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }
}
