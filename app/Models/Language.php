<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'is_rtl', 'is_default', 'is_active', 'show_in_navbar'];
}
