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

            try {
                $imageUrl = $service->generate(
                    $paper->description ?: $paper->name,
                    $accessory->description ?: $accessory->name,
                    $card->description ?: $card->name
                );

                // Đảm bảo imageUrl luôn có giá trị
                if (empty($imageUrl)) {
                    Log::warning('Service returned empty image URL, generating placeholder');
                    // Tạo placeholder nếu service trả về empty
                    $imageUrl = $service->generatePlaceholder(
                        $paper->description ?: $paper->name,
                        $accessory->description ?: $accessory->name,
                        $card->description ?: $card->name
                    );
                }

                Log::info('Gift preview generated successfully', [
                    'image_url' => substr($imageUrl, 0, 100) . '...', // Log một phần để tránh quá dài
                    'is_placeholder' => strpos($imageUrl, 'data:image/svg+xml') !== false
                ]);

                return response()->json([
                    'success' => true,
                    'image_url' => $imageUrl,
                    'is_placeholder' => strpos($imageUrl, 'data:image/svg+xml') !== false
                ]);
            } catch (\Exception $e) {
                // Nếu có lỗi trong quá trình generate, vẫn trả về placeholder
                Log::error('Error in generate method, using placeholder fallback', [
                    'error' => $e->getMessage()
                ]);
                
                // Tạo placeholder như fallback cuối cùng
                $imageUrl = $service->generatePlaceholder(
                    $paper->description ?: $paper->name,
                    $accessory->description ?: $accessory->name,
                    $card->description ?: $card->name
                );
                
                return response()->json([
                    'success' => true,
                    'image_url' => $imageUrl,
                    'is_placeholder' => true,
                    'message' => 'Đã tạo preview placeholder do lỗi kỹ thuật'
                ]);
            }
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

