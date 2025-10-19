<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrgAdminDashboardController extends Controller
{
    /**
     * Return dashboard overview for org admin
     * Includes: stats, registration trends, exam distribution, recent registrations
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('org_admin')) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        $orgAdmin = $user->orgAdmin;
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin linkage missing.'], 403);
        }

        $orgId = $orgAdmin->organization_id;

        // Total exams for this organization
        $totalExams = DB::table('exams')->where('organization_id', $orgId)->count();

        // Active exams (where registration_deadline is in the future or not set)
        $activeExams = DB::table('exams')
            ->where('organization_id', $orgId)
            ->where(function ($q) {
                $q->whereNull('registration_deadline')
                  ->orWhere('registration_deadline', '>=', now());
            })
            ->count();

        // Total registrations (all student_exams for this org's exams)
        $totalRegistrations = DB::table('student_exams')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->where('exams.organization_id', $orgId)
            ->count();

        // Pending registrations (not paid)
        $pendingRegistrations = DB::table('student_exams')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->leftJoin('payments', 'payments.student_exam_id', '=', 'student_exams.id')
            ->where('exams.organization_id', $orgId)
            ->where(function ($q) {
                $q->whereNull('payments.status_code')
                  ->orWhere('payments.status_code', '<>', 2);
            })
            ->count();

        // Total revenue (sum of paid payments)
        $totalRevenue = DB::table('payments')
            ->join('student_exams', 'payments.student_exam_id', '=', 'student_exams.id')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->where('exams.organization_id', $orgId)
            ->where('payments.status_code', 2)
            ->sum(DB::raw('COALESCE(payments.payhere_amount, exams.price, 0)'));

        // Revenue change (compare last 30 days vs previous 30 days)
        $last30DaysRevenue = DB::table('payments')
            ->join('student_exams', 'payments.student_exam_id', '=', 'student_exams.id')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->where('exams.organization_id', $orgId)
            ->where('payments.status_code', 2)
            ->where('payments.created_at', '>=', now()->subDays(30))
            ->sum(DB::raw('COALESCE(payments.payhere_amount, exams.price, 0)'));

        $previous30DaysRevenue = DB::table('payments')
            ->join('student_exams', 'payments.student_exam_id', '=', 'student_exams.id')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->where('exams.organization_id', $orgId)
            ->where('payments.status_code', 2)
            ->whereBetween('payments.created_at', [now()->subDays(60), now()->subDays(30)])
            ->sum(DB::raw('COALESCE(payments.payhere_amount, exams.price, 0)'));

        $revenueChange = $previous30DaysRevenue > 0 
            ? (($last30DaysRevenue - $previous30DaysRevenue) / $previous30DaysRevenue) * 100 
            : 0;

        // Upcoming exams (exams with future registration_deadline or exam_dates)
        $upcomingExams = DB::table('exams')
            ->leftJoin('exam_dates', 'exam_dates.exam_id', '=', 'exams.id')
            ->where('exams.organization_id', $orgId)
            ->where(function ($q) {
                $q->where('exams.registration_deadline', '>=', now())
                  ->orWhere('exam_dates.date', '>=', now());
            })
            ->distinct('exams.id')
            ->count('exams.id');

        // Registration trends (last 6 months)
        $registrationTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = DB::table('student_exams')
                ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
                ->where('exams.organization_id', $orgId)
                ->whereYear('student_exams.created_at', $month->year)
                ->whereMonth('student_exams.created_at', $month->month)
                ->count();
            $registrationTrends[] = [
                'name' => $month->format('M'),
                'value' => $count
            ];
        }

        // Exam distribution (top exams by registration count)
        $examDistribution = DB::table('student_exams')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->where('exams.organization_id', $orgId)
            ->select('exams.name', DB::raw('COUNT(student_exams.id) as value'))
            ->groupBy('exams.name')
            ->orderByDesc('value')
            ->limit(5)
            ->get()
            ->toArray();

        // Recent registrations (last 5)
        $recentRegistrations = DB::table('student_exams')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->join('users', 'student_exams.student_id', '=', 'users.id')
            ->leftJoin('payments', 'payments.student_exam_id', '=', 'student_exams.id')
            ->where('exams.organization_id', $orgId)
            ->select(
                'student_exams.id',
                'users.name as student',
                'exams.name as exam',
                'student_exams.created_at as date',
                DB::raw("CASE 
                    WHEN payments.status_code = 2 THEN 'completed'
                    WHEN payments.status_code IS NULL THEN 'pending'
                    ELSE 'rejected'
                END as status")
            )
            ->orderByDesc('student_exams.created_at')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'student' => $row->student,
                    'exam' => $row->exam,
                    'date' => Carbon::parse($row->date)->toDateString(),
                    'status' => $row->status,
                ];
            })
            ->toArray();

        return response()->json([
            'message' => 'Dashboard overview retrieved',
            'data' => [
                'stats' => [
                    'totalExams' => $totalExams,
                    'activeExams' => $activeExams,
                    'totalRegistrations' => $totalRegistrations,
                    'pendingRegistrations' => $pendingRegistrations,
                    'totalRevenue' => (float) $totalRevenue,
                    'revenueChange' => round($revenueChange, 1),
                    'upcomingExams' => $upcomingExams,
                ],
                'registrationTrends' => $registrationTrends,
                'examDistribution' => $examDistribution,
                'recentRegistrations' => $recentRegistrations,
            ]
        ]);
    }
}
