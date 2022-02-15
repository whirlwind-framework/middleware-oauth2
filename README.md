# Oauth2 middleware of Whirlwind framework

#### Validates Bearer token in:
- "Authorization" header (Bearer prefix required)
- "access_token" query param (Bearer prefix must be omitted)
- "access_token" field in body (Bearer prefix must be omitted)

Example of usage:
```
$middleware = $container->get(AuthMiddleware::class);

$app->map('GET', '/api/v1/messages', \App\Api\Action\Message\v1\MessageIndexAction::class)
    ->middleware($middleware->withRequiredScopes(['communication-messages.index']));
```