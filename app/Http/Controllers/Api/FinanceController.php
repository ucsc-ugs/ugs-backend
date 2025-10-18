<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Return finance overview for the organization admin (or all if super admin)
     * Query params:
     *  - exam_id (optional)
     *  - month (optional, YYYY-MM)
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $isSuper = method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : false;

        $examId = $request->query('exam_id');
        $month = $request->query('month'); // YYYY-MM

        $orgId = null;
        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin) {
                return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
            }
            $orgId = $orgAdmin->organization_id;
        }

        // Build aggregate query
        $query = DB::table('exams')
            ->leftJoin('exam_dates', 'exam_dates.exam_id', '=', 'exams.id')
            ->leftJoin('student_exams', 'student_exams.exam_id', '=', 'exams.id')
            ->leftJoin('payments', 'payments.student_exam_id', '=', 'student_exams.id')
            ->leftJoin('organizations', 'organizations.id', '=', 'exams.organization_id')
            ->when(!$isSuper && $orgId, function ($q) use ($orgId) {
                $q->where('exams.organization_id', $orgId);
            })
            ->when($examId, function ($q) use ($examId) {
                $q->where('exams.id', $examId);
            })
            ->groupBy('exams.id', 'exams.name', 'exams.price', 'organizations.name', 'exams.registration_deadline');

        // Aggregates
        $query->select([
            DB::raw('exams.id as id'),
            DB::raw('exams.name as exam_name'),
            DB::raw('COALESCE(MIN(exam_dates.date), exams.registration_deadline) as exam_date'),
            DB::raw('COALESCE(exams.price, 0) as exam_fee'),
            DB::raw('COALESCE(organizations.name, \'\') as university'),
            // counts
            DB::raw('COUNT(DISTINCT student_exams.id) as total_registrations'),
            DB::raw("COUNT(DISTINCT CASE WHEN payments.status_code = 2 THEN student_exams.id END) as paid_registrations"),
            DB::raw("COUNT(DISTINCT CASE WHEN payments.status_code IS NULL OR payments.status_code <> 2 THEN student_exams.id END) as unpaid_registrations"),
            // revenues
            DB::raw('COALESCE(SUM(CASE WHEN payments.status_code = 2 THEN COALESCE(payments.payhere_amount, exams.price) ELSE 0 END), 0) as paid_revenue'),
        ]);

        // Month filter (YYYY-MM) applied via HAVING using to_char on exam_date
        if ($month) {
            $query->havingRaw("to_char(COALESCE(MIN(exam_dates.date), exams.registration_deadline), 'YYYY-MM') = ?", [$month]);
        }

        $rows = $query->get();

        $data = $rows->map(function ($r) {
            $totalRevenue = (float)$r->paid_revenue + ((float)$r->exam_fee * (int)$r->unpaid_registrations);
            $unpaidRevenue = (float)$totalRevenue - (float)$r->paid_revenue;
            return [
                'id' => (int)$r->id,
                'examName' => $r->exam_name,
                'examDate' => $r->exam_date,
                'totalRegistrations' => (int)$r->total_registrations,
                'paidRegistrations' => (int)$r->paid_registrations,
                'unpaidRegistrations' => (int)$r->unpaid_registrations,
                'examFee' => (float)$r->exam_fee,
                'totalRevenue' => (float)$totalRevenue,
                'paidRevenue' => (float)$r->paid_revenue,
                'unpaidRevenue' => (float)$unpaidRevenue,
                'university' => $r->university,
            ];
        });

        return response()->json([
            'message' => 'Finance overview',
            'data' => $data,
        ]);
    }
}
