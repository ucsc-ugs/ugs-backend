<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public array $paymentDetails)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $studentName = $this->paymentDetails['student_name'] ?? 'Student';
        $examName = $this->paymentDetails['exam_name'] ?? 'N/A';
        $currency = $this->paymentDetails['currency'] ?? '';
        $amount = isset($this->paymentDetails['amount']) ? number_format($this->paymentDetails['amount'], 2) : 'N/A';
        $statusMessage = $this->paymentDetails['status_message'] ?? 'N/A';

        return (new MailMessage)
            ->subject('Payment Received')
            ->greeting('Payment Received')
            ->line("Dear {$studentName},")
            ->line("We have received your payment for the exam \"{$examName}\".")
            ->line("**Amount:** {$currency} {$amount}")
            ->line("**Status:** {$statusMessage}")
            ->line('If you have any questions, contact the administrator.')
            ->salutation('Thanks, ' . config('app.name') . ' Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
         return [
            //
        ];
    }
}
