<?php

namespace App\Models\Launcher;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerSetting extends Model
{
    use HasFactory;

    protected $connection = 'rohan_manage';
    protected $table = 'server_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null)
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
