<?php

namespace App\Http\Controllers;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Http\Request;
use App\Models\Property;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function handleChat(Request $request)
    {
        $query = strtolower(trim($request->input('query')));
        $propertiesQuery = Property::query();
        $appliedFilters = 0;

        // تحية
        $greetings = ['hi', 'hello', 'hey', 'السلام عليكم', 'ازيك', 'اهلا'];
        if (in_array($query, $greetings)) {
            return response()->json([
                'response' => "👋 Hello! I’m your property assistant. You can ask me things like:\n
                - Show me properties in Cairo\n
                - Apartments under 500,000\n- Apartments 100-150 sqm\n"
            ]);
        }

        if (Str::contains($query, 'under')) {
            preg_match('/under\s+(\d+)/', $query, $matches);
            $price = $matches[1] ?? null;
            if ($price) {
                $propertiesQuery->where('price', '<=', $price);
                $appliedFilters++;
            }
        }

        if (preg_match('/(\d+)\s*(?:bed(room)?s?|غرف)/i', $query, $matches)) {
            $bedrooms = (int) $matches[1];
            $propertiesQuery->where('bedrooms', $bedrooms);
            $appliedFilters++;
        }

        if (preg_match('/(\d+)\s*(?:bath(room)?s?|حمام|حمامات)/i', $query, $matches)) {
            $bathrooms = (int) $matches[1];
            $propertiesQuery->where('bathrooms', $bathrooms);
            $appliedFilters++;
        }

        if (preg_match('/(\d+)\s*(?:m2|sqm|متر|متر مربع)/i', $query, $matches)) {
            $size = (int) $matches[1];
            $propertiesQuery->whereBetween('size', [$size, $size * 2]);
            $appliedFilters++;
        }

        if (preg_match('/(?:in|at|في)\s+([a-zA-Zء-ي\s]+)/i', $query, $locationMatch)) {
            $location = trim($locationMatch[1]);
            $propertiesQuery->where(function ($q) use ($location) {
                $q->where('location->city', 'like', "%{$location}%")
                  ->orWhere('location->state', 'like', "%{$location}%")
                  ->orWhere('location->address', 'like', "%{$location}%")
                  ->orWhere('location->zip_code', 'like', "%{$location}%");
            });
            $appliedFilters++;
        }

        if ($appliedFilters === 0) {
            return response()->json([
                'response' => "😕 Sorry, I couldn’t understand your request. Please specify location, price, size, or number of rooms."
            ]);
        }

        $properties = $propertiesQuery
            ->take(5)
            ->get(['id', 'title', 'price', 'bedrooms', 'bathrooms', 'size', 'location']);

        if ($properties->isEmpty()) {
            return response()->json([
                'response' => "😕 Sorry, I couldn’t find any properties matching your request."
            ]);
        }

        $response = "✅ I found " . $properties->count() . " properties that match your request:<br>";

        foreach ($properties as $prop) {
            $city = $prop->location['city'] ?? '';
            $state = $prop->location['state'] ?? '';
            $address = $prop->location['address'] ?? '';
            $response .= "🏠 <b>{$prop->title}</b><br>💰 Price: {$prop->price} EGP<br>🛏 {$prop->bedrooms} bd | 🚿 {$prop->bathrooms} ba | 📏 {$prop->size} sqm<br>📍 {$address}, {$city}, {$state}<br><a href='/property/{$prop->id}'>View Details</a><br><br>";
        }

        return response()->json([
            'response' => $response,
            'properties' => $properties
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
