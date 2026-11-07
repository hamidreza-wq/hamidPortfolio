<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentRevision extends Model
{
    protected $fillable = ['scope', 'subject_id', 'label', 'snapshot', 'user_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
