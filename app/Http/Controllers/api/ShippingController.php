<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ShippingController extends Controller
{
    public function calc(Request $request)
    {
        $request->validate([
            'address'  => 'required|string',
            'province' => 'required|string',
            'district' => 'required|string',
            'ward'     => 'nullable|string',
            'weight'   => 'nullable|integer|min:100',
            'value'    => 'nullable|numeric|min:0',
        ]);

        $client = new Client([
            'timeout' => 10,
        ]);

        $params = [
            "address"  => $request->address,
            "province" => $request->province,
            "district" => $request->district,
            "ward"     => $request->ward ?? "",
            "weight"   => $request->weight ?? 500,
            "value"    => $request->value ?? 0,

            // PICK ADDRESS (KHO)
            "pick_province" => "Bình Dương",
            "pick_district" => "Dĩ An",
            "pick_ward"     => "Đông Hòa",
            "pick_street"   => "Ký túc xá Khu B",
            "pick_tel"      => "0946403788",
        ];

        try {
            $response = $client->get(
                "https://services.giaohangtietkiem.vn/services/shipment/fee",
                [
                    "headers" => [
                        "Token" => config('services.ghtk.token'),
                    ],
                    "query" => $params,
                ]
            );

            $data = json_decode($response->getBody(), true);

            if (!($data['success'] ?? false)) {
                return response()->json([
                    "message" => "GHTK không tính được phí",
                    "raw" => $data
                ], 400);
            }

            return response()->json([
                "carrier"        => "GHTK",
                "service"        => $data["fee"]["name"],
                "shipping_fee"   => $data["fee"]["fee"],
                "insurance_fee"  => $data["fee"]["insurance_fee"],
                "delivery_time"  => $data["fee"]["delivery"],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                "message" => "Lỗi kết nối GHTK",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
