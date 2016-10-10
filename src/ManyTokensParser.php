<?php
declare(strict_types = 1);

namespace Phocate;


class ManyTokensParser extends TokensParser
{
    /** @var TokensParser */
    private $parser;

    /**
     * ManyTokenParser constructor.
     * @param TokensParser $parser
     */
    public function __construct(TokensParser $parser)
    {
        $this->parser = $parser;
    }

    public function parse(Tokens $tokens): ?TokensResult
    {
        $results = [[]];
        $result = $this->parser->parse($tokens);
        if ($result === null) {
            return null;
        }
        while ($result != null) {
            $results[] = $result->result;
            $tokens = $result->tokens;
            $result = $this->parser->parse($tokens);
        }
        return new TokensResult(array_merge(...$results), $tokens);
    }
}