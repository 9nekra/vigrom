<?php

namespace Tests\Feature;

use App\Services\ConverterService;
use App\Wallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Можем добавлять деньги на существующий бумажник
     */
    public function testCanAddMoneyToExistsWallet()
    {
        $this->withoutExceptionHandling();

        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 0]);

        $params = [
            'wallet_id'        => $wallet->id,
            'transaction_type' => 'debit',
            'amount'           => 10,
            'currency'         => $wallet->currency,
        ];

        $this->json('post', '/api/wallets/change_balance', $params)->assertOk();

        $wallet->refresh();

        $this->assertEquals(10, $wallet->balance);
    }

    /**
     * Можем снимать деньги с существующего бумажника
     */
    public function testUserCanSubMoneyFromExistsWallet()
    {
        $this->withoutExceptionHandling();

        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 10]);

        $params = [
            'wallet_id'        => $wallet->id,
            'transaction_type' => 'credit',
            'amount'           => 10,
            'currency'         => $wallet->currency,
        ];

        $this->json('post', '/api/wallets/change_balance', $params)->assertOk();

        $wallet->refresh();

        $this->assertEquals(0, $wallet->balance);
    }

    /**
     * Можем добавлять деньги на существующий бумажник, c конвертацией
     *
     * @throws \Exception
     */
    public function testCanAddMoneyInDifferentCurrency()
    {
        $this->withoutExceptionHandling();

        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 0]);

        $amount = (new ConverterService())->convert(10, 'USD', 'RUB');

        $params = [
            'wallet_id'        => $wallet->id,
            'transaction_type' => 'debit',
            'amount'           => 10,
            'currency'         => 'USD',
        ];

        $this->json('post', '/api/wallets/change_balance', $params)->assertOk();

        $wallet->refresh();

        $this->assertBcEquals($amount, $wallet->balance,
            'Неверный баланс после преобразования');
    }


    /**
     * Нельзя списать больше чем есть в бумажнике
     */
    public function testUserCanNotSubMoreMoneyFromExistsWallet()
    {
        $this->withoutExceptionHandling([UnprocessableEntityHttpException::class]);

        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 5]);

        $params = [
            'wallet_id'        => $wallet->id,
            'transaction_type' => 'credit',
            'amount'           => 10,
            'currency'         => $wallet->currency,
        ];

        $this->json('post', '/api/wallets/change_balance', $params)->assertStatus(422);

        // Проверяем что баланс не поменялся
        $wallet->refresh();
        $this->assertEquals(5, $wallet->balance);
    }

    /**
     * Можно отсылать только известные типы транзакций
     */
    public function testUserCanNotSendBadTransaction()
    {
        $this->withoutExceptionHandling([ValidationException::class]);

        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 10]);

        $params = [
            'wallet_id'        => $wallet->id,
            'transaction_type' => 'fake',
            'amount'           => 10,
            'currency'         => $wallet->currency,
        ];

        $this->json('post', '/api/wallets/change_balance', $params)->assertStatus(422);
    }

    /**
     * Бумажник должен существовать
     */
    public function testCanNotChangeWrongWallet()
    {
        $this->withoutExceptionHandling([ModelNotFoundException::class]);

        $params = [
            'wallet_id'        => 123,
            'transaction_type' => 'debit',
            'amount'           => 10,
            'currency'         => 'RUB',
        ];

        $this->json('post', '/api/wallets/change_balance', $params)->assertStatus(404);
    }

    /**
     * Нельзя списать больше чем есть в бумажнике
     */
    public function testCanNotSendNegativeAmount()
    {
        $this->withoutExceptionHandling([ValidationException::class]);

        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 5]);

        $params = [
            'wallet_id'        => $wallet->id,
            'transaction_type' => 'credit',
            'amount'           => -2,
            'currency'         => $wallet->currency,
        ];

        $this->json('post', '/api/wallets/change_balance', $params)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['amount']);

        // Проверяем что баланс не поменялся
        $wallet->refresh();
        $this->assertEquals(5, $wallet->balance);
    }

    /**
     * Проверка что можем получить баланс кошелька
     */
    public function testGetBalance()
    {
        $wallet = Wallet::create(['currency' => 'RUB', 'balance' => 5]);

        $params = [
            'wallet_id' => $wallet->id,
        ];

        $this->json('get', '/api/wallets/get_balance', $params)
             ->assertOk()
             ->assertJsonFragment(['balance' => '5']);
    }

    /**
     * Возможность создавать новые кошельки по API
     */
    public function testCreateWallet()
    {
        $response = $this->json('post', '/api/wallets/create_wallet', ['currency' => 'RUB'])
                         ->assertOk()
                         ->assertJsonStructure(['id']);
    }

    /**
     * Проверка равенства с использование bccomp
     *
     * @param        $expected
     * @param        $actual
     * @param string $message
     */
    private function assertBcEquals($expected, $actual, $message = '')
    {
        $compare = bccomp($expected, $actual, 4);

        if ($compare !== 0) {
            $error = "Ожидалось $expected получили $actual";
            if ($message) {
                $error .= ' '.$message;
            }
            $this->fail($error);
        }
    }
}
