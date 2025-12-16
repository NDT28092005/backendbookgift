<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GeminiGiftPreviewService;
use App\Models\WrappingPaper;
use App\Models\DecorativeAccessory;
use App\Models\CardType;
use Illuminate\Support\Facades\Log;

class GiftPreviewController extends Controller
{
    public function preview(Request $request, GeminiGiftPreviewService $service)
    {
        try {
            // Validation
            $request->validate([
                'wrapping_paper_id' => 'required|integer|exists:wrapping_papers,id',
                'decorative_accessory_id' => 'required|integer|exists:decorative_accessories,id',
                'card_type_id' => 'required|integer|exists:card_types,id',
            ]);

            $paper = WrappingPaper::findOrFail($request->wrapping_paper_id);
            $accessory = DecorativeAccessory::findOrFail($request->decorative_accessory_id);
            $card = CardType::findOrFail($request->card_type_id);

            Log::info('Generating gift preview', [
                'paper' => $paper->name,
                'accessory' => $accessory->name,
                'card' => $card->name
            ]);

            $imageUrl = $service->generate(
                $paper->description ?: $paper->name,
                $accessory->description ?: $accessory->name,
                $card->description ?: $card->name
            );

            Log::info('Gift preview generated successfully', ['image_url' => $imageUrl]);

            return response()->json([
                'success' => true,
                'image_url' => $imageUrl,
                'is_placeholder' => strpos($imageUrl, 'data:image/svg+xml') !== false
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm quà tặng'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Gift preview generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Lỗi khi tạo preview quà tặng'
            ], 500);
        }
    }
}

