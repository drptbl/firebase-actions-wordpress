<?php

namespace Firebase\Auth\Token\Tests;

use Firebase\Auth\Token\ArrayKeyStore;
use Firebase\Auth\Token\Domain\KeyStore;
use Firebase\Auth\Token\Exception\InvalidToken;
use Firebase\Auth\Token\Exception\UnknownKey;
use Firebase\Auth\Token\Verifier;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Token;

class VerifierTest extends TestCase
{
    /**
     * @var Verifier
     */
    private $verifier;

    /**
     * @var KeyStore|\PHPUnit_Framework_MockObject_MockObject
     */
    private $keyStore;

    /**
     * @var Signer|\PHPUnit_Framework_MockObject_MockObject
     */
    private $signer;

    protected function setUp()
    {
        $this->keyStore = new ArrayKeyStore(['valid_key_id' => 'valid_key']);
        $this->signer = $this->createMockSigner();

        $this->verifier = new Verifier('project-id', $this->keyStore, $this->signer);
    }

    /**
     * @param Token $token
     *
     * @dataProvider validTokenProvider
     */
    public function testItSucceedsWithAValidToken($token)
    {
        $this->signer
            ->expects($this->once())
            ->method('verify')
            ->willReturn(true);

        $this->verifier->verifyIdToken($token);
    }

    /**
     * @param string $token
     *
     * @dataProvider validTokenStringProvider
     */
    public function testItCanHandleTokenString($token)
    {
        $this->signer
            ->expects($this->once())
            ->method('verify')
            ->willReturn(true);

        $this->verifier->verifyIdToken($token);
    }

    public function testItFailsOnAnUnknownKey()
    {
        $token = (new Builder())
            ->setExpiration(time() + 1800)
            ->setIssuedAt(time() - 10)
            ->setIssuer('https://securetoken.google.com/project-id')
            ->setHeader('kid', 'invalid_key_id')
            ->getToken();

        $this->expectException(UnknownKey::class);
        $this->verifier->verifyIdToken($token);
    }

    /**
     * @param Token $token
     *
     * @dataProvider invalidTokenProvider
     */
    public function testInvalidTokenResultsInException(Token $token)
    {
        $this->expectException(InvalidToken::class);

        $this->verifier->verifyIdToken($token);
    }

    public function validTokenProvider()
    {
        return [
            [(new Builder())
                ->setExpiration(time() + 1800)
                ->setIssuedAt(time() - 10)
                ->setIssuer('https://securetoken.google.com/project-id')
                ->setHeader('kid', 'valid_key_id')
                ->sign($this->createMockSigner(), 'valid_key')
                ->getToken()
            ]
        ];
    }

    public function validTokenStringProvider()
    {
        return [
            [(string) (new Builder())
                ->setExpiration(time() + 1800)
                ->setIssuedAt(time() - 10)
                ->setIssuer('https://securetoken.google.com/project-id')
                ->setHeader('kid', 'valid_key_id')
                ->sign($this->createMockSigner(), 'valid_key')
                ->getToken()
            ]
        ];
    }

    public function invalidTokenProvider()
    {
        $builder = new Builder();

        return [
            'expired' => [
                $builder
                    ->setExpiration(time() - 10)
                    ->getToken(),
            ],
            'not_yet_issued' => [
                $builder
                    ->setExpiration(time() + 1800)
                    ->setIssuedAt(time() + 1800)
                    ->getToken(),
            ],
            'invalid_issuer' => [
                $builder
                    ->setExpiration(time() + 1800)
                    ->setIssuedAt(time() - 10)
                    ->setIssuer('invalid_issuer')
                    ->getToken(),
            ],
            'missing_key_id' => [
                $builder
                    ->setExpiration(time() + 1800)
                    ->setIssuedAt(time() - 10)
                    ->setIssuer('https://securetoken.google.com/project-id')
                    ->getToken(),
            ],
            'unsigned' => [
                $builder
                    ->setExpiration(time() + 1800)
                    ->setIssuedAt(time() - 10)
                    ->setIssuer('https://securetoken.google.com/project-id')
                    ->setHeader('kid', 'valid_key_id')
                    ->getToken(),
            ],
            'invalid_signature' => [
                $builder
                    ->setExpiration(time() + 1800)
                    ->setIssuedAt(time() - 10)
                    ->setIssuer('https://securetoken.google.com/project-id')
                    ->setHeader('kid', 'valid_key_id')
                    ->sign($this->createMockSigner(), 'invalid_key')
                    ->getToken(),
            ],
        ];
    }
}
