<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

#[Group('platform')]
class TestSuiteDocumentationStandardTest extends TestCase
{
    /**
     * A file with at least this many #[Test] methods must carry section separators.
     */
    private const MIN_TESTS_FOR_SECTIONS = 11;

    /**
     * The canonical separator: exactly 20 '=', space, lowercase label, space, exactly 20 '='.
     */
    private const CANONICAL = '/^ {4}\/\/ ={20} [a-z0-9][^\n]*? ={20}$/';

    /**
     * Any line that looks like it is trying to be a separator.
     */
    private const SEPARATOR_LIKE = '/^\s*\/\/\s*=+.*$|^\s*\/\/\s*=+\s*$/';

    #[Test]
    public function every_section_separator_uses_the_canonical_format(): void
    {
        $violations = [];

        foreach ($this->filePaths() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $index => $line) {
                if (preg_match(self::SEPARATOR_LIKE, $line) !== 1) {
                    continue;
                }

                if (preg_match(self::CANONICAL, $line) === 1) {
                    continue;
                }

                $violations[] = sprintf(
                    '%s:%d  %s',
                    $this->relative($path),
                    $index + 1,
                    trim($line)
                );
            }
        }

        $this->assertSame([], $violations, sprintf(
            "%d separator line(s) do not match the canonical format.\n".
            "Canonical: // ==================== label ====================\n%s",
            count($violations),
            implode("\n", $violations)
        ));
    }

    #[Test]
    public function separators_are_surrounded_by_exactly_one_blank_line(): void
    {
        $violations = [];

        foreach ($this->filePaths() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            foreach ($lines as $index => $line) {
                if (preg_match(self::CANONICAL, $line) !== 1) {
                    continue;
                }

                $before = $lines[$index - 1] ?? null;
                $after = $lines[$index + 1] ?? null;

                if ($before === null || (trim($before) !== '' && trim($before) !== '{')) {
                    $violations[] = $this->relative($path).':'.($index + 1).' — missing blank line before';
                }

                if ($after === null || trim($after) !== '') {
                    $violations[] = $this->relative($path).':'.($index + 1).' — missing blank line after';
                }
            }
        }

        $this->assertSame([], $violations, sprintf(
            "%d separator(s) are not surrounded by exactly one blank line.\n%s",
            count($violations),
            implode("\n", $violations)
        ));
    }

    #[Test]
    public function large_test_classes_carry_section_separators(): void
    {
        $violations = [];

        foreach ($this->filePaths() as $path) {
            $contents = file_get_contents($path);
            $testCount = preg_match_all('/^\s*#\[Test\]/m', $contents);

            if ($testCount < self::MIN_TESTS_FOR_SECTIONS) {
                continue;
            }

            if (preg_match(self::CANONICAL.'m', $contents) === 1) {
                continue;
            }

            $violations[] = sprintf('%s (%d tests, no sections)', $this->relative($path), $testCount);
        }

        $this->assertSame([], $violations, sprintf(
            "%d file(s) with %d or more tests have no section separators.\n%s",
            count($violations),
            self::MIN_TESTS_FOR_SECTIONS,
            implode("\n", $violations)
        ));
    }

    // ==================== helpers ====================

    /**
     * Every *Test.php file in the suite, excluding this lint test itself.
     *
     * @return list<string>
     */
    private function filePaths(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path('tests'), RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $paths = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Test.php')) {
                continue;
            }

            if ($file->getPathname() === __FILE__) {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        sort($paths);

        return $paths;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }
}
