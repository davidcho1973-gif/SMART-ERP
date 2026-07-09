<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Message;
use App\Support\Comms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a comms message's file attachment — only to members of the channel it
 * was posted in. Images serve inline (so they preview in chat); everything else
 * downloads. The file lives on the object-storage disk; nothing is public.
 */
class CommsFileController extends Controller
{
    public function __invoke(Request $request, Message $message): Response
    {
        abort_unless($message->hasFile(), 404);

        $eid = Auth::user()?->employee_id;
        $me = $eid ? Employee::find($eid) : null;
        $channel = $message->channel;
        abort_unless($me && $channel && Comms::canAccess($channel, $me), 403);

        $disk = Storage::disk($message->att_disk ?: config('filesystems.default'));
        abort_unless($disk->exists($message->att_path), 404);

        $headers = ['Content-Type' => $message->att_mime ?: 'application/octet-stream'];
        $name = $message->att_name ?: 'file';

        // images preview inline; docs (or ?dl=1) download
        if ($message->isImage() && ! $request->boolean('dl')) {
            return $disk->response($message->att_path, $name, $headers, 'inline');
        }

        return $disk->download($message->att_path, $name, $headers);
    }
}
