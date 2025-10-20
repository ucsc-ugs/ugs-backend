<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultPublicationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public array $resultDetails)
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
        $studentName = $this->resultDetails['student_name'] ?? 'Student';
        $examName = $this->resultDetails['exam_name'] ?? 'N/A';
        $indexNumber = $this->resultDetails['index_number'] ?? 'N/A';
        $examDate = $this->resultDetails['exam_date'] ?? 'N/A';
        $result = $this->resultDetails['result'] ?? 'N/A';
        $attended = $this->resultDetails['attended'] ?? false;

        $mailMessage = (new MailMessage)
            ->subject('Exam Results Published - ' . $examName)
            ->greeting('Exam Results Published')
            ->line("Dear {$studentName},")
            ->line("We are pleased to inform you that the results for your exam have been published.")
            ->line('')
            ->line("**Exam Details:**")
            ->line("• **Exam Name:** {$examName}")
            ->line("• **Index Number:** {$indexNumber}")
            ->line("• **Exam Date:** {$examDate}")
            ->line("• **Attendance Status:** " . ($attended ? 'Present' : 'Absent'));

        if ($attended) {
            $mailMessage->line("• **Result:** {$result}");
        } else {
            $mailMessage->line("• **Result:** Not Applicable (Absent)");
        }

        $mailMessage->line('')
            ->line('You can view your detailed results by logging into your student portal.')
            ->action('View Results', url('/student/results'))
            ->line('')
            ->line('If you have any questions or concerns regarding your results, please contact the examination office.')
            ->line('')
            ->line('Best of luck with your future endeavors!')
            ->salutation('Best regards, ' . config('app.name') . ' Team');

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'exam_name' => $this->resultDetails['exam_name'] ?? null,
            'index_number' => $this->resultDetails['index_number'] ?? null,
            'result' => $this->resultDetails['result'] ?? null,
            'attended' => $this->resultDetails['attended'] ?? false,
            'exam_date' => $this->resultDetails['exam_date'] ?? null,
        ];
    }
}
