<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    #[OA\Get(
        path: '/audit-log',
        summary: 'Riwayat aktivitas (audit log)',
        tags: ['Audit Log'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'log_name', in: 'query', description: 'Filter berdasarkan modul: user, wajib_pajak, kewajiban_pajak, pembayaran', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'event', in: 'query', description: 'Filter event: created, updated, deleted', schema: new OA\Schema(type: 'string', enum: ['created', 'updated', 'deleted'])),
            new OA\Parameter(name: 'causer_id', in: 'query', description: 'Filter berdasarkan ID user yang melakukan aksi', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'subject_id', in: 'query', description: 'Filter berdasarkan ID resource yang diubah', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar audit log'),
            new OA\Response(response: 401, description: 'Tidak terautentikasi', content: new OA\JsonContent(ref: '#/components/schemas/Error401')),
            new OA\Response(response: 403, description: 'Hanya ADMIN', content: new OA\JsonContent(ref: '#/components/schemas/Error403')),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with('causer:id,username,role')
            ->latest();

        if ($logName = $request->query('log_name')) {
            $query->inLog($logName);
        }

        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }

        if ($causerId = $request->query('causer_id')) {
            $query->causedBy(\App\Models\User::find($causerId));
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        $data = $query->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }
}
