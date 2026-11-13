<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Services\ProductExcelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportExportController extends Controller
{
    protected ProductExcelService $excelService;

    public function __construct(ProductExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    /**
     * Upload and validate the Excel file structure & preview rows.
     */
    public function validateAndPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:25600', // max 25MB
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
     * Confirm and execute the batch import.
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
     * Export products to Excel matching standard 36 ILP columns.
     */
    public function export(Request $request): StreamedResponse
    {
        return $this->excelService->export($request);
    }

    /**
     * Download standard 36-column template.
     */
    public function downloadTemplate(): StreamedResponse
    {
        return $this->excelService->generateTemplate();
    }
}
