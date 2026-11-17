<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => [
                'nullable',
                'date',
                Rule::when(
                    $request->filled('from'),
                    ['after_or_equal:from'],
                ),
            ],
        ]);

        $logs = $this->filteredQuery($filters)
            ->with('actor:id,name')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(static fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'action_label' => Str::of($log->action)->replace('.', ' ')->headline()->toString(),
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                ] : null,
                'actor_name' => $log->actor_name,
                'subject_type' => $log->subject_type === null
                    ? null
                    : Str::headline(class_basename($log->subject_type)),
                'subject_id' => $log->subject_id,
                'subject_label' => $log->subject_label,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ]);

        return Inertia::render('audit-logs/index', [
            'logs' => $logs,
            'actions' => AuditLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
            'stats' => [
                'total' => AuditLog::query()->count(),
                'today' => AuditLog::query()->whereDate('created_at', today())->count(),
                'actions' => AuditLog::query()->distinct()->count('action'),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'action' => $filters['action'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('action', 'like', "%{$search}%")
                        ->orWhere('actor_name', 'like', "%{$search}%")
                        ->orWhere('subject_label', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('metadata', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['action'] ?? null,
                fn (Builder $query, string $action) => $query->where('action', $action),
            )
            ->when(
                $filters['from'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date),
            )
            ->when(
                $filters['to'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date),
            );
    }
}
