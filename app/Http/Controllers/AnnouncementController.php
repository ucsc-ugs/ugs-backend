<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\Announcement;
use Illuminate\Support\Facades\Artisan;
use App\Traits\CreatesNotifications;

class AnnouncementController extends Controller
{
    use CreatesNotifications;
    public function index()
    {
        // Update scheduled announcements whose publish_date has passed
        $now = now();
        $scheduled = \App\Models\Announcement::where('status', 'scheduled')
            ->where('publish_date', '<=', $now)
            ->get();

        foreach ($scheduled as $announcement) {
            $announcement->status = 'published';
            $announcement->save();
        }

        // Return all announcements, no user filter
        return response()->json(\App\Models\Announcement::all());
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'audience' => 'required|in:all,exam-specific',
                'exam_id' => 'nullable|integer',
                // department_id and year_level removed
                'expiry_date' => 'required|date',
                'publish_date' => 'nullable|date',
                'status' => 'required|in:published,draft,scheduled',
                'priority' => 'required|in:low,medium,high,urgent',
                'category' => 'required|in:general,exam,academic,administrative,emergency',
                'tags' => 'nullable|array',
                'is_pinned' => 'required|boolean',
                'created_by' => 'nullable|integer',
            ]);

            $announcement = Announcement::create($validated);

            // Create a notification for the new announcement
            $this->createNotification(
                $announcement->title,
                $announcement->message,
                $announcement->exam_id ?? null,
                null,
                ($announcement->audience === 'all')
            );

            // Immediately trigger the scheduled command after creation
            Artisan::call('announcements:publish-scheduled');

            return response()->json($announcement, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $announcement = \App\Models\Announcement::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'audience' => 'required|in:all,exam-specific',
            'exam_id' => 'nullable',
            // department_id and year_level removed
            'expiry_date' => 'required|date',
            'publish_date' => 'nullable|date',
            'status' => 'required|string',
            'priority' => 'required|string',
            'category' => 'required|string',
            'tags' => 'nullable|array',
            'is_pinned' => 'boolean',
            'created_by' => 'nullable|integer',
        ]);

        $announcement->update($validated);

        return response()->json($announcement);
    }

    public function destroy($id)
    {
        $announcement = \App\Models\Announcement::findOrFail($id);
        $announcement->delete();
        return response()->json(['message' => 'Announcement deleted successfully.']);
    }
}
