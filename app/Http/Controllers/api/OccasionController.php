<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Occasion;
use App\Imports\OccasionsImport;
use App\Exports\OccasionsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class OccasionController extends Controller
{
    public function index()
    {
        return Occasion::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $occasion = Occasion::create($data);
        return response()->json($occasion, 201);
    }

    public function show(Occasion $occasion)
    {
        return $occasion;
    }

    public function update(Request $request, Occasion $occasion)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $occasion->update($data);
        return response()->json($occasion);
    }

    public function destroy(Occasion $occasion)
    {
        $occasion->delete();
        return response()->json(null, 204);
    }

    /**
     * Import occasions from Excel file
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:5120', // Max 5MB
            ]);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy file upload'
                ], 400);
            }

            $file = $request->file('file');
            
            Excel::import(new OccasionsImport, $file);
            
            return response()->json([
                'success' => true,
                'message' => 'Import dịp lễ thành công!'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessages = [];
            foreach ($errors as $field => $messages) {
                $errorMessages = array_merge($errorMessages, $messages);
            }
            return response()->json([
                'success' => false,
                'message' => 'Lỗi validation: ' . implode(', ', $errorMessages),
                'errors' => $errors
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Occasion import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export occasions to Excel file
     */
    public function export()
    {
        return Excel::download(new OccasionsExport, 'occasions_' . date('Y-m-d_His') . '.xlsx');
    }

    /**
     * Delete all occasions
     */
    public function deleteAll()
    {
        try {
            DB::beginTransaction();
            
            $count = Occasion::count();
            Occasion::truncate();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Đã xóa tất cả {$count} dịp lễ thành công!"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}
