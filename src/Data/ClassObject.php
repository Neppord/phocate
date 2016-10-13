<?php
declare(strict_types = 1);

namespace Phocate\Data;


use Phocate\Token\Either;

class ClassObject implements Either
{
    /** @var string */
    public $name;

    /** @var string[] */
    public $implements;
    /** @var string */
    public $extends;

    public function __construct(
        string $name,
        string $extends = '',
        array $implements = []
    ) {
        $this->name = $name;
        $this->extends = $extends;
        $this->implements = $implements;
    }

}