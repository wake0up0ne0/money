<?php

namespace App\Http\Controllers;

use App\Helpers\MoneyFormatter;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Enum\OperationType;
use App\Models\Operation;
use App\Service\ReportService;
use Illuminate\Http\Request;
use Telegram\Bot\Api;

class TelegramBotController extends Controller
{
    const COMMAND_REPORT = '/report';
    const COMMANDS = [
        self::COMMAND_REPORT,
    ];

    private $telegram;
    private ReportService $reportService;

    public function __construct(Api $telegram, ReportService $reportService)
    {
        $this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $this->reportService = $reportService;
    }

    public function handleWebhook()
    {
        $updates = $this->telegram->getWebhookUpdate();
        $message = $updates->getMessage();

        $userId = $message->from->id;
        $text = $message->text;

        logger()->info('Message received', [
            'message' => $text,
            'user_id' => $userId,
        ]);

        if (!in_array($userId, $this->getUserIds())) {
            $this->telegram->sendMessage([
                'chat_id' => $userId,
                'text' => 'You are not allowed to use this bot',
            ]);

            logger()->warning('User not allowed', [
                'user_id' => $userId,
                'text' => $text,
            ]);
            return;
        }

        if ($text === self::COMMAND_REPORT) {
            $this->handleReportCommand($userId);
        } else {
            $this->createExpense($text, $userId);
        }
    }

    private function createExpense(string $text, int $userId)
    {
        try {
            $text = explode(' ', $text);
            $amount = $text[0];
            if (!is_numeric($amount)) {
                $this->telegram->sendMessage([
                    'chat_id' => $userId,
                    'text' => 'Invalid amount',
                ]);

                logger()->warning('Invalid amount', [
                    'user_id' => $userId,
                    'text' => $text,
                ]);
                return;
            }
            $categoryName = $text[1] ?? '';

            $category = null;
            if ($categoryName) {
                $category = Category::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($categoryName) . '%'])->first();
            }
            $bill = Bill::default()->firstOrFail();
            $currency = Currency::active()->firstOrFail();

            $operation = new Operation();
            $operation->amount = $amount;

            if ($category) {
                $operation->category_id = $category->id;
            } else {
                $operation->notes = $categoryName;
            }

            $operation->bill_id = $bill->id;
            $operation->currency_id = $currency->id;
            $operation->date = date('Y-m-d');
            $operation->type = OperationType::Expense->name;

            $operation->user_id = array_search($userId, $this->getUserIds());
            if ($operation->user_id === false) {
                $operation->user_id = 1;
            }

            $operation->is_draft = true;

            $operation->save();

            if ($category) {
                $this->telegram->sendMessage([
                    'chat_id' => $userId,
                    'text' => sprintf('Expense of %s %s for %s created', $amount, $currency->name, $category->name)
                ]);
            } else if (strlen($operation->notes) > 0) {
                $this->telegram->sendMessage([
                    'chat_id' => $userId,
                    'text' => sprintf('Expense of %s %s with notes %s created', $amount, $currency->name, $operation->notes)
                ]);
            } else {
                $this->telegram->sendMessage([
                    'chat_id' => $userId,
                    'text' => sprintf('Expense of %s %s created', $amount, $currency->name)
                ]);
            }
        } catch (\Exception $e) {
            $this->telegram->sendMessage([
                'chat_id' => $userId,
                'text' => 'Error creating expense: ' . $e->getMessage(),
            ]);

            logger()->error('Error creating expense', [
                'user_id' => $userId,
                'text' => $text,
                'exception' => $e,
            ]);
        }
    }

    private function handleReportCommand(int $userId): void
    {
        $month = date('n');
        $year = date('Y');

        // @todo: move together with \App\Service\ReportService::getOperations
        $operations = $this->reportService->getOperations($month, $year);

        $total = $operations->map(function ($operation) {
            return $operation->amount_in_default_currency;
        })->sum();
        $total = MoneyFormatter::getWithCurrencyName($total, Currency::getDefaultCurrencyName());

        $data = $this->reportService->getTotalByCategories(
            $operations,
            Currency::getDefaultCurrencyName()
        );
        $text = 'Report for ' . date('F Y') . ':' . PHP_EOL;
        $text .= 'Total: ' . $total . PHP_EOL . PHP_EOL;
        foreach ($data as $value) {
            $text .= $value['categoryName'] . ': ' . $value['total'] . PHP_EOL;
        }

        $this->telegram->sendMessage([
            'chat_id' => $userId,
            'text' => $text,
        ]);
    }

    public function getUserIds(): array
    {
        $userIds = env('TELEGRAM_USER_IDS', '');
        $userIds = json_decode($userIds, true);

        if (empty($userIds)) {
            throw new \Exception('TELEGRAM_USER_IDS is not set');
        }

        return $userIds;
    }
}
