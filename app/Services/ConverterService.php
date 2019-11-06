<?php
/**
 * Created by PhpStorm.
 * User: 9nekr
 * Date: 06.11.2019
 * Time: 23:28
 */

namespace App\Services;


class ConverterService
{
    /**
     * @param $amount
     * @param $formCurrency
     * @param $toCurrency
     *
     * @return string
     * @throws \Exception
     */
    public function convert($amount, $formCurrency, $toCurrency)
    {
        if ($formCurrency === 'USD' && $toCurrency === 'RUB') {
            return bcmul(62, $amount, 4);
        }
        if ($formCurrency === 'RUB' && $toCurrency === 'USD') {
            return bcdiv($amount, 62, 4);
        }

        throw new \Exception('Неверная операция преобразования');
    }
}