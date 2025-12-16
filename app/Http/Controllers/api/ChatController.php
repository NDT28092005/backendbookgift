<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Occasion;
use App\Models\Product;
use App\Events\MessageSent;
use App\Jobs\ProcessAiReply;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function productAdvice(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $message = Str::lower(trim($request->input('message')));
        
        // Lấy tất cả occasions
        $occasions = Occasion::all();
        
        // Nhận diện occasion
        $detectedOccasion = $this->detectOccasion($message, $occasions);
        
        if (!$detectedOccasion) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy dịp lễ phù hợp',
                'occasions' => $occasions->map(fn($occ) => [
                    'id' => $occ->id,
                    'name' => $occ->name,
                    'description' => $occ->description
                ])
            ], 200);
        }

        $products = Product::where('occasion_id', $detectedOccasion->id)
            ->where('is_active', true)
            ->with('images', 'category')
            ->get();

        return response()->json([
            'success' => true,
            'occasion' => [
                'id' => $detectedOccasion->id,
                'name' => $detectedOccasion->name,
                'description' => $detectedOccasion->description
            ],
            'products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'price' => $product->price,
                    'image_url' => $product->image_url,
                    'stock_quantity' => $product->stock_quantity,
                    'category' => $product->category ? $product->category->name : null,
                    'images' => $product->images->pluck('image_url')
                ];
            }),
            'count' => $products->count()
        ]);
    }

    /**
     * Nhận diện dịp lễ từ message
     */
    private function detectOccasion($message, $occasions)
    {
        // Keywords mapping
        $keywords = [
            'sinh nhật' => ['sinh nhật', 'birthday', 'sinh nhat', 'sn'],
            'valentine' => ['valentine', '14/2', '14-2', 'ngày tình nhân'],
            '8/3' => ['8/3', '8-3', 'quốc tế phụ nữ', 'ngày phụ nữ', 'ngay phu nu'],
            '20/10' => ['20/10', '20-10', 'ngày phụ nữ việt nam', 'phu nu viet nam'],
            'giáng sinh' => ['giáng sinh', 'noel', 'christmas', '25/12', '25-12', 'giang sinh'],
            'tết' => ['tết', 'tet', 'nguyên đán', 'nguyen dan', 'năm mới', 'nam moi'],
            'khai trương' => ['khai trương', 'khai truong', 'mở cửa', 'mo cua'],
            'tốt nghiệp' => ['tốt nghiệp', 'tot nghiep', 'graduation', 'ra trường', 'ra truong'],
            'cưới hỏi' => ['cưới', 'cuoi', 'đám cưới', 'dam cuoi', 'wedding'],
            'kỷ niệm' => ['kỷ niệm', 'ky niem', 'anniversary', 'ngày kỷ niệm', 'ngay ky niem'],
            '20/11' => ['20/11', '20-11', 'ngày nhà giáo', 'ngay nha giao', 'thầy cô'],
            '1/6' => ['1/6', '1-6', 'thiếu nhi', 'thieu nhi', 'ngày trẻ em']
        ];

        // 1. Tìm exact match với tên occasion
        foreach ($occasions as $occasion) {
            $occasionName = Str::lower($occasion->name);
            if ($message === $occasionName || Str::contains($message, $occasionName) || Str::contains($occasionName, $message)) {
                return $occasion;
            }
        }

        // 2. Tìm theo keywords
        foreach ($keywords as $key => $keyList) {
            foreach ($keyList as $keyword) {
                if (Str::contains($message, $keyword)) {
                    // Tìm occasion tương ứng
                    foreach ($occasions as $occasion) {
                        $occasionName = Str::lower($occasion->name);
                        if (Str::contains($occasionName, $key) || Str::contains($key, $occasionName)) {
                            return $occasion;
                        }
                    }
                }
            }
        }

        // 3. Fuzzy matching - tìm từng từ
        $words = explode(' ', $message);
        foreach ($words as $word) {
            if (strlen($word) < 3) continue;

            foreach ($occasions as $occasion) {
                $occasionName = Str::lower($occasion->name);
                if (Str::contains($occasionName, $word) || Str::contains($word, $occasionName)) {
                    return $occasion;
                }
            }
        }

        return null;
    }

    /**
     * Tạo hoặc lấy conversation cho product chatbot
     */
    public function startProductChat(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Tìm hoặc tạo conversation cho product chatbot (title = 'product_chatbot')
        $conv = Conversation::firstOrCreate(
            [
                'user_id' => $user->id,
                'title' => 'product_chatbot'
            ],
            [
                'user_id' => $user->id,
                'title' => 'product_chatbot'
            ]
        );

        // Load messages hiện có
        $messages = Message::where('conversation_id', $conv->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'type' => $msg->sender_type === 'bot' ? 'bot' : 'user',
                    'content' => $msg->content,
                    'timestamp' => $msg->created_at,
                    'products' => isset($msg->meta['products']) ? $msg->meta['products'] : null,
                    'showOccasions' => isset($msg->meta['showOccasions']) ? $msg->meta['showOccasions'] : false,
                ];
            });

        return response()->json([
            'conversation_id' => $conv->id,
            'messages' => $messages
        ]);
    }

    /**
     * Lưu tin nhắn của product chatbot
     */
    public function saveProductChatMessage(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'content' => 'required|string',
            'type' => 'required|in:user,bot',
            'products' => 'nullable|array',
            'showOccasions' => 'nullable|boolean',
        ]);

        // Kiểm tra conversation thuộc về user
        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        // Lưu message
        $message = Message::create([
            'conversation_id' => $request->conversation_id,
            'user_id' => $request->type === 'user' ? $user->id : null,
            'sender_type' => $request->type === 'user' ? 'user' : 'bot',
            'content' => $request->content,
            'meta' => [
                'products' => $request->products ?? null,
                'showOccasions' => $request->showOccasions ?? false,
            ]
        ]);

        return response()->json([
            'id' => $message->id,
            'type' => $request->type,
            'content' => $message->content,
            'timestamp' => $message->created_at,
            'products' => $request->products ?? null,
            'showOccasions' => $request->showOccasions ?? false,
        ]);
    }

    /**
     * Load lịch sử chat của product chatbot
     */
    public function getProductChatHistory(Request $request, $conversationId)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Kiểm tra conversation thuộc về user
        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->where('title', 'product_chatbot')
            ->first();

        if (!$conversation) {
            return response()->json(['error' => 'Conversation not found'], 404);
        }

        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'type' => $msg->sender_type === 'bot' ? 'bot' : 'user',
                    'content' => $msg->content,
                    'timestamp' => $msg->created_at,
                    'products' => isset($msg->meta['products']) ? $msg->meta['products'] : null,
                    'showOccasions' => isset($msg->meta['showOccasions']) ? $msg->meta['showOccasions'] : false,
                ];
            });

        return response()->json($messages);
    }
}
