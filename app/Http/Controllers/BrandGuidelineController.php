<?php

namespace App\Http\Controllers;

use App\Models\BrandGuideline;
use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BrandGuidelineController extends Controller
{
    /**
     * Get brand guidelines for a specific social account
     */
    public function show(SocialAccount $socialAccount): JsonResponse
    {
        // Ensure the social account belongs to the authenticated user
        if ($socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $brandGuideline = $socialAccount->brandGuidelines;
        
        return response()->json([
            'brand_guideline' => $brandGuideline
        ]);
    }

    /**
     * Store or update brand guidelines for a social account
     */
    public function store(Request $request, SocialAccount $socialAccount): JsonResponse
    {
        // Ensure the social account belongs to the authenticated user
        if ($socialAccount->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'tone_of_voice' => 'required|string|max:1000',
                'brand_voice' => 'required|string|max:1000',
                'content_themes' => 'required|array|min:1',
                'content_themes.*' => 'string|max:255',
                'hashtag_strategy' => 'required|array|min:1',
                'hashtag_strategy.*' => 'string|max:100',
                'posting_frequency' => 'required|string|in:daily,weekly,bi-weekly,monthly',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        $brandGuideline = BrandGuideline::updateOrCreate(
            ['social_account_id' => $socialAccount->id],
            $validated
        );

        return response()->json([
            'message' => 'Brand guidelines saved successfully',
            'brand_guideline' => $brandGuideline
        ], 201);
    }

    /**
     * Get all brand guidelines for the authenticated user
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $socialAccounts = $user->socialAccounts()->with('brandGuidelines')->get();
        
        $guidelines = $socialAccounts->map(function ($account) {
            return [
                'social_account_id' => $account->id,
                'platform' => $account->platform,
                'account_name' => $account->account_name,
                'brand_guideline' => $account->brandGuidelines
            ];
        });

        return response()->json([
            'brand_guidelines' => $guidelines
        ]);
    }
}