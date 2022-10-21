<?php

namespace App\Exceptions;

use Exception;

class StockNotEnoughException extends Exception
{
    public function __construct($item, $options, $stock)
    {
        $production_number = $expiry_date = '';
        if (array_key_exists('production_number', $options)) {
            $production_number = ' (PID:'.$options["production_number"].')';
        }
        if (array_key_exists('expiry_date', $options)) {
            $expiry_date = strtotime($options["expiry_date"]);
            $expiry_date = date('Y-m-d', $expiry_date);
            $expiry_date = ' (E/D:'.$expiry_date.')';
        }

        parent::__construct('Stock '.$item->label. $production_number . $expiry_date . ' not enough! Current stock = '.$stock.' '.$item->unit, 422);
    }
}
