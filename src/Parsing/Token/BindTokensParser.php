<?php
declare(strict_types = 1);

namespace Phocate\Parsing\Token;


class BindTokensParser extends TokensParser
{
    /** @var TokensParser */
    private $from;
    /** @var callable */
    private $through;

    public function __construct(TokensParser $from, callable $through)
    {
        $this->from = $from;
        $this->through = $through;
    }

    public function parse(Tokens $tokens): TokensResult
    {
        $result = $this->from->parse($tokens);
        if ($result instanceof JustTokensResult) {
            $through = $this->through;
            return $through($result->result)->parse($result->tokens);
        } else {
            return $result;
        }
    }
}