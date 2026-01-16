<?php

namespace App\Models\Launcher;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpRule extends Model
{
    use HasFactory;

    protected $connection = 'rohan_manage';
    protected $table = 'ip_rules';
    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'rule_type',
        'max_clients',
        'reason',
    ];

    public function scopeBlacklist($query)
    {
        return $query->where('rule_type', 'blacklist');
    }

    public function scopeWhitelist($query)
    {
        return $query->where('rule_type', 'whitelist');
    }

    public function scopeForIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }
}
