<?php

namespace App\Http\Controllers;

use App\Helpers\MoneyFormatter;
use App\Models\Currency;
use App\Models\Operation;
use App\Service\ReportService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Получение суммы операций по категориям за выбранный месяц
     * @param Request $request
     * @return View
     */
    public function getSumByCategories(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $defaultCurrencyName = Currency::default()->first()->name;

        $operations = $this->reportService->getOperations($month, $year);

        // Получение общей суммы операций в валюте по умолчанию
        $total = $operations->map(function ($operation) {
            return $operation->amount_in_default_currency;
        })->sum();
        $total = MoneyFormatter::getWithCurrencyName($total, $defaultCurrencyName);

        // Получение суммы операций по категориям и валютам
        $categories = $operations->groupBy(['category.name', 'currency.name']);
        $categories = $categories->map(function ($currencies) {
            return $currencies->map(function ($operations, $currencyName) {
                return collect([
                    'amount' => MoneyFormatter::getWithCurrencyName($operations->sum('amount'), $currencyName),
                    'operation_currency' => $currencyName,
                    'amount_in_default_currency' => MoneyFormatter::getWithCurrencyName(
                        $operations->sum('amount_in_default_currency'),
                        Currency::default()->first()->name
                    ),
                ]);
            })->sortKeys();
        });
        $result = $categories->sortKeys();

        // Получение суммы операций по категориям в валюте по умолчанию
        $totalByCategories = $this->reportService->getTotalByCategories(
            $this->reportService->getOperations($month, $year),
            $defaultCurrencyName,
            $month,
            $year
        );

        // Получение списка месяцев и годов для фильтрации
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $date = Carbon::create(null, $i);
            $months[$i] = ucfirst($date->monthName);
        }
        $months = array_reverse($months, true);

        // Получение списка годов в которых были расходы
        $years = Operation::select(DB::raw('EXTRACT(YEAR FROM date) as year'))
            ->isExpense()
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        return view('reports.sum-by-categories', [
            'result' => $result,
            'months' => $months,
            'years' => $years,
            'total' => $total,
            'defaultCurrencyName' => $defaultCurrencyName,
            'totalByCategories' => $totalByCategories,
        ]);
    }
}
