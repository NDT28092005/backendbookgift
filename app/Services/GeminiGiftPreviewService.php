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
        // Dịch mô tả sang tiếng Anh để API hỗ trợ (Stability AI chỉ hỗ trợ tiếng Anh)
        $paperDescEn = $this->translateToEnglish($paperDesc);
        $accessoryDescEn = $this->translateToEnglish($accessoryDesc);
        $cardDescEn = $this->translateToEnglish($cardDesc);
        
        // Đảm bảo tất cả mô tả đều là tiếng Anh (kiểm tra lại sau khi translate)
        if (empty($paperDescEn) || preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i', $paperDescEn)) {
            $paperDescEn = 'wrapping paper';
        }
        if (empty($accessoryDescEn) || preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i', $accessoryDescEn)) {
            $accessoryDescEn = 'decorative ribbon bow';
        }
        if (empty($cardDescEn) || preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i', $cardDescEn)) {
            $cardDescEn = 'greeting card';
        }
        
        Log::info('Translated descriptions', [
            'paper_original' => $paperDesc,
            'paper_translated' => $paperDescEn,
            'accessory_original' => $accessoryDesc,
            'accessory_translated' => $accessoryDescEn,
            'card_original' => $cardDesc,
            'card_translated' => $cardDescEn
        ]);
        
        $prompt = <<<PROMPT
A high-quality product photography of a beautifully wrapped gift box.
Wrapping paper: {$paperDescEn}
Decorative accessory: {$accessoryDescEn}
Greeting card: {$cardDescEn}
The gift is elegantly wrapped, with soft studio lighting, clean white background, professional product photography style, realistic and detailed.
PROMPT;

        // Thử sử dụng Stability AI (miễn phí với giới hạn)
        $stabilityApiKey = trim(config('services.stability.key', ''));
        
        if ($stabilityApiKey) {
            try {
                return $this->generateWithStabilityAI($prompt);
            } catch (\Exception $e) {
                Log::error('Stability AI failed, using placeholder', [
                    'error' => $e->getMessage()
                ]);
                // Fallback to placeholder nếu API fail
                return $this->generatePlaceholder($paperDesc, $accessoryDesc, $cardDesc);
            }
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
                Log::warning('Stability AI API key is empty');
                throw new \Exception('Stability AI API key chưa được cấu hình');
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
                        // API v1 format
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
                                    ]
                                ],
                                'cfg_scale' => 7,
                                'height' => 1024,
                                'width' => 1024,
                                'samples' => 1,
                                'steps' => 30,
                            ]);
                    } else {
                        // API v2beta format - YÊU CẦU multipart/form-data
                        // Sử dụng asMultipart() với array format đúng
                        $multipartData = [
                            [
                                'name' => 'prompt',
                                'contents' => $prompt
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
                        ];
                        
                        $response = Http::timeout(90)
                            ->withHeaders([
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Accept' => 'application/json',
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
                            // v2beta với Accept: application/json sẽ trả về JSON
                            try {
                                $json = $response->json();
                                if (isset($json['image'])) {
                                    $imageData = base64_decode($json['image']);
                                } elseif (isset($json['data'])) {
                                    $imageData = base64_decode($json['data']);
                                } else {
                                    // Nếu không có image trong JSON, thử body
                                    $imageData = $response->body();
                                }
                            } catch (\Exception $e) {
                                // Nếu không parse được JSON, lấy body trực tiếp
                                $imageData = $response->body();
                            }
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
                        // Xử lý error response
                        $error = null;
                        $errorBody = $response->body();
                        
                        try {
                            $error = $response->json();
                        } catch (\Exception $e) {
                            $error = $errorBody;
                        }
                        
                        // Xử lý error có thể là string hoặc array
                        $lastError = null;
                        if (is_array($error)) {
                            if (isset($error['errors']) && is_array($error['errors'])) {
                                $lastError = implode(', ', $error['errors']);
                            } elseif (isset($error['message'])) {
                                $lastError = $error['message'];
                            } elseif (isset($error['errors'])) {
                                $lastError = is_array($error['errors']) ? implode(', ', $error['errors']) : (string)$error['errors'];
                            } else {
                                $lastError = 'Unknown error: ' . json_encode($error);
                            }
                        } else {
                            $lastError = (string)$error;
                        }
                        
                        Log::warning('Stability AI endpoint failed', [
                            'endpoint' => $endpoint,
                            'status' => $response->status(),
                            'error' => $lastError,
                            'error_body' => substr($errorBody, 0, 500)
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
            
            // Nếu tất cả endpoints đều fail
            $errorMsg = is_array($lastError) ? implode(', ', $lastError) : (string)($lastError ?? 'Unknown error');
            throw new \Exception('Tất cả endpoints đều thất bại. Lỗi cuối: ' . $errorMsg);
            
        } catch (\Exception $e) {
            Log::error('Stability AI generation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Fallback to placeholder với thông tin lỗi
            throw $e; // Re-throw để controller có thể xử lý
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
     * Translate Vietnamese to English (simple mapping)
     */
    private function translateToEnglish($text)
    {
        if (empty($text)) {
            return 'default';
        }
        
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
            'knaf' => 'kraft', // Fix typo
            
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
                return $en;
            }
        }
        
        // Nếu vẫn có ký tự tiếng Việt, trả về giá trị mặc định
        if (preg_match('/[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]/i', $text)) {
            // Nếu có tiếng Việt nhưng không match, trả về giá trị mặc định
            if (strpos($textLower, 'giấy') !== false || strpos($textLower, 'paper') !== false) {
                return 'wrapping paper';
            }
            if (strpos($textLower, 'nơ') !== false || strpos($textLower, 'ruy băng') !== false || strpos($textLower, 'ribbon') !== false) {
                return 'ribbon bow';
            }
            if (strpos($textLower, 'thiệp') !== false || strpos($textLower, 'card') !== false) {
                return 'greeting card';
            }
            return 'decorative accessory';
        }
        
        // Nếu không tìm thấy và không có tiếng Việt, trả về text gốc (có thể đã là tiếng Anh)
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
