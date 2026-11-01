<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les textes sources sont en français : chaque clé passée à __(), trans_choice()
 * ou t() (JavaScript) doit avoir son entrée dans le dictionnaire anglais.
 */
class TranslationCoverageTest extends TestCase
{
    /** @return array<string, array{0: list<string>, 1: string, 2: string}> */
    public static function sources(): array
    {
        return [
            'PHP et Blade' => [['app', 'resources/views', 'routes'], 'lang/en.json', "/(?:__|trans_choice)\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/"],
            'JavaScript' => [['resources/js'], 'resources/js/lang/en.json', "/(?<![\\w.])t\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/"],
        ];
    }

    /** @param  list<string>  $directories */
    #[DataProvider('sources')]
    public function test_every_translatable_text_has_an_english_translation(array $directories, string $dictionary, string $pattern): void
    {
        $translations = json_decode(File::get(base_path($dictionary)), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach ($directories as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                preg_match_all($pattern, $file->getContents(), $matches);

                foreach ($matches[1] as $key) {
                    $key = str_replace(["\\'", '\\\\'], ["'", '\\'], $key);

                    if (preg_match('/\p{L}{2}/u', $key) && ! array_key_exists($key, $translations)) {
                        $missing[$key] = $file->getRelativePathname();
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Traductions anglaises manquantes dans '.$dictionary);
    }
}
