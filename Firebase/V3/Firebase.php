<?php

namespace Firebase\V3;

use Firebase\Auth\Token\Handler;
use Firebase\Database;
use Firebase\Database\ApiClient;
use Firebase\Exception\InvalidArgumentException;
use Firebase\Http\Middleware;
use Firebase\ServiceAccount;
use Firebase\V3\Auth\CustomToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

class Firebase
{
    /**
     * @var ServiceAccount
     */
    private $serviceAccount;

    /**
     * @var UriInterface
     */
    private $databaseUri;

    /**
     * @var Database
     */
    private $database;

    public function getDatabaseUri() {
    	return $this->databaseUri;
    }

    public function __construct(ServiceAccount $serviceAccount, UriInterface $databaseUri)
    {
        $this->serviceAccount = $serviceAccount;
        $this->databaseUri = $databaseUri;
    }

    /**
     * @param mixed $serviceAccount Service Account (ServiceAccount instance, JSON, array, path to JSON file)
     * @param string|UriInterface $databaseUri
     *
     * @throws InvalidArgumentException
     *
     * @return Firebase
     */
    public static function fromServiceAccount($serviceAccount, $databaseUri = null)
    {
        $serviceAccount = ServiceAccount::fromValue($serviceAccount);

        $databaseUri = $databaseUri
            ? Psr7\uri_for($databaseUri)
            : new Uri(sprintf('https://%s.firebaseio.com', $serviceAccount->getProjectId()));

        return new self($serviceAccount, $databaseUri);
    }

    public function withDatabaseUri($databaseUri): Firebase
    {
        return new self($this->serviceAccount, Psr7\uri_for($databaseUri));
    }

    public function getDatabase(): Database
    {
        if (!$this->database) {
            $this->database = $this->createDatabase();
        }

        return $this->database;
    }

    public function asUserWithClaims(string $uid, array $claims = []): Firebase
    {
        return $this->withCustomAuth(new CustomToken($uid, $claims));
    }

    /**
     * Returns a Token Handler to be used for creating Custom Tokens and
     * verifying ID tokens.
     *
     * @see https://firebase.google.com/docs/auth/admin/create-custom-tokens
     * @see https://firebase.google.com/docs/auth/admin/verify-id-tokens
     *
     * @return Handler
     */
    public function getTokenHandler(): Handler
    {
        return new Handler(
            $this->serviceAccount->getProjectId(),
            $this->serviceAccount->getClientEmail(),
            $this->serviceAccount->getPrivateKey()
        );
    }

    private function withCustomAuth(Auth $override): Firebase
    {
        $firebase = new self($this->serviceAccount, $this->databaseUri);
        $firebase->database = $this->getDatabase()->withCustomAuth($override);

        return $firebase;
    }

    private function createDatabase(): Database
    {
        $client = $this->createDatabaseClient($this->databaseUri);

        return new Database($this->databaseUri, $client);
    }

    private function createDatabaseClient(UriInterface $databaseUri): ApiClient
    {
        $googleAuthTokenMiddleware = $this->createGoogleAuthTokenMiddleware($this->serviceAccount);

        $stack = HandlerStack::create();
        $stack->push(Middleware::ensureJson(), 'ensure_json');
        $stack->push($googleAuthTokenMiddleware, 'auth_service_account');

        $http = new Client([
            'base_uri' => $databaseUri,
            'handler' => $stack,
            'auth' => 'google_auth',
        ]);

        return new ApiClient($http);
    }

    /**
     * @param ServiceAccount $serviceAccount
     *
     * @return AuthTokenMiddleware
     */
    private function createGoogleAuthTokenMiddleware(ServiceAccount $serviceAccount)
    {
        $scopes = [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/firebase.database',
        ];

        $credentials = [
            'client_email' => $serviceAccount->getClientEmail(),
            'client_id' => $serviceAccount->getClientId(),
            'private_key' => $serviceAccount->getPrivateKey(),
        ];

        return new AuthTokenMiddleware(new ServiceAccountCredentials($scopes, $credentials));
    }
}
