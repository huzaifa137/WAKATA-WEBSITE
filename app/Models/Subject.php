<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogue;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use FlushesCatalogue;

    protected $fillable = ['name', 'color_1', 'color_2', 'color_3', 'sort_order'];
}
