<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitPrintJob;
use App\Services\PrintRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

class PrintJobController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string'],
            'file_path' => ['required', 'string'],
        ]);

        $queueName = app(PrintRouter::class)->resolve($validated['filename']);

        if ($queueName === null) {
            return response()->json([
                'message' => 'No printer queue could be resolved for this filename.',
            ], 422);
        }

        Queue::dispatch(new SubmitPrintJob(
            filePath: $validated['file_path'],
            queue: $queueName,
        ));

        return response()->json([
            'message' => 'Print job queued.',
            'queue' => $queueName,
        ]);
    }
}
