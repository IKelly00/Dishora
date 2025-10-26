<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DietarySpecification extends Model
{
  use HasFactory;

  protected $primaryKey = 'dietary_specification_id';
  protected $fillable = ['dietary_spec_name', 'dietary_category_id'];

  protected $table = 'dietary_specifications';
}
