<?php

declare(strict_types=1);

namespace Whirlwind\Middleware\OAuth\Infrastructure\Oauth;

final class TokenInfo
{
    private string $ownerId;

    private string $ownerType;

    private string $accessToken;

    private string $clientId;

    /**
     * @var array $scopes Array containing scopes info
     * $scopes = [
     *     'events.delivery' => [ // key equals scope id
     *          'id' => 'events.delivery',
     *          'description' => 'Access to event delivery endpoints'
     *     ],
     * ];
     */
    private array $scopes;

    public function __construct(
        string $ownerId,
        string $ownerType,
        string $accessToken,
        string $clientId,
        array $scopes
    ) {
        $this->ownerId = $ownerId;
        $this->ownerType = $ownerType;
        $this->accessToken = $accessToken;
        $this->clientId = $clientId;
        $this->scopes = $scopes;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
    }

    public function getOwnerType(): string
    {
        return $this->ownerType;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }
}
