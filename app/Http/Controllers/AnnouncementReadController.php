<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AnnouncementRead;
use Illuminate\Support\Facades\Auth;

class AnnouncementReadController extends Controller
{
    public function markAsRead(Request $request)
    {
        $request->validate([
            'announcement_id' => 'required|exists:announcements,id',
        ]);


        $user = Auth::user();
        $studentId = $user->id;
        $read = AnnouncementRead::firstOrCreate([
            'announcement_id' => $request->announcement_id,
            'student_id' => $studentId,
        ], [
            'read_at' => now(),
        ]);

        return response()->json(['message' => 'Marked as read', 'data' => $read]);
    }
}
