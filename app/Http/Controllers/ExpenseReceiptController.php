<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Expense;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve an expense's receipt image — only to accounting/approver roles. Images
 * preview inline; the file lives on private object storage, never public.
 */
class ExpenseReceiptController extends Controller
{
    public function __invoke(Request $request, Expense $expense): Response
    {
        abort_unless($expense->hasReceipt(), 404);

        $user = Auth::user();
        $roles = [];
        if ($user) {
            $roles[] = $user->access;
            if ($user->employee_id && ($e = Employee::find($user->employee_id))) {
                $roles[] = $e->role;
            }
        }
        // anyone who may submit or decide expenses may view the receipt
        $ok = Access::allows($roles, 'expenses.submit') || Access::allows($roles, 'expenses.decide');
        abort_unless($ok, 403);

        $disk = Storage::disk($expense->att_disk ?: config('filesystems.default'));
        abort_unless($disk->exists($expense->att_path), 404);

        $headers = ['Content-Type' => $expense->att_mime ?: 'application/octet-stream'];
        $name = $expense->att_name ?: 'receipt';

        if ($expense->isImage() && ! $request->boolean('dl')) {
            return $disk->response($expense->att_path, $name, $headers, 'inline');
        }

        return $disk->download($expense->att_path, $name, $headers);
    }
}
