<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A site-wide, admin-editable value (non-secret). Read through App\Support\Settings.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
