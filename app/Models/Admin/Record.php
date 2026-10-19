<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

abstract class Record extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $guarded = ['id'];

    public function getLabelAttribute(): string
    {
        return (string) ($this->getAttribute('name_'.app()->getLocale()) ?: $this->getAttribute('name_en') ?: $this->getAttribute('number') ?: $this->getAttribute('code') ?: $this->id);
    }
}
