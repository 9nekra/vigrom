<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Wallet
 *
 * @property  int    id
 * @property  string currency
 * @property  string balance
 *
 * @package App
 * @mixin \Eloquent
 */
class Wallet extends Model
{
    protected $fillable
        = [
            'currency',
            'balance',
        ];
}
