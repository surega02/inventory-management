<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** Admin only (role:admin on the route). Read-only: activity_log is never edited from the UI. */
class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogService $audit): Response
    {
        $filters = $request->validate([
            'causer_id' => ['nullable', 'integer'],
            'subject_type' => ['nullable', Rule::in(array_keys(AuditLogService::SUBJECTS))],
            'event' => ['nullable', Rule::in(AuditLogService::EVENTS)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return Inertia::render('audit-logs/index', [
            'filters' => $filters,
            'entries' => $audit->list($filters)->through(fn (Activity $a) => [
                'id' => $a->id,
                'at' => $a->created_at?->toIso8601String(),
                'causer' => $a->causer instanceof User ? $a->causer->name : null,
                'event' => $a->event,
                'description' => $a->description,
                'subject' => $audit->subject($a),
                'changes' => [
                    'old' => $a->attribute_changes?->get('old') ?? [],
                    'new' => $a->attribute_changes?->get('attributes') ?? [],
                ],
                'properties' => $a->properties?->all() ?? [],
            ]),
            'causers' => $audit->causers(),
            'subjectTypes' => array_keys(AuditLogService::SUBJECTS),
            'events' => AuditLogService::EVENTS,
        ]);
    }
}
