<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Review;
use App\Models\Property;

class ReviewAnalysisController extends Controller
{
    public function analyze(Property $property)
    {
        $reviews = $property->reviews()->pluck('comment')
            ->toArray();

        if (empty($reviews)) {
            return response()->json([
                'success' => false,
                'message' => 'No reviews found for this property'
            ]);
        }

        $reviewsText = implode("\n---\n", $reviews);

        $apikey = config('services.openrouter.key');
        if (!$apikey) {
            return response()->json([
                'success' => false,
                'message' => 'OpenRouter API key not configured'
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apikey,
                'Content-Type' => 'application/json',
            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'gpt-4o-mini', 
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a data analyst specializing in customer reviews. 
                            Analyze the following reviews for sentiment, recurring themes, 
                            and overall satisfaction. Return the result in JSON format 
                            with fields: sentiment_summary,
                            overall_summary (a short 2-3 sentence text giving the user a quick idea of the reviews).'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Here are the reviews for the property:\n\n{$reviewsText}"
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0,
            ]);

            if ($response->successful()) {
                $result = $response->json();

                $analysis = $result['choices'][0]['message']['content'] ?? null;
                if ($analysis) {
                    // Remove markdown code block formatting
                    $analysis = preg_replace('/^```json|```$/m', '', $analysis);
                    $analysis = trim($analysis);
                }
                return response()->json([
                    'success' => true,
                    'analysis' => json_decode($analysis, true) ?? $analysis
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'API request failed',
                'status' => $response->status(),
                'debug' => $response->json()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ], 500);
        }
    }
}
