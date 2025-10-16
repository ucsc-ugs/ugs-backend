<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Announcement;
use Carbon\Carbon;

class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-scheduled';
    protected $description = 'Publish scheduled announcements whose publish_date has passed';

    public function handle()
    {
        $now = Carbon::now();
        $this->info('Current time: ' . $now->toDateTimeString());
        $announcements = Announcement::where('status', 'scheduled')
            ->where('publish_date', '<=', $now)
            ->get();

        if ($announcements->isEmpty()) {
            $this->info('No scheduled announcements found to publish.');
        } else {
            foreach ($announcements as $announcement) {
                $this->info('Publishing announcement ID: ' . $announcement->id . ' | Title: ' . $announcement->title . ' | Publish Date: ' . $announcement->publish_date . ' | Status: ' . $announcement->status);
                $announcement->status = 'published';
                $announcement->save();
            }
            $this->info('Published ' . $announcements->count() . ' scheduled announcements.');
        }
    }
}
