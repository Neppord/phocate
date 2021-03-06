#! /usr/bin/env php
<?php
declare(strict_types=1);

namespace Phocate;

use Phocate\File\Directory;
use Phocate\Parsing\Data\UseData\UseData;
use Phocate\Parsing\FileParser;

ini_set('memory_limit', '1024M');

require_once __DIR__ . '/../vendor/autoload.php';

$file_p = new FileParser();

$project_dir = new Directory($argv[1]);
$database = new Database('sqlite:phocate.db');
$database->begin();
$database->load_schema();
foreach($project_dir->getPhpFiles() as $php_file) {
    $path = $php_file->getPath();
    $tokens = $php_file->getTokens();
    echo "parsing: $path\n";
    $result = $file_p->parser($path, $tokens);
    foreach ($result->file->namespaces as $namespace) {
        $namespace_name = $namespace->name;
        $database->insert_namespace->execute([
            ':namespace_path' => $path,
            ':namespace' => $namespace_name
        ]);
        foreach ($namespace->usages as $use) {
            $name = $use->name;
            $FQN = $use->FQN;
            $database->insert_use->execute([
                ':namespace' => $namespace_name,
                ':usage_path' => $path,
                ':name' => $name,
                ':FQN' => $FQN
            ]);
        }
        foreach ($namespace->classes as $class) {
            $name = $class->name;
            $FQN = "$namespace_name\\$name";
            $database->insert_class->execute([
                ':class_path' => $path,
                ':namespace' => $namespace_name,
                ':FQN' => $FQN,
                ':name' => $name
            ]);
            if ($class->extends != '') {
                $extends = $class->extends;
                if ($extends[0] === '\\') {
                    $super_FQN = "$extends";
                } else {
                    $usage = $namespace->usages->get($extends);
                    if ($usage instanceof UseData) {
                        $super_FQN = $usage->FQN;
                    } else {
                        $super_FQN = "$namespace_name\\$extends";
                        $database->insert_use->execute([
                            ':namespace' => $namespace_name,
                            ':usage_path' => $path,
                            ':name' => $extends,
                            ':FQN' => $super_FQN
                        ]);
                    }
                }
                $database->insert_extends->execute([
                    ':FQN' => $FQN,
                    ':super_FQN' => $super_FQN
                ]);
            }
            foreach ($class->implements as $interface) {
                if ($interface[0] === '\\') {
                    $interface_FQN = "$interface";
                } else {
                    $usage = $namespace->usages->get($interface);
                    if ($usage instanceof UseData) {
                        $interface_FQN = $usage->FQN;
                    } else {
                        $interface_FQN = "$namespace_name\\$interface";
                        $database->insert_use->execute([
                            ':namespace' => $namespace_name,
                            ':usage_path' => $path,
                            ':name' => $interface,
                            ':FQN' => $interface_FQN
                        ]);
                    }
                }
                $database->insert_implements->execute([
                    ':FQN' => $FQN,
                    ':interface_FQN' => $interface_FQN
                ]);
            }
        }
    }
}

$database->end();
