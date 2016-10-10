<?php
declare(strict_types = 1);

namespace Phocate;


abstract class TokensParser
{
    abstract public function parse(Tokens $tokens): ?TokensResult;
    public function bind(callable $closure): TokensParser
    {
        return new BindTokensParser($this, $closure);
    }
    public function before(TokensParser $parser): TokensParser
    {
        /** @noinspection PhpUnusedParameterInspection */
        return $this->bind(function (array $tokens) use ($parser){
            return $parser;
        });
    }
}