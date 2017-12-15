<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class HrisAllowanceType extends Model
{
    protected $fillable = ['tenant_id', 'code', 'name', 'type'];
}
