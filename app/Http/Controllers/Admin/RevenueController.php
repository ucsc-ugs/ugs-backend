<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->query('range', 'all_time');
        
        // Determine date range
        $startDate = $this->getStartDate($range);
        
        // Calculate total revenue and commission
        $totals = DB::table('payments')
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate);
            })
            ->where('status_code', 2) // status_code 2 means completed payment
            ->selectRaw('
                SUM(payhere_amount) as total_revenue,
                SUM(commission_amount) as total_commission
            ')
            ->first();
        
        // Revenue by organization
        $organizationRevenues = DB::table('payments')
            ->join('student_exams', 'payments.student_exam_id', '=', 'student_exams.id')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->join('organizations', 'exams.organization_id', '=', 'organizations.id')
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('payments.created_at', '>=', $startDate);
            })
            ->where('payments.status_code', 2)
            ->groupBy('organizations.id', 'organizations.name')
            ->selectRaw('
                organizations.id,
                organizations.name,
                SUM(payments.payhere_amount) as revenue,
                SUM(payments.commission_amount) as commission,
                COUNT(DISTINCT exams.id) as exam_count
            ')
            ->get();
        
        // Revenue by exam
        $examRevenues = DB::table('payments')
            ->join('student_exams', 'payments.student_exam_id', '=', 'student_exams.id')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->join('organizations', 'exams.organization_id', '=', 'organizations.id')
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('payments.created_at', '>=', $startDate);
            })
            ->where('payments.status_code', 2)
            ->groupBy('exams.id', 'exams.name', 'organizations.name')
            ->selectRaw('
                exams.id,
                exams.name,
                organizations.name as organization_name,
                SUM(payments.payhere_amount) as revenue,
                SUM(payments.commission_amount) as commission,
                COUNT(payments.id) as attempt_count
            ')
            ->get();
        
        // Monthly revenue trends
        $monthlyRevenues = DB::table('payments')
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate);
            })
            ->where('status_code', 2)
            ->groupBy('month')
            ->selectRaw("
                TO_CHAR(created_at, 'YYYY-MM') as month,
                SUM(payhere_amount) as revenue,
                SUM(commission_amount) as commission
            ")
            ->orderBy('month', 'asc')
            ->get();
        
        return response()->json([
            'total_revenue' => (float) ($totals->total_revenue ?? 0),
            'total_commission' => (float) ($totals->total_commission ?? 0),
            'organization_revenues' => $organizationRevenues->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'revenue' => (float) $item->revenue,
                    'commission' => (float) $item->commission,
                    'exam_count' => (int) $item->exam_count,
                ];
            }),
            'exam_revenues' => $examRevenues->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'organization_name' => $item->organization_name,
                    'revenue' => (float) $item->revenue,
                    'commission' => (float) $item->commission,
                    'attempt_count' => (int) $item->attempt_count,
                ];
            }),
            'monthly_revenues' => $monthlyRevenues->map(function ($item) {
                return [
                    'month' => $item->month,
                    'revenue' => (float) $item->revenue,
                    'commission' => (float) $item->commission,
                ];
            }),
        ]);
    }
    
    private function getStartDate($range)
    {
        return match($range) {
            'last_7_days' => Carbon::now()->subDays(7),
            'last_30_days' => Carbon::now()->subDays(30),
            'last_quarter' => Carbon::now()->subMonths(3),
            'last_year' => Carbon::now()->subYear(),
            default => null, // all_time
        };
    }
}