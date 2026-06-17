<?php
namespace GCWorld\ObjectManager;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class DocblockMigrator
{
    /**
     * @param string[] $paths
     * @return array{processed:int,updated:int,skipped:int,warnings:string[]}
     */
    public function migratePaths(array $paths, bool $dryRun = false): array
    {
        $result = [
            'processed' => 0,
            'updated'   => 0,
            'skipped'   => 0,
            'warnings'  => [],
        ];

        foreach ($paths as $path) {
            foreach ($this->expandPath($path) as $file) {
                ++$result['processed'];
                $fileResult = $this->migrateFile($file, $dryRun);
                if ($fileResult['updated']) {
                    ++$result['updated'];
                } else {
                    ++$result['skipped'];
                }

                foreach ($fileResult['warnings'] as $warning) {
                    $result['warnings'][] = $warning;
                }
            }
        }

        return $result;
    }

    /**
     * @return array{updated:bool,warnings:string[]}
     */
    public function migrateFile(string $path, bool $dryRun = false): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('File not found: '.$path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read file: '.$path);
        }

        $transformed = $this->transformContents($contents, $path);
        if ($transformed['changed'] && !$dryRun) {
            file_put_contents($path, $transformed['contents']);
        }

        return [
            'updated'  => $transformed['changed'],
            'warnings' => $transformed['warnings'],
        ];
    }

    /**
     * @return array{changed:bool,contents:string,warnings:string[]}
     */
    public function transformContents(string $contents, ?string $path = null): array
    {
        $classes = $this->discoverClasses($contents);
        if ($classes === []) {
            return [
                'changed'  => false,
                'contents' => $contents,
                'warnings' => [],
            ];
        }

        $edits = [];
        $warnings = [];
        $needsObjectManagerImport = false;
        $needsObjectFactoryImport = false;
        $needsObjectManagerMethodImport = false;

        foreach ($classes as $class) {
            if ($class['docblock'] === null) {
                continue;
            }

            $metadata = $this->parseMetadata($class['docblock']['text']);
            if ($metadata === null) {
                continue;
            }

            if ($class['hasObjectManagerAttribute']) {
                $warnings[] = $this->buildPrefix($path, $class['name']).
                    'already has an ObjectManager attribute; skipped.';
                continue;
            }

            $missingFactoryMethods = [];
            foreach (array_values($metadata['factories']) as $factoryMethod) {
                if (!array_key_exists($factoryMethod, $class['methods'])) {
                    $missingFactoryMethods[] = $factoryMethod;
                }
            }

            if ($missingFactoryMethods !== []) {
                $warnings[] = $this->buildPrefix($path, $class['name']).
                    'references missing factory methods: '.implode(', ', $missingFactoryMethods);
                continue;
            }

            $classAttribute = $this->renderObjectManagerAttribute($metadata);
            $cleanDocblock = $this->stripMetadataLines($class['docblock']['text']);
            $replacement = $cleanDocblock === '' ? $classAttribute."\n" : $cleanDocblock."\n".$classAttribute."\n";
            $edits[] = [
                'start'       => $class['docblock']['start'],
                'end'         => $class['classTokenStart'],
                'replacement' => $replacement,
            ];
            $needsObjectManagerImport = true;
            $needsObjectManagerMethodImport = true;

            foreach (array_values($metadata['factories']) as $factoryMethod) {
                $method = $class['methods'][$factoryMethod];
                if ($method['hasObjectFactoryAttribute']) {
                    continue;
                }

                $edits[] = [
                    'start'       => $method['start'],
                    'end'         => $method['start'],
                    'replacement' => $method['indent']."#[ObjectFactory]\n",
                ];
                $needsObjectFactoryImport = true;
            }
        }

        if ($edits === []) {
            return [
                'changed'  => false,
                'contents' => $contents,
                'warnings' => $warnings,
            ];
        }

        if ($needsObjectManagerImport) {
            $importEdit = $this->buildUseImportEdit(
                $contents,
                'use GCWorld\\ObjectManager\\Attributes\\ObjectManager;'
            );
            if ($importEdit !== null) {
                $edits[] = $importEdit;
            }
        }

        if ($needsObjectManagerMethodImport) {
            $importEdit = $this->buildUseImportEdit(
                $contents,
                'use GCWorld\\ObjectManager\\Enums\\ObjectManagerMethod;'
            );
            if ($importEdit !== null) {
                $edits[] = $importEdit;
            }
        }

        if ($needsObjectFactoryImport) {
            $importEdit = $this->buildUseImportEdit(
                $contents,
                'use GCWorld\\ObjectManager\\Attributes\\ObjectFactory;'
            );
            if ($importEdit !== null) {
                $edits[] = $importEdit;
            }
        }

        usort(
            $edits,
            static fn(array $left, array $right): int => $right['start'] <=> $left['start']
        );

        foreach ($edits as $edit) {
            $contents = substr($contents, 0, $edit['start']).$edit['replacement'].substr($contents, $edit['end']);
        }

        return [
            'changed'  => true,
            'contents' => $contents,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return string[]
     */
    private function expandPath(string $path): array
    {
        if (is_file($path)) {
            return [realpath($path) ?: $path];
        }

        if (!is_dir($path)) {
            throw new RuntimeException('Path not found: '.$path);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, array{
     *   name:string,
     *   hasObjectManagerAttribute:bool,
     *   classTokenStart:int,
     *   docblock:?array{start:int,end:int,text:string},
     *   methods:array<string, array{start:int,indent:string,hasObjectFactoryAttribute:bool}>
     * }>
     */
    private function discoverClasses(string $contents): array
    {
        $tokens = token_get_all($contents);
        $classes = [];
        $offsets = [];
        $offset = 0;
        foreach ($tokens as $index => $token) {
            $offsets[$index] = $offset;
            $offset += strlen(is_array($token) ? $token[1] : $token);
        }

        $tokenCount = count($tokens);
        for ($index = 0; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            $previous = $this->previousSignificantToken($tokens, $index);
            if ($previous !== null && is_array($previous) && in_array($previous[0], [T_NEW, T_DOUBLE_COLON], true)) {
                continue;
            }

            $className = $this->readNextIdentifier($tokens, $index + 1);
            if ($className === null) {
                continue;
            }

            [$classStart, $classEnd] = $this->locateClassBounds($tokens, $offsets, $index);
            $docblock = $this->locatePrecedingDocblock($tokens, $offsets, $index);
            $prefix = substr(
                $contents,
                $docblock['end'] ?? $offsets[$index],
                $offsets[$index] - ($docblock['end'] ?? $offsets[$index])
            );

            $classes[] = [
                'name'                     => $className,
                'hasObjectManagerAttribute' => str_contains($prefix, '#[')
                    && (str_contains($prefix, 'ObjectManager') || str_contains($prefix, 'Attributes\\ObjectManager')),
                'classTokenStart'          => $offsets[$index],
                'docblock'                 => $docblock,
                'methods'                  => $this->discoverMethods($tokens, $offsets, $classStart, $classEnd, $contents),
            ];
        }

        return $classes;
    }

    /**
     * @return array<string, array{start:int,indent:string,hasObjectFactoryAttribute:bool}>
     */
    private function discoverMethods(array $tokens, array $offsets, int $classStart, int $classEnd, string $contents): array
    {
        $methods = [];
        $depth = 0;
        $inClass = false;
        $classBodyDepth = 0;
        $source = $this->rebuildTokenSlice($tokens);

        foreach ($tokens as $index => $token) {
            $tokenText = is_array($token) ? $token[1] : $token;
            $tokenStart = $offsets[$index];
            $tokenEnd = $tokenStart + strlen($tokenText);

            if ($tokenStart < $classStart) {
                if ($tokenText === '{') {
                    ++$depth;
                } elseif ($tokenText === '}') {
                    --$depth;
                }
                continue;
            }

            if ($tokenEnd > $classEnd) {
                break;
            }

            if (!$inClass && $tokenText === '{') {
                $inClass = true;
                ++$depth;
                $classBodyDepth = $depth;
                continue;
            }

            if ($tokenText === '{') {
                ++$depth;
                continue;
            }

            if ($tokenText === '}') {
                --$depth;
                continue;
            }

            if (!$inClass || $depth !== $classBodyDepth || !is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            $methodName = $this->readNextIdentifier($tokens, $index + 1);
            if ($methodName === null) {
                continue;
            }

            $prefixStart = $this->findMethodPrefixStart($tokens, $offsets, $index);
            $prefix = substr($source, $prefixStart, $tokenStart - $prefixStart);
            $lineStart = strrpos(substr($contents, 0, $tokenStart), "\n");
            $lineStart = $lineStart === false ? 0 : $lineStart + 1;
            $indent = substr($contents, $lineStart, $tokenStart - $lineStart);
            $indent = preg_match('/^\s*$/', $indent) ? $indent : '';

            $methods[$methodName] = [
                'start'                    => $lineStart,
                'indent'                   => $indent,
                'hasObjectFactoryAttribute' => str_contains($prefix, '#[')
                    && (str_contains($prefix, 'ObjectFactory') || str_contains($prefix, 'Attributes\\ObjectFactory')),
            ];
        }

        return $methods;
    }

    private function rebuildTokenSlice(array $tokens): string
    {
        $buffer = '';
        foreach ($tokens as $token) {
            $buffer .= is_array($token) ? $token[1] : $token;
        }

        return $buffer;
    }

    /**
     * @return array{start:int,end:int}|array{0:int,1:int}
     */
    private function locateClassBounds(array $tokens, array $offsets, int $classIndex): array
    {
        $tokenCount = count($tokens);
        $depth = 0;
        $classStart = $offsets[$classIndex];

        for ($index = $classIndex; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            $tokenText = is_array($token) ? $token[1] : $token;
            if ($tokenText === '{') {
                ++$depth;
                if ($depth === 1) {
                    $classStart = $offsets[$index];
                    continue;
                }
            } elseif ($tokenText === '}') {
                --$depth;
                if ($depth === 0) {
                    return [$classStart, $offsets[$index] + 1];
                }
            }
        }

        return [$classStart, strlen($this->rebuildTokenSlice($tokens))];
    }

    /**
     * @return array{start:int,end:int,text:string}|null
     */
    private function locatePrecedingDocblock(array $tokens, array $offsets, int $index): ?array
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];
            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                return [
                    'start' => $offsets[$cursor],
                    'end'   => $offsets[$cursor] + strlen($token[1]),
                    'text'  => $token[1],
                ];
            }

            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                return null;
            }

            return null;
        }

        return null;
    }

    private function findMethodPrefixStart(array $tokens, array $offsets, int $functionIndex): int
    {
        $start = $offsets[$functionIndex];
        for ($cursor = $functionIndex - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT], true)) {
                $start = $offsets[$cursor];
                continue;
            }

            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                $start = $offsets[$cursor];
                continue;
            }

            if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                $start = $offsets[$cursor];
                continue;
            }

            break;
        }

        return $start;
    }

    /**
     * @return array{
     *   method:string,
     *   name:?string,
     *   namespace:?string,
     *   gc:?int,
     *   factories:array<int, string>
     * }|null
     */
    private function parseMetadata(string $docblock): ?array
    {
        if (!preg_match('/@om-method\s+([^\s*]+)/', $docblock, $methodMatch)) {
            return null;
        }

        $metadata = [
            'method'    => trim($methodMatch[1]),
            'name'      => $this->matchOptionalValue($docblock, '/@om-name\s+([^\r\n*]+)/'),
            'namespace' => $this->matchOptionalValue($docblock, '/@om-namespace\s+([^\r\n*]+)/'),
            'gc'        => null,
            'factories' => [],
        ];

        $gc = $this->matchOptionalValue($docblock, '/@om-gc\s+([^\r\n*]+)/');
        if ($gc !== null && $gc !== '') {
            $metadata['gc'] = abs((int) trim($gc));
        }

        if (preg_match_all('/@om-factory-(\d+)-method\s+([^\s*]+)/', $docblock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $metadata['factories'][(int) $match[1]] = trim($match[2]);
            }
            ksort($metadata['factories']);
        }

        if ($metadata['name'] !== null) {
            $metadata['name'] = trim($metadata['name']);
        }
        if ($metadata['namespace'] !== null) {
            $metadata['namespace'] = trim($metadata['namespace']);
        }

        return $metadata;
    }

    private function matchOptionalValue(string $docblock, string $pattern): ?string
    {
        if (!preg_match($pattern, $docblock, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function renderObjectManagerAttribute(array $metadata): string
    {
        $arguments = ['method: '.$this->renderMethodEnumReference($metadata['method'])];
        if ($metadata['name'] !== null && $metadata['name'] !== '') {
            $arguments[] = 'name: '.var_export($metadata['name'], true);
        }
        if ($metadata['namespace'] !== null && $metadata['namespace'] !== '') {
            $arguments[] = 'namespace: '.var_export($metadata['namespace'], true);
        }
        if ($metadata['gc'] !== null) {
            $arguments[] = 'gc: '.$metadata['gc'];
        }

        return '#[ObjectManager('.implode(', ', $arguments).')]';
    }

    private function renderMethodEnumReference(string $method): string
    {
        return match ($method) {
            'getObject' => 'ObjectManagerMethod::GetObject',
            'getModel' => 'ObjectManagerMethod::GetModel',
            'getFactoryObject' => 'ObjectManagerMethod::GetFactoryObject',
            'getFactoryModelObject' => 'ObjectManagerMethod::GetFactoryModelObject',
            'getMultiObject' => 'ObjectManagerMethod::GetMultiObject',
            default => throw new RuntimeException('Unsupported ObjectManager method: '.$method),
        };
    }

    private function stripMetadataLines(string $docblock): string
    {
        $lines = preg_split("/\R/", $docblock);
        if ($lines === false) {
            return '';
        }

        $cleaned = [];
        foreach ($lines as $line) {
            if (preg_match('/@om-(?:method|namespace|name|gc|factory-\d+-(?:method|arg))\b/', $line)) {
                continue;
            }

            $cleaned[] = $line;
        }

        while ($cleaned !== [] && trim(end($cleaned)) === '*/') {
            $candidate = array_pop($cleaned);
            while ($cleaned !== [] && trim(end($cleaned)) === '*') {
                array_pop($cleaned);
            }
            $cleaned[] = $candidate;
            break;
        }

        $meaningful = array_filter(
            $cleaned,
            static fn(string $line): bool => !in_array(trim($line), ['/**', '*/', '*'], true)
        );

        return $meaningful === [] ? '' : implode("\n", $cleaned);
    }

    private function buildUseImportEdit(string $contents, string $useStatement): ?array
    {
        if (str_contains($contents, $useStatement)) {
            return null;
        }

        if (!preg_match(
            '/\A<\?php(?:\s+declare\s*\([^)]+\)\s*;)?\s*(?:namespace\s+[^;{]+[;{]\s*)?(?:(?:use\s+[^\n;]+;\s*)*)/s',
            $contents,
            $matches
        )) {
            return [
                'start'       => 0,
                'end'         => 0,
                'replacement' => $useStatement."\n",
            ];
        }

        $insertAt = strlen($matches[0]);
        return [
            'start'       => $insertAt,
            'end'         => $insertAt,
            'replacement' => $useStatement."\n",
        ];
    }

    private function previousSignificantToken(array $tokens, int $index): mixed
    {
        for ($cursor = $index - 1; $cursor >= 0; --$cursor) {
            $token = $tokens[$cursor];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private function readNextIdentifier(array $tokens, int $start): ?string
    {
        $tokenCount = count($tokens);
        for ($index = $start; $index < $tokenCount; ++$index) {
            $token = $tokens[$index];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
    }

    private function buildPrefix(?string $path, string $className): string
    {
        return ($path !== null ? $path.': ' : '').$className.' ';
    }
}
