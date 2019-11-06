<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConverterService;
use App\Wallet;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class WalletController extends Controller
{
    /**
     * @param Request $request
     *
     * @throws \Throwable
     */
    public function changeBalance(Request $request)
    {

        $params = $this->validate($request, [
            'wallet_id'        => 'required',
            'transaction_type' => ['required', Rule::in(['debit', 'credit'])],
            'amount'           => ['required', 'gt:0'],
            'currency'         => ['required', Rule::in(['RUB', 'USD'])],
        ]);

        DB::transaction(function () use ($params) {

            // Блокируем от изменения другими пользователями
            Wallet::where('id', '=', $params['wallet_id'])->lockForUpdate();

            // Получаем последние данные
            $wallet = Wallet::findOrFail($params['wallet_id']);
            $amount = $params['amount'];
            if ($wallet->currency !== $params['currency']) {
                $amount = (new ConverterService())->convert($amount, $params['currency'], $wallet->currency);
            }

            switch ($params['transaction_type']) {
                case 'debit':
                    $wallet->balance = bcadd($wallet->balance, $amount, 4);
                    $wallet->save();
                    break;

                case 'credit':
                    $newBalance = bcsub($wallet->balance, $amount, 4);
                    // Проверка отрицательного баланса
                    if (bccomp($newBalance, 0) < 0) {
                        throw new UnprocessableEntityHttpException('Невозможно списать больше денег, чем есть на счете');
                    }
                    $wallet->balance = $newBalance;
                    $wallet->save();
                    break;
            }
        });
    }

    /**
     * @param Request $request
     *
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function getBalance(Request $request)
    {
        $params = $this->validate($request, [
            'wallet_id' => 'required',
        ]);
        $wallet = Wallet::findOrFail($params['wallet_id']);

        return ['balance' => $wallet->balance];
    }
}
