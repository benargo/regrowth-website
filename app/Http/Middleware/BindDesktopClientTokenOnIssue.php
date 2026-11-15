<?php

namespace App\Http\Middleware;

use App\Actions\DesktopClient\BindDesktopClientToken;
use App\Data\DesktopClient\DesktopClientHeadersData;
use App\Exceptions\TooManyDesktopClientsException;
use App\Models\DesktopClientToken;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\CryptTrait;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Binds each access token issued by `POST /oauth/token` to the desktop client named by the
 * `X-Desktop-Client-Id` and `X-Desktop-Client-Name` headers.
 *
 * - Missing or invalid device headers answer `400 invalid_request` before Passport runs.
 * - A refresh token whose access token is not bound to the device answers `401 invalid_grant` before Passport runs.
 * - After a `200`, the new access token is bound with `BindDesktopClientToken`. A refused binding revokes the
 *   issued access and refresh tokens and answers `403 too_many_desktop_clients` or `401 invalid_grant`.
 */
class BindDesktopClientTokenOnIssue
{
    use CryptTrait;

    public function __construct(Encrypter $encrypter)
    {
        $this->setEncryptionKey(Passport::tokenEncryptionKey($encrypter));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $headers = DesktopClientHeadersData::tryFromRequest($request);

        if ($headers === null) {
            return response()->json(['error' => 'invalid_request'], 400);
        }

        $previousAccessTokenId = null;

        if ($request->input('grant_type') === 'refresh_token') {
            $previousAccessTokenId = $this->refreshTokenAccessTokenId((string) $request->input('refresh_token'));

            $isBoundToDevice = $previousAccessTokenId !== null
                && DesktopClientToken::boundTo($previousAccessTokenId, $headers->id)->exists();

            if (! $isBoundToDevice) {
                return $this->invalidGrant();
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $claims = $this->accessTokenClaims((string) json_decode((string) $response->getContent(), true)['access_token']);

        try {
            BindDesktopClientToken::run(
                User::findOrFail($claims->get('sub')),
                $headers->id,
                $headers->name,
                $claims->get('jti'),
                $previousAccessTokenId,
            );
        } catch (TooManyDesktopClientsException $exception) {
            $this->revokeIssuedTokens($claims->get('jti'));

            return response()->json(['error' => 'too_many_desktop_clients', 'message' => $exception->getMessage()], 403);
        } catch (AuthenticationException) {
            $this->revokeIssuedTokens($claims->get('jti'));

            return $this->invalidGrant();
        }

        return $response;
    }

    private function refreshTokenAccessTokenId(string $refreshToken): ?string
    {
        try {
            $accessTokenId = json_decode($this->decrypt($refreshToken), true)['access_token_id'] ?? null;
        } catch (Throwable) {
            return null;
        }

        return is_string($accessTokenId) ? $accessTokenId : null;
    }

    private function accessTokenClaims(string $accessToken): DataSet
    {
        /** @var UnencryptedToken $token */
        $token = (new Parser(new JoseEncoder))->parse($accessToken);

        return $token->claims();
    }

    private function revokeIssuedTokens(string $accessTokenId): void
    {
        $accessToken = Token::with('refreshToken')->find($accessTokenId);

        $accessToken?->refreshToken?->revoke();
        $accessToken?->revoke();
    }

    private function invalidGrant(): JsonResponse
    {
        return response()->json(['error' => 'invalid_grant'], 401);
    }
}
