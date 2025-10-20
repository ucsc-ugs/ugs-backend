<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Exam;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class NotificationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get exams and students
        $gcct = Exam::where('name', 'General Computing Competence Test')->first();
        $gcat = Exam::where('name', 'General Computing Aptitude Test')->first();
        
        $students = User::where('user_type', 'student')->get();
        $adminUser = User::whereHas('roles', function ($query) {
            $query->where('name', 'super-admin');
        })->first();

        if (!$adminUser) {
            $adminUser = User::where('user_type', 'super-admin')->first();
        }

        $createdBy = $adminUser ? $adminUser->id : null;

        // Create Announcements
        $announcements = [
            [
                'title' => 'Welcome to UCSC Graduate Studies Portal',
                'message' => "Dear Students,\n\nWelcome to the University of Colombo School of Computing Graduate Studies Portal. This platform will be your gateway to register for entrance examinations, check results, and receive important updates about your graduate studies journey.\n\nPlease ensure your profile information is up to date and check this portal regularly for announcements and notifications.\n\nBest regards,\nUCSC Graduate Studies Team",
                'audience' => 'all',
                'exam_id' => null,
                'expiry_date' => now()->addMonths(6),
                'publish_date' => now()->subDays(5),
                'status' => 'published',
                'priority' => 'high',
                'category' => 'general',
                'tags' => ['welcome', 'getting-started'],
                'is_pinned' => true,
                'created_by' => $createdBy,
            ],
            [
                'title' => 'GCCT Examination Registration Open',
                'message' => "Dear Prospective Students,\n\nWe are pleased to announce that registration for the General Computing Competence Test (GCCT) is now open. This test is a prerequisite for applying to our Master of Computer Science (MCS) and Master of Science in Computer Science (MSc in CS) programs.\n\nKey Details:\n- Registration Deadline: " . now()->addDays(30)->format('F d, Y') . "\n- Exam Date: " . now()->addDays(45)->format('F d, Y') . "\n- Test Fee: Rs. 5,000\n- Validity: 2 years from test date\n\nThe GCCT assesses general computing skills and is scored on a ten-band scale. Please register early to secure your preferred exam date and location.\n\nFor more information, visit the Exams section of this portal.\n\nGood luck with your preparation!\nUCSC Admissions Team",
                'audience' => 'exam-specific',
                'exam_id' => $gcct ? $gcct->id : null,
                'expiry_date' => now()->addDays(30),
                'publish_date' => now()->subDays(3),
                'status' => 'published',
                'priority' => 'high',
                'category' => 'exam',
                'tags' => ['registration', 'gcct', 'deadline'],
                'is_pinned' => true,
                'created_by' => $createdBy,
            ],
        ];

        $createdAnnouncements = [];
        foreach ($announcements as $announcementData) {
            $announcement = Announcement::create($announcementData);
            $createdAnnouncements[] = $announcement;
            $this->command->info("Created announcement: {$announcement->title}");
        }

        // Create some announcement reads (simulate that some students have read some announcements)
        if ($students->count() > 0) {
            // First student has read the welcome announcement
            if (isset($createdAnnouncements[0])) {
                AnnouncementRead::create([
                    'announcement_id' => $createdAnnouncements[0]->id,
                    'student_id' => $students[0]->student_id,
                    'read_at' => now()->subDays(4),
                ]);
            }

            // Second student has read welcome and GCCT announcement
            if ($students->count() > 1 && isset($createdAnnouncements[0]) && isset($createdAnnouncements[1])) {
                AnnouncementRead::create([
                    'announcement_id' => $createdAnnouncements[0]->id,
                    'student_id' => $students[1]->student_id,
                    'read_at' => now()->subDays(3),
                ]);
                AnnouncementRead::create([
                    'announcement_id' => $createdAnnouncements[1]->id,
                    'student_id' => $students[1]->student_id,
                    'read_at' => now()->subDays(2),
                ]);
            }
        }

        // Create Notifications
        $notifications = [
            [
                'title' => 'Your account has been created',
                'message' => 'Welcome to UCSC Graduate Studies Portal! Your account has been successfully created. Please complete your profile and explore available examinations.',
                'exam_id' => null,
                'user_id' => null,
                'is_for_all' => true,
                'created_at' => now()->subDays(5),
            ],
        ];

        foreach ($notifications as $notificationData) {
            $notification = Notification::create($notificationData);
            $this->command->info("Created notification: {$notification->title}");
        }

        $this->command->info('Notifications and announcements seeded successfully!');
    }
}
