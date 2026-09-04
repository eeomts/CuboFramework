<?php

namespace Cubo\Console;

readonly class Paths
{
    public function __construct(public string $frameworkRoot) {}

    public static function detect(): self
    {
        return new self(dirname(__DIR__, 2));
    }

    public function src(): string
    {
        return $this->frameworkRoot . DIRECTORY_SEPARATOR . 'src';
    }

    public function versionFile(): string
    {
        return $this->frameworkRoot . DIRECTORY_SEPARATOR . 'VERSION';
    }

    public function dist(): string
    {
        return $this->frameworkRoot . DIRECTORY_SEPARATOR . 'dist';
    }

    public function skeleton(string $name): string
    {
        return $this->frameworkRoot . DIRECTORY_SEPARATOR . 'skeletons' . DIRECTORY_SEPARATOR . $name;
    }

    /** @return list<string> nomes dos esqueletos disponiveis */
    public function skeletons(): array
    {
        $dir = $this->frameworkRoot . DIRECTORY_SEPARATOR . 'skeletons';

        if (!is_dir($dir)) {
            return [];
        }

        $names = [];
        foreach (new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isDir()) {
                $names[] = $item->getFilename();
            }
        }

        sort($names);

        return $names;
    }
}
