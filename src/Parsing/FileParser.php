<?php
declare(strict_types = 1);

namespace Phocate\Parsing;

use Phocate\Parsing\Data\ClassObject;
use Phocate\Parsing\Data\UseData\ConsUseDataList;
use Phocate\Parsing\Data\FileObject;
use Phocate\Parsing\Data\NamespaceObject;
use Phocate\Parsing\Data\UseData\UseData;
use Phocate\Parsing\Token\Match;
use Phocate\Parsing\Token\PureTokenParser;
use Phocate\Parsing\Token\Token;
use Phocate\Parsing\Token\Tokens;
use Phocate\Parsing\Token\TokensParser;

class FileParser
{

    public static function fqn_parser()
    {
        return self::absolute_fqn_parser()
            ->ifFail(self::relative_fqn_parser());
    }

    public static function relative_fqn_parser(): TokensParser
    {
        return (new Match(T_STRING))->sepBy(new Match(T_NS_SEPARATOR));
    }

    public static function absolute_fqn_parser(): TokensParser
    {
        return (new Match(T_NS_SEPARATOR))
            ->before(self::relative_fqn_parser())
            ->bind(function (array $tokens) {
                /** @var Token $token */
                $token = $tokens[0];
                $tokens[0] = Token::fromArray([
                    $token->type,
                    '\\' . $token->contents
                ]);
                return new PureTokenParser($tokens);
            });
    }

    public static function extends_parser(string $class_name): EitherParser
    {
        return (new Match(T_WHITESPACE))
            ->before(new Match(T_EXTENDS))
            ->before(new Match(T_WHITESPACE))
            ->before(self::fqn_parser())
            ->mapToEitherParser(function (array $tokens) use ($class_name) {
                $strings = array_map(function (Token $token) {
                    return $token->contents;
                },$tokens);
                $super_class = implode('\\', $strings);
                return new ClassObject($class_name, $super_class, []);
            });
    }

    public static function implements_parser(string $class_name): EitherParser
    {
        return (new Match(T_WHITESPACE))
            ->before(new Match(T_IMPLEMENTS))
            ->before(new Match(T_WHITESPACE))
            ->before(self::fqn_parser())
            ->mapToEitherParser(function (array $tokens) use ($class_name) {
                $strings = array_map(function (Token $token) {
                    return $token->contents;
                },$tokens);
                $interface = implode('\\', $strings);
                return new ClassObject($class_name, '', [$interface]);
            });
    }

    public static function namespace_parser(): EitherParser
    {
        return (new Match(T_NAMESPACE))
            ->before(new Match(T_WHITESPACE))
            ->before(self::relative_fqn_parser())
            ->mapToEitherParser(function (array $tokens): Either {
                $strings = array_map(function (Token $token) {
                    return $token->contents;
                },$tokens);
                $namespace = implode('\\', $strings);
                return new NamespaceObject($namespace);
            });
    }

    public static function class_parser(): EitherParser
    {
        return (new Match(T_CLASS))
            ->before(new Match(T_WHITESPACE))
            ->before(new Match(T_STRING))
            ->bindEither(function ($tokens): EitherParser {
                $class_name = $tokens[0]->contents;
                $normal = new PureEitherParser(new ClassObject($class_name, '', []));
                return self::extends_parser($class_name)
                    ->ifFail(self::implements_parser($class_name))
                    ->ifFail($normal);
            });
    }

    public static function use_parser()
    {
        return (new Match(T_USE))
            ->before(new Match(T_WHITESPACE))
            ->before(self::relative_fqn_parser())
            ->mapToEitherParser(function (array $tokens): Either {
                $strings = array_map(function (Token $token) {
                    return $token->contents;
                },$tokens);
                $FQN = implode('\\', $strings);
                return new UseData($FQN, $tokens[count($tokens) - 1]->contents);
            });
    }


    public function parser(string $path, Tokens $tokens): FileResult
    {
        $namespace_stmt_p = self::namespace_parser();
        $class_stmt_p = self::class_parser();
        $use_stmt_p = self::use_parser();
        $body_p = $namespace_stmt_p
            ->ifFail($use_stmt_p)
            ->ifFail($class_stmt_p);
        $file = new FileObject();
        $file->path = $path;
        /** @var NamespaceObject $namespace */
        $namespace = null;

        while (!$tokens->nil()) {
            $result = $body_p->parse($tokens);
            if ($result instanceof NothingEitherResult) {
                $tokens = $tokens->tail();
            } else if ($result instanceof  JustEitherResult) {
                $tokens = $result->tokens;
                $object = $result->result;
                if ($object instanceof NamespaceObject) {
                    $namespace = $object;
                    $file->namespaces[] = $namespace;
                } else if ($object instanceof UseData) {
                    if ($namespace === null) {
                        $namespace = new NamespaceObject('');
                        $file->namespaces[] = $namespace;
                    }
                    $namespace->usages = new ConsUseDataList(
                        $object,
                        $namespace->usages
                    );
                } else if ($object instanceof ClassObject) {
                    if ($namespace === null) {
                        $namespace = new NamespaceObject('');
                        $file->namespaces[] = $namespace;
                    }
                    $namespace->classes[] = $object;
                }
            }
        }
        return new FileResult($file, $tokens);
    }
}