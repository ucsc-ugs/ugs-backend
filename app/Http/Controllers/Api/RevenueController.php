<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Exam;
use App\Models\RevenueTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $timeRange = $request->input('time_range', 'all_time');
        $query = RevenueTransaction::query()->where('status', 'completed');

        $query = $this->applyTimeRangeFilter($query, $timeRange);

        $totals = $query->selectRaw('
            SUM(revenue) as total_revenue,
            SUM(commission) as total_commission
        ')->first();

        $organizationRevenues = $this->getOrganizationRevenues($timeRange);
        $examRevenues = $this->getExamRevenues($timeRange);
        $monthlyRevenues = $this->getMonthlyRevenues($timeRange);

        return response()->json([
            'total_revenue' => (float) ($totals->total_revenue ?? 0),
            'total_commission' => (float) ($totals->total_commission ?? 0),
            'organization_revenues' => $organizationRevenues,
            'exam_revenues' => $examRevenues,
            'monthly_revenues' => $monthlyRevenues,
        ]);
    }

    private function applyTimeRangeFilter($query, string $timeRange)
    {
        switch ($timeRange) {
            case 'last_7_days':
                return $query->where('transaction_date', '>=', Carbon::now()->subDays(7));
            case 'last_30_days':
                return $query->where('transaction_date', '>=', Carbon::now()->subDays(30));
            case 'last_quarter':
                return $query->where('transaction_date', '>=', Carbon::now()->subMonths(3));
            case 'last_year':
                return $query->where('transaction_date', '>=', Carbon::now()->subYear());
            default:
                return $query;
        }
    }

    private function getOrganizationRevenues(string $timeRange)
    {
        $query = Organization::select(
            'organizations.id',
            'organizations.name',
            DB::raw('COALESCE(SUM(revenue_transactions.revenue), 0) as revenue'),
            DB::raw('COALESCE(SUM(revenue_transactions.commission), 0) as commission'),
            DB::raw('COUNT(DISTINCT revenue_transactions.exam_id) as exam_count')
        )
        ->leftJoin('revenue_transactions', function($join) use ($timeRange) {
            $join->on('organizations.id', '=', 'revenue_transactions.organization_id')
                 ->where('revenue_transactions.status', '=', 'completed');
            
            if ($timeRange !== 'all_time') {
                $startDate = $this->getStartDateForTimeRange($timeRange);
                if ($startDate) {
                    $join->where('revenue_transactions.transaction_date', '>=', $startDate);
                }
            }
        })
        ->groupBy('organizations.id', 'organizations.name')
        ->orderBy('revenue', 'desc');

        return $query->get()->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'revenue' => (float) $org->revenue,
                'commission' => (float) $org->commission,
                'exam_count' => (int) $org->exam_count,
            ];
        });
    }

    private function getExamRevenues(string $timeRange)
    {
        $query = Exam::select(
            'exams.id',
            'exams.name',
            'organizations.name as organization_name',
            DB::raw('COALESCE(SUM(revenue_transactions.revenue), 0) as revenue'),
            DB::raw('COALESCE(SUM(revenue_transactions.commission), 0) as commission'),
            DB::raw('COUNT(revenue_transactions.id) as attempt_count')
        )
        ->join('organizations', 'exams.organization_id', '=', 'organizations.id')
        ->leftJoin('revenue_transactions', function($join) use ($timeRange) {
            $join->on('exams.id', '=', 'revenue_transactions.exam_id')
                 ->where('revenue_transactions.status', '=', 'completed');
            
            if ($timeRange !== 'all_time') {
                $startDate = $this->getStartDateForTimeRange($timeRange);
                if ($startDate) {
                    $join->where('revenue_transactions.transaction_date', '>=', $startDate);
                }
            }
        })
        ->groupBy('exams.id', 'exams.name', 'organizations.name')
        ->orderBy('revenue', 'desc');

        return $query->get()->map(function ($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'organization_name' => $exam->organization_name,
                'revenue' => (float) $exam->revenue,
                'commission' => (float) $exam->commission,
                'attempt_count' => (int) $exam->attempt_count,
            ];
        });
    }

    private function getMonthlyRevenues(string $timeRange)
    {
        $startDate = $this->getStartDateForTimeRange($timeRange);
        
        $query = RevenueTransaction::select(
            DB::raw('DATE_FORMAT(transaction_date, "%b") as month'),
            DB::raw('SUM(revenue) as revenue'),
            DB::raw('SUM(commission) as commission')
        )
        ->where('status', 'completed')
        ->groupBy(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m")'), 'month')
        ->orderBy(DB::raw('DATE_FORMAT(transaction_date, "%Y-%m")'));

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        return $query->get()->map(function ($item) {
            return [
                'month' => $item->month,
                'revenue' => (float) $item->revenue,
                'commission' => (float) $item->commission,
            ];
        });
    }

    private function getStartDateForTimeRange(string $timeRange): ?Carbon
    {
        return match($timeRange) {
            'last_7_days' => Carbon::now()->subDays(7),
            'last_30_days' => Carbon::now()->subDays(30),
            'last_quarter' => Carbon::now()->subMonths(3),
            'last_year' => Carbon::now()->subYear(),
            default => null,
        };
    }

    public function updateCommission(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:0|max:100',
        ]);

        $exam->update([
            'commission_rate' => $validated['commission_rate'],
        ]);

        return response()->json([
            'message' => 'Commission rate updated successfully',
            'exam' => $exam,
        ]);
    }

    public function organizationDetails(Organization $organization, Request $request)
    {
        $timeRange = $request->input('time_range', 'all_time');
        
        $query = RevenueTransaction::where('organization_id', $organization->id)
            ->where('status', 'completed');
        
        $query = $this->applyTimeRangeFilter($query, $timeRange);

        $totals = $query->selectRaw('
            SUM(revenue) as total_revenue,
            SUM(commission) as total_commission,
            COUNT(*) as transaction_count
        ')->first();

        $exams = $organization->exams()
            ->withCount('studentExams')
            ->get();

        return response()->json([
            'organization' => $organization,
            'total_revenue' => (float) ($totals->total_revenue ?? 0),
            'total_commission' => (float) ($totals->total_commission ?? 0),
            'transaction_count' => (int) ($totals->transaction_count ?? 0),
            'exams' => $exams,
        ]);
    }
}