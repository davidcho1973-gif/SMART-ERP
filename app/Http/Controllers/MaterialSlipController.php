<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\MaterialBatch;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Serve a materials batch's slip image — only to materials/accounting roles. */
class MaterialSlipController extends Controller
{
    public function __invoke(Request $request, MaterialBatch $batch): Response
    {
        abort_unless($batch->hasImage(), 404);

        $user = Auth::user();
        $roles = [];
        if ($user) {
            $roles[] = $user->access;
            if ($user->employee_id && ($e = Employee::find($user->employee_id))) {
                $roles[] = $e->role;
            }
        }
        $ok = Access::allows($roles, 'materials.submit') || Access::allows($roles, 'materials.decide');
        abort_unless($ok, 403);

        $disk = Storage::disk($batch->att_disk ?: config('filesystems.default'));
        abort_unless($disk->exists($batch->att_path), 404);

        $headers = ['Content-Type' => $batch->att_mime ?: 'application/octet-stream'];
        $name = $batch->att_name ?: 'slip';

        if ($batch->isImage() && ! $request->boolean('dl')) {
            return $disk->response($batch->att_path, $name, $headers, 'inline');
        }

        return $disk->download($batch->att_path, $name, $headers);
    }
}
