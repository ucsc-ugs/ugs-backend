<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\StudentExam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function initiatePayment(int $regId, int $amount, $userData)
    {
        $merchant_id = env('PAYHERE_MERCHANT_ID');
        $merchant_secret = env('PAYHERE_MERCHANT_SECRET');
        $currency = 'LKR';

        $hash = strtoupper(
            md5(
                $merchant_id . 
                $regId . 
                number_format($amount, 2, '.', '') . 
                $currency .  
                strtoupper(md5($merchant_secret)) 
            ) 
        );

        return [
            'merchant_id' => $merchant_id,
            'amount' => $amount,
            'currency' => $currency,
            'notify_url' => 'https://6c8f55c58cf7.ngrok-free.app/api/payment/notify', // route('payment.notify'),
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'],
            'address' => '',
            'city' => 'Colombo',
            'country' => 'Sri Lanka',
            'order_id' => $regId,
            'items' => 'Exam Registration Fee',
            'hash' => $hash
        ];
    }

    public function notify(Request $request)
    {
        $merchant_id      = $request->input('merchant_id');
        $order_id         = $request->input('order_id');
        $payhere_amount   = $request->input('payhere_amount');
        $payhere_currency = $request->input('payhere_currency');
        $status_code      = $request->input('status_code');
        $md5sig           = $request->input('md5sig');

        $merchant_secret = env('PAYHERE_MERCHANT_SECRET');

        $local_md5sig = strtoupper(
            md5(
                $merchant_id . 
                $order_id . 
                $payhere_amount . 
                $payhere_currency . 
                $status_code . 
                strtoupper(md5($merchant_secret)) 
            ) 
        );

        // Find the student exam registration by order_id
        $studentExam = StudentExam::find($order_id, 'id');

        if ($studentExam) {
            // Create payment record
            $payment = Payment::create([
                'student_exam_id' => $studentExam->id,
                'payment_id' => $request->input('payment_id'),
                'payhere_amount' => $payhere_amount,
                'payhere_currency' => $payhere_currency,
                'status_code' => $status_code,
                'md5sig' => $md5sig,
                'method' => $request->input('method'),
                'status_message' => $request->input('status_message'),
            ]);
        }

        /**
         * Payment Status Codes
         * 2 - success
         * 0 - pending
         * -1 - canceled
         * -2 - failed
         * -3 - chargedback
         */

        $studentExam->update(['payment_id' => $payment->id]);

        if (($local_md5sig === $md5sig) AND ($status_code == 2) ){
            // Update student exam status to registered
            $studentExam->update(['status' => 'registered']);
        } else if ($status_code == 0) {
            // Payment pending
            $studentExam->update(['status' => 'pending']);
        } else if ($status_code == -1) {
            // Payment rejected
            $studentExam->update(['status' => 'rejected']);
        } else if ($status_code == -2) {
            // Payment pending
            $studentExam->update(['status' => 'rejected']);
        } else if ($status_code == -3) {
            // Payment pending
            $studentExam->update(['status' => 'rejected']);
        } else {
            // Payment failed or cancelled
            $studentExam->update(['status' => 'rejected']);
        }
    }

    public function verify(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:student_exams,id'
        ]);

        $orderId = $request->input('order_id');

        // Find the student exam registration by order_id (which is student_exam_id)
        $studentExam = StudentExam::with(['payment', 'exam', 'student'])
            ->find($orderId);

        if (!$studentExam) {
            return response()->json([
                'status' => 'error',
                'message' => 'No exam registration found for this order ID'
            ], 404);
        }

        // Prepare response data
        $responseData = [
            'status' => 'success',
            'data' => [
                'order_id' => $studentExam->id,
                'exam_name' => $studentExam->exam ? $studentExam->exam->name : null,
                'registration_status' => $studentExam->status,
                'index_number' => $studentExam->index_number,
                'student_name' => $studentExam->student ? $studentExam->student->name : null,
                'payment' => null
            ]
        ];

        // Add payment details if payment exists
        if ($studentExam->payment) {
            $payment = $studentExam->payment;
            $responseData['data']['payment'] = [
                'payment_id' => $payment->payment_id,
                'amount' => $payment->payhere_amount,
                'currency' => $payment->payhere_currency,
                'status_code' => $payment->status_code,
                'status_message' => $this->getPaymentStatusMessage($payment->status_code),
                'method' => $payment->method,
                'gateway_status_message' => $payment->status_message,
                'created_at' => $payment->created_at,
            ];
        }

        return response()->json($responseData);
    }

    /**
     * Get human-readable payment status message
     */
    private function getPaymentStatusMessage($statusCode)
    {
        return match($statusCode) {
            2 => 'Payment Successful',
            0 => 'Payment Pending',
            -1 => 'Payment Cancelled',
            -2 => 'Payment Failed',
            -3 => 'Payment Charged Back',
            default => 'Unknown Status'
        };
    }
}

/**
 * merchant_id - PayHere Merchant ID
 * return_url - URL to redirect users when payment is approved
 * cancel_url - URL to redirect users when user cancel the payment
 * notify_url - URL to callback the status of the payment (Needs to be a URL accessible on a public IP/domain)
 * first_name - Customer’s First Name
 * last_name - Customer’s Last Name
 * email - Customer’s Email
 * phone - Customer’s Phone No
 * address - Customer’s Address Line1 + Line2
 * city - Customer’s City
 * country - Customer’s Country
 * order_id - Order ID generated by the merchant
 * items - Item title or Order/Invoice number
 * currency - Currency Code (LKR/USD)
 * amount - Total Payment Amount
 * hash - Generated hash value as mentioned below (*Required from 2023-01-16)
 */
