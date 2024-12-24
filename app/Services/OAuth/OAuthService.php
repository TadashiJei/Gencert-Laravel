<?php

namespace App\Services\OAuth;

use App\Models\User;
use App\Models\OAuthProvider;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OAuthService
{
    protected $supportedProviders = [
        'google' => [
            'scopes' => ['email', 'profile', 'https://www.googleapis.com/auth/drive.file'],
            'additional_permissions' => ['offline', 'access_type']
        ],
        'microsoft' => [
            'scopes' => ['user.read', 'files.readwrite'],
            'additional_permissions' => ['offline_access']
        ],
        'linkedin' => [
            'scopes' => ['r_liteprofile', 'r_emailaddress'],
            'additional_permissions' => []
        ],
        'slack' => [
            'scopes' => ['identity.basic', 'identity.email', 'identity.team'],
            'additional_permissions' => []
        ],
        'discord' => [
            'scopes' => ['identify', 'email'],
            'additional_permissions' => []
        ],
        'zoom' => [
            'scopes' => ['user:read', 'user:write'],
            'additional_permissions' => []
        ]
    ];

    /**
     * Redirect the user to the OAuth provider's authentication page
     */
    public function redirect(string $provider)
    {
        if (!array_key_exists($provider, $this->supportedProviders)) {
            throw new \InvalidArgumentException("Unsupported OAuth provider: {$provider}");
        }

        $socialite = Socialite::driver($provider);
        
        // Add scopes and additional permissions
        if (isset($this->supportedProviders[$provider]['scopes'])) {
            $socialite->scopes($this->supportedProviders[$provider]['scopes']);
        }

        foreach ($this->supportedProviders[$provider]['additional_permissions'] as $permission) {
            $socialite->with($permission, true);
        }

        return $socialite->redirect();
    }

    /**
     * Handle the OAuth provider callback
     */
    public function handleCallback(string $provider)
    {
        try {
            $socialiteUser = Socialite::driver($provider)->user();

            // Find or create the user
            $user = $this->findOrCreateUser($provider, $socialiteUser);

            // Update or create OAuth provider record
            $this->updateOAuthProvider($user, $provider, $socialiteUser);

            return $user;
        } catch (\Exception $e) {
            \Log::error("OAuth callback error for provider {$provider}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Find or create a user based on OAuth data
     */
    protected function findOrCreateUser(string $provider, $socialiteUser)
    {
        $oauthProvider = OAuthProvider::where([
            'provider' => $provider,
            'provider_user_id' => $socialiteUser->getId()
        ])->first();

        if ($oauthProvider) {
            return $oauthProvider->user;
        }

        // Check if user exists with same email
        $user = User::where('email', $socialiteUser->getEmail())->first();

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $socialiteUser->getName(),
                'email' => $socialiteUser->getEmail(),
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now()
            ]);
        }

        return $user;
    }

    /**
     * Update or create OAuth provider record
     */
    protected function updateOAuthProvider(User $user, string $provider, $socialiteUser)
    {
        OAuthProvider::updateOrCreate(
            [
                'provider' => $provider,
                'provider_user_id' => $socialiteUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'access_token' => $socialiteUser->token,
                'refresh_token' => $socialiteUser->refreshToken,
                'expires_at' => isset($socialiteUser->expiresIn) ? now()->addSeconds($socialiteUser->expiresIn) : null,
                'token_type' => $socialiteUser->tokenType ?? 'Bearer',
                'scopes' => $this->supportedProviders[$provider]['scopes'],
            ]
        );
    }

    /**
     * Refresh the access token for a provider
     */
    public function refreshToken(User $user, string $provider)
    {
        $oauthProvider = $user->oauthProviders()
            ->where('provider', $provider)
            ->first();

        if (!$oauthProvider || !$oauthProvider->refresh_token) {
            throw new \RuntimeException("No refresh token available for provider: {$provider}");
        }

        try {
            $newToken = Socialite::driver($provider)
                ->refreshToken($oauthProvider->refresh_token);

            $oauthProvider->update([
                'access_token' => $newToken->getToken(),
                'refresh_token' => $newToken->getRefreshToken(),
                'expires_at' => isset($newToken->expiresIn) ? now()->addSeconds($newToken->expiresIn) : null,
            ]);

            return $oauthProvider;
        } catch (\Exception $e) {
            \Log::error("Failed to refresh token for provider {$provider}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a token needs refreshing
     */
    public function needsTokenRefresh(User $user, string $provider): bool
    {
        $oauthProvider = $user->oauthProviders()
            ->where('provider', $provider)
            ->first();

        if (!$oauthProvider || !$oauthProvider->expires_at) {
            return false;
        }

        // Refresh if token expires in less than 5 minutes
        return $oauthProvider->expires_at->subMinutes(5)->isPast();
    }

    /**
     * Get a valid access token for a provider
     */
    public function getValidAccessToken(User $user, string $provider): string
    {
        if ($this->needsTokenRefresh($user, $provider)) {
            $oauthProvider = $this->refreshToken($user, $provider);
        } else {
            $oauthProvider = $user->oauthProviders()
                ->where('provider', $provider)
                ->first();
        }

        if (!$oauthProvider) {
            throw new \RuntimeException("No OAuth provider found: {$provider}");
        }

        return $oauthProvider->access_token;
    }

    /**
     * Revoke access for a provider
     */
    public function revokeAccess(User $user, string $provider)
    {
        $oauthProvider = $user->oauthProviders()
            ->where('provider', $provider)
            ->first();

        if (!$oauthProvider) {
            return;
        }

        try {
            // Attempt to revoke token with provider
            $this->revokeTokenWithProvider($provider, $oauthProvider->access_token);
        } catch (\Exception $e) {
            \Log::warning("Failed to revoke token with provider {$provider}: " . $e->getMessage());
        }

        // Delete the provider record regardless of remote revocation success
        $oauthProvider->delete();
    }

    /**
     * Revoke token with the OAuth provider
     */
    protected function revokeTokenWithProvider(string $provider, string $token)
    {
        switch ($provider) {
            case 'google':
                Http::get('https://accounts.google.com/o/oauth2/revoke', [
                    'token' => $token
                ]);
                break;
            case 'microsoft':
                Http::post('https://login.microsoftonline.com/common/oauth2/v2.0/logout', [
                    'token' => $token
                ]);
                break;
            // Add other provider-specific revocation endpoints
        }
    }
}
