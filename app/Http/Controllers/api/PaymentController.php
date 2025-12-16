<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with('order.user')
            ->when($request->method, fn($q) => $q->where('payment_method', $request->method))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10);

        return response()->json($payments);
    }

    public function updateStatus(Request $request, $id)
    {
        $payment = Payment::findOrFail($id);
        $payment->status = $request->status;
        $payment->save();

        return response()->json(['message' => 'Payment status updated successfully']);
    }

    /**
     * Proxy Google Apps Script to bypass browser CORS.
     */
    public function sheetProxy(Request $request)
    {
        $url = $request->query('url');
        if (!$url || !is_string($url)) {
            return response()->json(['message' => 'Missing url'], 400);
        }
        // Basic allowlist to prevent SSRF (avoid php8 dependency on str_starts_with)
        $isAllowed = (strpos($url, 'https://script.googleusercontent.com/') === 0)
            || (strpos($url, 'https://script.google.com/') === 0);
        if (!$isAllowed) {
            return response()->json(['message' => 'URL not allowed'], 400);
        }

        try {
            $resp = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'text/plain, text/html, application/json;q=0.9,*/*;q=0.8',
                    'User-Agent' => 'CinemasterProxy/1.0'
                ])
                ->withOptions([
                    'allow_redirects' => true,
                    'verify' => false, // if local env has SSL issues
                ])
                ->get($url);

            // Normalize Content-Type header (can be array or null)
            $rawContentType = $resp->header('Content-Type');
            if (is_array($rawContentType)) {
                $contentType = implode(', ', $rawContentType);
            } else {
                $contentType = $rawContentType ?: 'text/plain; charset=UTF-8';
            }

            $status = $resp->status();

            // Forward upstream response body and status, add permissive CORS
            return response($resp->body(), $status)
                ->header('Content-Type', $contentType)
                ->header('Access-Control-Allow-Origin', '*');
        } catch (\Throwable $e) {
            \Log::error('sheet-proxy failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Proxy error', 'error' => $e->getMessage()], 502);
        }
    }
}
