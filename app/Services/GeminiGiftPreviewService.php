<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeminiGiftPreviewService
{
    /**
     * Generate gift preview image
     * 
     * LƯU Ý: Gemini API không hỗ trợ text-to-image generation.
     * Service này sẽ sử dụng Stability AI hoặc một API tạo ảnh khác.
     */
    public function generate($paperDesc, $accessoryDesc, $cardDesc)
    {
        // Dịch mô tả sang tiếng Anh để API v1 hỗ trợ
        // Tạm thời dùng mô tả tiếng Anh cơ bản
        $paperDescEn = $this->translateToEnglish($paperDesc);
        $accessoryDescEn = $this->translateToEnglish($accessoryDesc);
        $cardDescEn = $this->translateToEnglish($cardDesc);
        
        // Tạo prompt chi tiết và chân thật hơn với mô tả cụ thể
        $prompt = <<<PROMPT
Professional product photography of a real, beautifully wrapped gift box, highly detailed and photorealistic, e-commerce style.

GIFT BOX COMPOSITION:
- A rectangular gift box (approximately 20cm x 15cm x 10cm) completely wrapped with {$paperDescEn} wrapping paper
- The wrapping paper is perfectly folded with crisp, clean edges and sharp corners, no wrinkles or air bubbles
- The paper pattern and texture are clearly visible and realistic
- A decorative {$accessoryDescEn} is elegantly placed on the center top of the gift box, naturally draped or positioned
- The accessory is properly secured and looks realistic, with natural folds and positioning
- A {$cardDescEn} greeting card is attached to the front or side of the gift box with a small ribbon or tape
- The card is partially visible, showing its design and material texture
- All three elements (wrapping paper, accessory, card) are harmoniously combined and look like a real, professionally wrapped gift

PHOTOGRAPHY SPECIFICATIONS:
- Professional e-commerce product photography style
- Soft, diffused studio lighting from top-left and front-right, creating natural depth
- Clean, seamless pure white background (#FFFFFF), no shadows on background
- Shot from 45-degree angle (slightly elevated) showing the top surface and front face of the box
- High resolution, ultra-sharp focus, 8K quality, every detail crisp and clear
- Natural, soft shadows beneath the box for depth and realism
- Perfect composition with the gift box centered, taking up 70% of the frame
- Professional depth of field: box in sharp focus, background completely white

MATERIAL REALISM:
- Wrapping paper: Realistic paper texture, visible grain, accurate colors and patterns as described
- Accessory: Realistic material texture (fabric, ribbon, or decorative element), natural appearance
- Card: Realistic card stock texture, visible paper quality, readable but not overly detailed text
- All materials look authentic and match real-world products

TECHNICAL REQUIREMENTS:
- Photorealistic rendering, absolutely no illustration, cartoon, or artistic style
- Accurate colors that match the described materials exactly
- Natural lighting with soft, realistic shadows
- No text overlays, watermarks, labels, or branding
- No human hands, people, or other objects in the frame
- Single gift box as the sole subject, perfectly presented
- No distortion, blur, or artifacts
- Commercial product photography quality, ready for e-commerce use

STYLE: Professional product photography, e-commerce photography, commercial photography, realistic, detailed, high quality, photorealistic, studio photography
PROMPT;

        // Thử sử dụng Stability AI (miễn phí với giới hạn)
        $stabilityApiKey = config('services.stability.key');
        
        if ($stabilityApiKey) {
            try {
                $result = $this->generateWithStabilityAI($prompt);
                // Kiểm tra nếu result hợp lệ (không null và không phải placeholder)
                if ($result && strpos($result, 'data:image/svg+xml') === false) {
                    return $result;
                }
                // Nếu result là null hoặc placeholder, fallback
                Log::info('Stability AI returned null or placeholder, using fallback');
            } catch (\Exception $e) {
                Log::error('Stability AI failed, using placeholder', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Fallback to placeholder nếu API fail hoặc trả về null
            return $this->generatePlaceholder($paperDesc, $accessoryDesc, $cardDesc);
        }

        // Fallback: Tạo placeholder image hoặc sử dụng service khác
        return $this->generatePlaceholder($paperDesc, $accessoryDesc, $cardDesc);
    }

    /**
     * Generate image using Stability AI
     */
    private function generateWithStabilityAI($prompt)
    {
        try {
            $apiKey = config('services.stability.key');
            
            if (empty($apiKey)) {
                Log::warning('Stability AI API key is empty');
                return null; // Return null để trigger fallback
            }

            Log::info('Calling Stability AI', ['prompt_length' => strlen($prompt)]);
            
            // Thử endpoint v1 trước (ổn định hơn)
            $endpoints = [
                'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image',
                'https://api.stability.ai/v2beta/stable-image/generate/core',
            ];
            
            $lastError = null;
            
            foreach ($endpoints as $endpoint) {
                try {
                    Log::info('Trying endpoint', ['endpoint' => $endpoint]);
                    
                    if (strpos($endpoint, 'v1') !== false) {
                        // API v1 format với negative prompt
                        $negativePrompt = "blurry, low quality, distorted, deformed, cartoon, illustration, drawing, sketch, watermark, text overlay, multiple boxes, hands, people, cluttered background, bad lighting, oversaturated, unrealistic colors, abstract art, painting";
                        
                        $response = Http::timeout(90)
                            ->withHeaders([
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ])
                            ->post($endpoint, [
                                'text_prompts' => [
                                    [
                                        'text' => $prompt,
                                        'weight' => 1.0
                                    ],
                                    [
                                        'text' => $negativePrompt,
                                        'weight' => -1.0
                                    ]
                                ],
                                'cfg_scale' => 9, // Tăng từ 7 lên 9 để tuân thủ prompt tốt hơn
                                'height' => 1024,
                                'width' => 1024,
                                'samples' => 1,
                                'steps' => 40, // Tăng từ 30 lên 40 để có chi tiết tốt hơn
                                'style_preset' => 'photographic', // Thêm style preset cho ảnh chân thật
                            ]);
                    } else {
                        // API v2beta format - YÊU CẦU multipart/form-data
                        // Sử dụng asMultipart() với array format đúng
                        $negativePrompt = "blurry, low quality, distorted, deformed, cartoon, illustration, drawing, sketch, watermark, text overlay, multiple boxes, hands, people, cluttered background, bad lighting, oversaturated, unrealistic colors, abstract art, painting";
                        
                        $multipartData = [
                            [
                                'name' => 'prompt',
                                'contents' => $prompt
                            ],
                            [
                                'name' => 'negative_prompt',
                                'contents' => $negativePrompt
                            ],
                            [
                                'name' => 'output_format',
                                'contents' => 'png'
                            ],
                            [
                                'name' => 'aspect_ratio',
                                'contents' => '1:1'
                            ],
                            [
                                'name' => 'mode',
                                'contents' => 'generate'
                            ],
                            [
                                'name' => 'model',
                                'contents' => 'stable-core-1.6'
                            ],
                            [
                                'name' => 'seed',
                                'contents' => rand(0, 4294967295) // Random seed để có variation
                            ],
                        ];
                        
                        $response = Http::timeout(90)
                            ->withHeaders([
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Accept' => 'image/png',
                            ])
                            ->asMultipart()
                            ->post($endpoint, $multipartData);
                    }

                    Log::info('Stability AI response', [
                        'status' => $response->status(),
                        'headers' => $response->headers(),
                    ]);

                    if ($response->successful()) {
                        // V1 API trả về JSON với base64, v2beta trả về binary
                        $imageData = null;
                        
                        if (strpos($endpoint, 'v1') !== false) {
                            $json = $response->json();
                            if (isset($json['artifacts'][0]['base64'])) {
                                $imageData = base64_decode($json['artifacts'][0]['base64']);
                            }
                        } else {
                            $imageData = $response->body();
                        }
                        
                        if (empty($imageData)) {
                            Log::warning('Empty image data from Stability AI');
                            continue; // Thử endpoint tiếp theo
                        }

                        // Lưu file
        $path = 'gift-previews/' . Str::uuid() . '.png';
                        Storage::disk('public')->put($path, $imageData);

                        Log::info('Image saved successfully', ['path' => $path]);
                        
                        // Sử dụng asset() để đảm bảo URL đúng
                        return asset('storage/' . $path);
                    } else {
                        $error = $response->json();
                        // Xử lý error có thể là string hoặc array
                        if (isset($error['errors']) && is_array($error['errors'])) {
                            $lastError = implode(', ', $error['errors']);
                        } elseif (isset($error['message'])) {
                            $lastError = $error['message'];
                        } elseif (isset($error['errors'])) {
                            $lastError = is_array($error['errors']) ? implode(', ', $error['errors']) : $error['errors'];
                        } else {
                            $lastError = 'Unknown error: ' . json_encode($error);
                        }
                        
                        Log::warning('Stability AI endpoint failed', [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'error' => $lastError,
                            'full_response' => $error
                        ]);
                        continue; // Thử endpoint tiếp theo
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::warning('Stability AI endpoint exception', [
                        'endpoint' => $endpoint,
                        'error' => $lastError
                    ]);
                    continue;
                }
            }
            
            // Nếu tất cả endpoints đều fail, không throw exception mà return null
            // để method generate() có thể fallback về placeholder
            $errorMsg = is_array($lastError) ? implode(', ', $lastError) : (string)$lastError;
            Log::warning('All Stability AI endpoints failed', [
                'error' => $errorMsg
            ]);
            return null; // Return null để trigger fallback trong generate()
            
        } catch (\Exception $e) {
            Log::error('Stability AI generation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Return null thay vì throw để có thể fallback
            return null;
        }
    }

    /**
     * Generate placeholder image (fallback solution)
     * Tạo một placeholder đơn giản hoặc sử dụng service khác
     */
    public function generatePlaceholder($paperDesc, $accessoryDesc, $cardDesc)
    {
        // Tạo SVG placeholder và convert thành base64 data URI
        // Điều này tránh vấn đề serve file từ storage
        $svg = $this->createSVGPlaceholder($paperDesc, $accessoryDesc, $cardDesc);
        
        // Encode SVG thành base64 data URI
        $base64 = base64_encode($svg);
        return 'data:image/svg+xml;base64,' . $base64;
    }

    /**
     * Translate Vietnamese to English (improved mapping)
     */
    private function translateToEnglish($text)
    {
        // Extended translation mapping for common gift terms
        $translations = [
            // Wrapping papers
            'giấy kraft' => 'kraft paper',
            'giấy gói' => 'wrapping paper',
            'giấy bọc' => 'wrapping paper',
            'giấy màu' => 'colored wrapping paper',
            'giấy hoa' => 'floral wrapping paper',
            'giấy kẻ sọc' => 'striped wrapping paper',
            'giấy chấm bi' => 'polka dot wrapping paper',
            'giấy vàng' => 'gold wrapping paper',
            'giấy đỏ' => 'red wrapping paper',
            'giấy xanh' => 'blue wrapping paper',
            'giấy hồng' => 'pink wrapping paper',
            
            // Accessories
            'nơ' => 'ribbon bow',
            'nơ ruy băng' => 'ribbon bow',
            'ruy băng' => 'ribbon',
            'dây ruy băng' => 'ribbon',
            'nơ đỏ' => 'red ribbon bow',
            'nơ vàng' => 'gold ribbon bow',
            'nơ hồng' => 'pink ribbon bow',
            'phụ kiện' => 'decorative accessory',
            'phụ kiện trang trí' => 'decorative accessory',
            'hoa trang trí' => 'decorative flower',
            'lá trang trí' => 'decorative leaf',
            'quả thông' => 'pine cone',
            'ngôi sao' => 'star',
            
            // Cards
            'thiệp' => 'greeting card',
            'thiệp chúc mừng' => 'greeting card',
            'thiệp kraft' => 'kraft greeting card',
            'thiệp trắng' => 'white greeting card',
            'thiệp màu' => 'colored greeting card',
            'thiệp hoa' => 'floral greeting card',
        ];
        
        $textLower = mb_strtolower(trim($text), 'UTF-8');
        
        // Thử tìm exact match hoặc partial match
        foreach ($translations as $vn => $en) {
            if (strpos($textLower, $vn) !== false) {
                // Nếu text chỉ chứa từ khóa, trả về bản dịch
                if (trim($textLower) === $vn || strpos($textLower, $vn) === 0) {
                    return $en;
                }
                // Nếu text chứa từ khóa, thay thế nó
                $textLower = str_replace($vn, $en, $textLower);
            }
        }
        
        // Nếu không tìm thấy translation, trả về text gốc (có thể đã là tiếng Anh hoặc cần giữ nguyên)
        return $text;
    }

    /**
     * Create SVG placeholder
     */
    private function createSVGPlaceholder($paperDesc, $accessoryDesc, $cardDesc)
    {
        // Escape text để tránh XSS và lỗi XML
        $paperDesc = htmlspecialchars($paperDesc, ENT_XML1, 'UTF-8');
        $accessoryDesc = htmlspecialchars($accessoryDesc, ENT_XML1, 'UTF-8');
        $cardDesc = htmlspecialchars($cardDesc, ENT_XML1, 'UTF-8');
        
        return <<<SVG
<svg width="800" height="800" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800">
  <defs>
    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#FB6376;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#FCB1A6;stop-opacity:1" />
    </linearGradient>
    <filter id="shadow">
      <feDropShadow dx="0" dy="4" stdDeviation="8" flood-opacity="0.2"/>
    </filter>
  </defs>
  <rect width="800" height="800" fill="url(#grad1)"/>
  <rect x="150" y="200" width="500" height="400" fill="#fff" rx="25" opacity="0.95" filter="url(#shadow)"/>
  <text x="400" y="320" font-family="Arial, sans-serif" font-size="64" font-weight="bold" text-anchor="middle" fill="#5D2A42">🎁</text>
  <text x="400" y="370" font-family="Arial, sans-serif" font-size="24" font-weight="bold" text-anchor="middle" fill="#5D2A42">Gói Quà Tặng</text>
  <line x1="250" y1="400" x2="550" y2="400" stroke="#FB6376" stroke-width="2" opacity="0.3"/>
  <text x="400" y="430" font-family="Arial, sans-serif" font-size="16" text-anchor="middle" fill="#666">Giấy gói: {$paperDesc}</text>
  <text x="400" y="460" font-family="Arial, sans-serif" font-size="16" text-anchor="middle" fill="#666">Phụ kiện: {$accessoryDesc}</text>
  <text x="400" y="490" font-family="Arial, sans-serif" font-size="16" text-anchor="middle" fill="#666">Thiệp: {$cardDesc}</text>
  <text x="400" y="540" font-family="Arial, sans-serif" font-size="13" text-anchor="middle" fill="#999" font-style="italic">Preview sẽ được tạo tự động khi có API key</text>
</svg>
SVG;
    }
}
