<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->when($request->filled('user'), function ($query) use ($request) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->user}%")
                        ->orWhere('email', 'like', "%{$request->user}%");
                });
            })
            ->when($request->filled('event'), function ($query) use ($request) {
                $query->where('event', $request->event);
            })
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('auditable_type', $request->type);
            })
            ->when($request->filled('date_from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->date_to);
            });

        $auditLogs = $query->latest()->paginate(20);

        $events = AuditLog::distinct('event')->pluck('event');
        $types = AuditLog::distinct('auditable_type')
            ->pluck('auditable_type')
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => class_basename($type),
                ];
            });

        return view('admin.audit-logs.index', compact('auditLogs', 'events', 'types'));
    }

    public function show(AuditLog $auditLog)
    {
        return view('admin.audit-logs.show', compact('auditLog'));
    }

    public function export(Request $request)
    {
        $filename = 'audit-logs-' . now()->format('Y-m-d') . '.csv';
        
        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');
            
            // Headers
            fputcsv($handle, [
                'ID',
                'User',
                'Event',
                'Model',
                'Model ID',
                'Changes',
                'IP Address',
                'User Agent',
                'Date',
            ]);

            // Query chunks of 1000 records
            AuditLog::with('user')
                ->when($request->filled('user'), function ($query) use ($request) {
                    $query->whereHas('user', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->user}%")
                            ->orWhere('email', 'like', "%{$request->user}%");
                    });
                })
                ->when($request->filled('event'), function ($query) use ($request) {
                    $query->where('event', $request->event);
                })
                ->when($request->filled('type'), function ($query) use ($request) {
                    $query->where('auditable_type', $request->type);
                })
                ->when($request->filled('date_from'), function ($query) use ($request) {
                    $query->whereDate('created_at', '>=', $request->date_from);
                })
                ->when($request->filled('date_to'), function ($query) use ($request) {
                    $query->whereDate('created_at', '<=', $request->date_to);
                })
                ->orderBy('id')
                ->chunk(1000, function ($logs) use ($handle) {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->id,
                            $log->user ? $log->user->name : 'System',
                            $log->event,
                            class_basename($log->auditable_type),
                            $log->auditable_id,
                            json_encode($log->changes),
                            $log->ip_address,
                            $log->user_agent,
                            $log->created_at->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename);
    }
}
