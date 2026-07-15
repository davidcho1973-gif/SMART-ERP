<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EquipmentPhoto;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serve an equipment photo — only to equipment roles. */
class EquipmentPhotoController extends Controller
{
    public function __invoke(Request $request, EquipmentPhoto $photo): Response
    {
        abort_unless(! empty($photo->att_path), 404);

        $user = Auth::user();
        $roles = [];
        if ($user) {
            $roles[] = $user->access;
            if ($user->employee_id && ($e = Employee::find($user->employee_id))) {
                $roles[] = $e->role;
            }
        }
        abort_unless(Access::allows($roles, 'equipment.checkout') || Access::allows($roles, 'equipment.manage'), 403);

        $disk = Storage::disk($photo->att_disk ?: config('filesystems.default'));
        abort_unless($disk->exists($photo->att_path), 404);

        $headers = ['Content-Type' => $photo->att_mime ?: 'application/octet-stream'];
        $name = $photo->att_name ?: 'photo';
        if ($photo->isImage() && ! $request->boolean('dl')) {
            return $disk->response($photo->att_path, $name, $headers, 'inline');
        }

        return $disk->download($photo->att_path, $name, $headers);
    }
}
