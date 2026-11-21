<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Services\MemberExcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportExportController extends Controller
{
    protected MemberExcelService $excelService;

    public function __construct(MemberExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Upload and validate the Member Excel file structure & preview rows.
     */
    public function validateAndPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:51200', // max 50MB for tens of thousands of rows
        ]);

        $result = $this->excelService->validateAndPreview($request->file('file'));

        if (!($result['valid'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Format file tidak sesuai standar.',
                'details' => $result,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Struktur file Excel valid.',
            'data' => $result,
        ]);
    }

    /**
     * Confirm and execute the high-speed batch member import.
     */
    public function processImport(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'duplicate_action' => 'nullable|string|in:update,skip',
        ]);

        $action = $request->input('duplicate_action', 'update');
        $result = $this->excelService->processImport($request->input('token'), $action);

        if (!($result['success'] ?? false)) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Export members to Excel matching standard 16 ILP columns.
     */
    public function export(Request $request): StreamedResponse
    {
        return $this->excelService->export($request);
    }

    /**
     * Download standard 16-column template.
     */
    public function downloadTemplate(): StreamedResponse
    {
        return $this->excelService->generateTemplate();
    }
}
