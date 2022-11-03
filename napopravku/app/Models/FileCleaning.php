<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string path
 * @property \DateTime deleteAt
 * @property \DateTime createdAt
 * @property \DateTime updatedAt
 */
class FileCleaning extends Model
{
    use HasFactory;
}
