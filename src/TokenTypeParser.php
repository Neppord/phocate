<?php
namespace Phocate;

class TokenTypeParser extends TokensParser
{
    /** @var int */
    private $type;

    public function __construct(int $type)
    {
        $this->type = $type;
    }

    public function parse(Tokens $tokens): ?TokensResult
    {
        $head = $tokens->head();
        if ($head->type === $this->type) {
            return new TokensResult([$head], $tokens->tail());
        } else {
            return null;
        }
    }
}
