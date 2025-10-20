<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\StudentExam;
use App\Models\RevenueTransaction;
use App\Traits\CreatesNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    use CreatesNotifications;
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
            'notify_url' => 'https://1fcd80146b28.ngrok-free.app/api/payment/notify', // route('payment.notify'),
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
        $studentExam = StudentExam::with(['exam.organization'])->find($order_id);

        if (!$studentExam) {
            return response()->json(['status' => 'error', 'message' => 'Student exam not found'], 404);
        }

        DB::beginTransaction();
        
        try {
            $exam = $studentExam->exam;
            
            // Calculate commission and net amount
            $commissionAmount = $exam ? $exam->calculateCommission($payhere_amount) : 0;
            $netAmount = $payhere_amount - $commissionAmount;

            // Create or update payment record
            $payment = Payment::updateOrCreate(
                [
                    'student_exam_id' => $studentExam->id,
                ],
                [
                    'payment_id' => $request->input('payment_id'),
                    'payhere_amount' => $payhere_amount,
                    'payhere_currency' => $payhere_currency,
                    'status_code' => $status_code,
                    'md5sig' => $md5sig,
                    'method' => $request->input('method'),
                    'status_message' => $request->input('status_message'),
                    'commission_amount' => $commissionAmount,
                    'net_amount' => $netAmount,
                ]
            );

            /**
             * Payment Status Codes
             * 2 - success
             * 0 - pending
             * -1 - canceled
             * -2 - failed
             * -3 - chargedback
             */

            // Update student exam with payment_id
            $studentExam->update(['payment_id' => $payment->id]);

            // Handle payment status and create revenue transaction if successful
            if (($local_md5sig === $md5sig) && ($status_code == 2)) {
                // Payment successful
                $studentExam->update(['status' => 'registered']);

                // Sending the payment success email notification
                $student = $studentExam->student;
                $paymentDetails = [
                    'student_name' => $student ? $student->name : null,
                    'exam_name' => $exam ? $exam->name : null,
                    'amount' => $payhere_amount,
                    'currency' => $payhere_currency,
                    'status_message' => $this->getPaymentStatusMessage($status_code),
                ];

                try {
                    $student->notify(new \App\Notifications\PaymentNotification($paymentDetails));
                } catch (\Exception $e) {
                    Log::warning('Failed to send payment notification email: ' . $e->getMessage());
                }
                
                // Create in-app notification for the student
                $this->createNotification(
                    'Payment Successful',
                    "Your payment of {$payhere_currency} {$payhere_amount} for exam \"{$exam->name}\" has been successfully processed. You will receive an email shortly.",
                    null,
                    $student->id,
                    false
                );
                
                // Create revenue transaction
                $this->createRevenueTransaction(
                    $studentExam,
                    $exam,
                    $payhere_amount,
                    $commissionAmount,
                    $netAmount
                );
                
            } else if ($status_code == 0) {
                // Payment pending
                $studentExam->update(['status' => 'pending']);
                
                // Create in-app notification for pending payment
                $student = $studentExam->student;
                $this->createNotification(
                    'Payment Pending',
                    "Your payment of {$payhere_currency} {$payhere_amount} for exam \"{$exam->name}\" is pending.",
                    null,
                    $student->id,
                    false
                );
            } else if ($status_code == -1) {
                // Payment cancelled
                $studentExam->update(['status' => 'rejected']);
                
                // Create in-app notification for cancelled payment
                $student = $studentExam->student;
                $this->createNotification(
                    'Payment Cancelled',
                    "Your payment of {$payhere_currency} {$payhere_amount} for exam \"{$exam->name}\" was cancelled.",
                    null,
                    $student->id,
                    false
                );
            } else if ($status_code == -2) {
                // Payment failed
                $studentExam->update(['status' => 'rejected']);
                
                // Create in-app notification for failed payment
                $student = $studentExam->student;
                $this->createNotification(
                    'Payment Failed',
                    "Your payment of {$payhere_currency} {$payhere_amount} for exam \"{$exam->name}\" failed. Please try again.",
                    null,
                    $student->id,
                    false
                );
            } else if ($status_code == -3) {
                // Payment charged back
                $studentExam->update(['status' => 'rejected']);
                
                // If there was a previous successful transaction, mark it as refunded
                $this->handleRefund($studentExam);
                
                // Create in-app notification for charged back payment
                $student = $studentExam->student;
                $this->createNotification(
                    'Payment Charged Back',
                    "Your payment of {$payhere_currency} {$payhere_amount} for exam \"{$exam->name}\" was charged back.",
                    null,
                    $student->id,
                    false
                );
            } else {
                // Payment failed or cancelled
                $studentExam->update(['status' => 'rejected']);
                
                // Create in-app notification for unknown status
                $student = $studentExam->student;
                $this->createNotification(
                    'Payment Issue',
                    "There was an issue with your payment of {$payhere_currency} {$payhere_amount} for exam \"{$exam->name}\". Please contact support.",
                    null,
                    $student->id,
                    false
                );
            }

            DB::commit();
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the error
            Log::error('Payment processing error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Payment processing failed'
            ], 500);
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
                'commission_amount' => $payment->commission_amount,
                'net_amount' => $payment->net_amount,
                'created_at' => $payment->created_at,
            ];
        }

        return response()->json($responseData);
    }

    /**
     * Create revenue transaction after successful payment
     */
    private function createRevenueTransaction($studentExam, $exam, $amount, $commission, $netAmount)
    {
        // Check if revenue transaction already exists for this student exam
        $existingTransaction = RevenueTransaction::where('student_exam_id', $studentExam->id)
            ->where('status', 'completed')
            ->first();
        
        if ($existingTransaction) {
            // Transaction already exists, don't create duplicate
            return;
        }

        RevenueTransaction::create([
            'student_exam_id' => $studentExam->id,
            'organization_id' => $exam->organization_id,
            'exam_id' => $exam->id,
            'revenue' => $amount,
            'commission' => $commission,
            'net_revenue' => $netAmount,
            'transaction_reference' => $this->generateTransactionReference(),
            'status' => 'completed',
            'transaction_date' => now(),
        ]);
    }

    /**
     * Handle refund by marking revenue transaction as refunded
     */
    private function handleRefund($studentExam)
    {
        $transaction = RevenueTransaction::where('student_exam_id', $studentExam->id)
            ->where('status', 'completed')
            ->first();
        
        if ($transaction) {
            $transaction->update(['status' => 'refunded']);
        }
    }

    /**
     * Generate unique transaction reference
     */
    private function generateTransactionReference(): string
    {
        do {
            $reference = 'TXN-' . strtoupper(Str::random(12));
        } while (RevenueTransaction::where('transaction_reference', $reference)->exists());
        
        return $reference;
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
 * first_name - Customer's First Name
 * last_name - Customer's Last Name
 * email - Customer's Email
 * phone - Customer's Phone No
 * address - Customer's Address Line1 + Line2
 * city - Customer's City
 * country - Customer's Country
 * order_id - Order ID generated by the merchant
 * items - Item title or Order/Invoice number
 * currency - Currency Code (LKR/USD)
 * amount - Total Payment Amount
 * hash - Generated hash value as mentioned below (*Required from 2023-01-16)
 */