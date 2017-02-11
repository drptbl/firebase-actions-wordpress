<?php

namespace Firebase\Database\Query\Filter;

use Firebase\Database\Query\Filter;
use Firebase\Database\Query\ModifierTrait;
use Firebase\Exception\InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class EqualTo implements Filter
{
    use ModifierTrait;

    private $value;

    public function __construct($value)
    {
        if ($value !== null && !is_scalar($value)) {
            throw new InvalidArgumentException('Only scalar values are allowed for "equalTo" queries.');
        }

        $this->value = $value;
    }

    public function modifyUri(UriInterface $uri): UriInterface
    {
        return $this->appendQueryParam($uri, 'equalTo', $this->value);
    }
}
