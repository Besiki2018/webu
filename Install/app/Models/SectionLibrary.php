<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionLibrary extends Model
{
    use HasFactory;

    protected $table = 'sections_library';

    protected $fillable = [
        'key',
        'category',
        'schema_json',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'schema_json' => 'array',
            'enabled' => 'boolean',
        ];
    }
}

