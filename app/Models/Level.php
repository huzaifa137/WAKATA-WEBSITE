<?php

namespace App\Models;

use App\Models\Concerns\FlushesCatalogue;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use FlushesCatalogue;

    protected $fillable = ['code', 'label', 'short', 'book_code', 'book_label', 'full_name', 'sort_order'];
}
