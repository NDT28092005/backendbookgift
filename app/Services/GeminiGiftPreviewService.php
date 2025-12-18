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
        
        // Tạo prompt ngắn gọn và hiệu quả hơn (rút ngắn để tránh lỗi)
        $prompt = "Professional product photography of a beautifully wrapped gift box, photorealistic, e-commerce style. A rectangular gift box wrapped with {$paperDescEn} wrapping paper, perfectly folded with crisp edges. A decorative {$accessoryDescEn} elegantly placed on top center. A {$cardDescEn} greeting card attached to the front. Clean white background, soft studio lighting from top-left, 45-degree angle view, high resolution, sharp focus, natural shadows. Photorealistic, no illustration or cartoon style, accurate colors, no watermarks or text overlays, single gift box as main subject.";

        // Thử sử dụng Stability AI (miễn phí với giới hạn)
        $stabilityApiKey = trim(config('services.stability.key', ''));
        
        Log::info('Checking Stability AI configuration', [
            'has_api_key' => !empty($stabilityApiKey),
            'api_key_length' => $stabilityApiKey ? strlen($stabilityApiKey) : 0,
            'api_key_preview' => $stabilityApiKey ? substr($stabilityApiKey, 0, 10) . '...' : 'not set',
            'api_key_starts_with' => $stabilityApiKey ? substr($stabilityApiKey, 0, 3) : 'none'
        ]);
        
        if (!empty($stabilityApiKey)) {
            try {
                Log::info('Attempting to generate image with Stability AI', [
                    'prompt_length' => strlen($prompt),
                    'paper_desc' => $paperDescEn,
                    'accessory_desc' => $accessoryDescEn,
                    'card_desc' => $cardDescEn
                ]);
                
                $result = $this->generateWithStabilityAI($prompt);
                
                // Kiểm tra nếu result hợp lệ (không null và không phải placeholder)
                if ($result && strpos($result, 'data:image/svg+xml') === false) {
                    Log::info('Stability AI generated image successfully', [
                        'result_url' => substr($result, 0, 100)
                    ]);
                    return $result;
                }
                
                // Nếu result là null hoặc placeholder, fallback
                Log::warning('Stability AI returned null or placeholder', [
                    'result' => $result ? substr($result, 0, 50) : 'null',
                    'is_placeholder' => $result ? (strpos($result, 'data:image/svg+xml') !== false) : false
                ]);
            } catch (\Exception $e) {
                Log::error('Stability AI failed with exception, using placeholder', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Fallback to placeholder nếu API fail hoặc trả về null
            Log::info('Falling back to placeholder image');
            return $this->generatePlaceholder($paperDesc, $accessoryDesc, $cardDesc);
        } else {
            Log::warning('Stability AI API key not configured, using placeholder');
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
            $apiKey = trim(config('services.stability.key', ''));
            
            if (empty($apiKey)) {
                Log::warning('Stability AI API key is empty or invalid');
                return null; // Return null để trigger fallback
            }
            
            // Đảm bảo prompt không quá dài (Stability AI có giới hạn ~1000 ký tự cho prompt chính)
            $maxPromptLength = 1000;
            if (strlen($prompt) > $maxPromptLength) {
                Log::warning('Prompt too long, truncating', [
                    'original_length' => strlen($prompt),
                    'max_length' => $maxPromptLength
                ]);
                $prompt = substr($prompt, 0, $maxPromptLength);
            }
            
            Log::info('Using prompt for Stability AI', [
                'prompt_length' => strlen($prompt),
                'prompt_preview' => substr($prompt, 0, 100) . '...'
            ]);

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
                        
                        Log::info('Calling Stability AI v1 endpoint', [
                            'endpoint' => $endpoint,
                            'prompt_length' => strlen($prompt),
                            'api_key_set' => !empty($apiKey)
                        ]);
                        
                        $response = Http::timeout(120)
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
                                'cfg_scale' => 7, // Giảm xuống 7 để ổn định hơn
                                'height' => 1024,
                                'width' => 1024,
                                'samples' => 1,
                                'steps' => 30, // Giảm xuống 30 để nhanh hơn và ổn định hơn
                                'style_preset' => 'photographic',
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
                        
                        Log::info('Calling Stability AI v2beta endpoint', [
                            'endpoint' => $endpoint,
                            'prompt_length' => strlen($prompt),
                            'api_key_set' => !empty($apiKey)
                        ]);
                        
                        $response = Http::timeout(120)
                            ->withHeaders([
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Accept' => 'image/png',
                            ])
                            ->asMultipart()
                            ->post($endpoint, $multipartData);
                    }

                    Log::info('Stability AI response', [
                        'status' => $response->status(),
                        'endpoint' => $endpoint,
                        'has_body' => !empty($response->body()),
                        'body_length' => strlen($response->body()),
                    ]);

                    if ($response->successful()) {
                        // V1 API trả về JSON với base64, v2beta trả về binary
                        $imageData = null;
                        
                        if (strpos($endpoint, 'v1') !== false) {
                            $json = $response->json();
                            Log::info('Stability AI v1 response structure', [
                                'has_artifacts' => isset($json['artifacts']),
                                'artifacts_count' => isset($json['artifacts']) ? count($json['artifacts']) : 0,
                                'has_base64' => isset($json['artifacts'][0]['base64']),
                            ]);
                            
                            if (isset($json['artifacts'][0]['base64'])) {
                                $imageData = base64_decode($json['artifacts'][0]['base64']);
                                Log::info('Decoded image data', [
                                    'size' => strlen($imageData),
                                    'is_valid' => !empty($imageData)
                                ]);
                            } else {
                                Log::warning('No base64 data in v1 response', [
                                    'json_keys' => array_keys($json),
                                    'artifacts_structure' => isset($json['artifacts']) ? json_encode($json['artifacts']) : 'not set'
                                ]);
                            }
                        } else {
                            // v2beta trả về binary trực tiếp
                            $imageData = $response->body();
                            Log::info('Stability AI v2beta binary response', [
                                'size' => strlen($imageData),
                                'is_valid' => !empty($imageData) && strlen($imageData) > 100 // Ít nhất phải có 100 bytes
                            ]);
                        }
                        
                        if (empty($imageData) || strlen($imageData) < 100) {
                            Log::warning('Empty or invalid image data from Stability AI', [
                                'data_size' => strlen($imageData ?? ''),
                                'endpoint' => $endpoint
                            ]);
                            continue; // Thử endpoint tiếp theo
                        }

                        // Lưu file
                        $path = 'gift-previews/' . Str::uuid() . '.png';
                        $saved = Storage::disk('public')->put($path, $imageData);

                        if ($saved) {
                            Log::info('Image saved successfully', [
                                'path' => $path,
                                'size' => strlen($imageData),
                                'url' => asset('storage/' . $path)
                            ]);
                            
                            // Sử dụng asset() để đảm bảo URL đúng
                            return asset('storage/' . $path);
                        } else {
                            Log::error('Failed to save image to storage', ['path' => $path]);
                            continue;
                        }
                    } else {
                        // Lỗi từ API
                        $errorBody = $response->body();
                        $error = null;
                        
                        // Thử parse JSON error
                        try {
                            $error = $response->json();
                        } catch (\Exception $e) {
                            $error = $errorBody;
                        }
                        
                        // Xử lý error có thể là string hoặc array
                        if (is_array($error)) {
                            if (isset($error['errors']) && is_array($error['errors'])) {
                                $lastError = implode(', ', $error['errors']);
                            } elseif (isset($error['message'])) {
                                $lastError = $error['message'];
                            } elseif (isset($error['errors'])) {
                                $lastError = is_array($error['errors']) ? implode(', ', $error['errors']) : $error['errors'];
                            } else {
                                $lastError = 'Unknown error: ' . json_encode($error);
                            }
                        } else {
                            $lastError = (string)$error;
                        }
                        
                        // Tạo status text từ status code
                        $statusCode = $response->status();
                        $statusTexts = [
                            400 => 'Bad Request',
                            401 => 'Unauthorized',
                            403 => 'Forbidden',
                            404 => 'Not Found',
                            429 => 'Too Many Requests',
                            500 => 'Internal Server Error',
                            502 => 'Bad Gateway',
                            503 => 'Service Unavailable'
                        ];
                        $statusText = $statusTexts[$statusCode] ?? 'Unknown Status';
                        
                        Log::warning('Stability AI endpoint failed', [
                            'endpoint' => $endpoint,
                            'status' => $statusCode,
                            'status_text' => $statusText,
                            'error' => $lastError,
                            'error_body' => substr($errorBody, 0, 1000), // Log 1000 ký tự đầu để debug tốt hơn
                            'response_headers' => $response->headers(),
                            'api_key_length' => strlen($apiKey),
                            'prompt_length' => strlen($prompt)
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
