<?php

namespace Tanmuhittin\LaravelGoogleTranslate\TranslationFileTranslators;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Tanmuhittin\LaravelGoogleTranslate\Contracts\FileTranslatorContract;
use Tanmuhittin\LaravelGoogleTranslate\Helpers\ConsoleHelper;

class ReactArrayFileTranslator implements FileTranslatorContract
{
    use ConsoleHelper;

    private $base_locale;
    private $verbose;
    private $force;

    public function __construct($base_locale, $verbose = true, $force = false)
    {
        $this->base_locale = $base_locale;
        $this->verbose = $verbose;
        $this->force = $force;
    }

    public function handle($target_locale): void
    {
        $translationKeys = $this->extractTranslations();
        $this->saveTranslations($translationKeys, $target_locale);
    }

    private function extractTranslations(): array
    {
        $translationKeys = [];

        $finder = new Finder();
        $finder->in(base_path('resources/react'))
            ->name('*.js')
            ->name('*.jsx')
            ->files();

        $patternUseTranslation = "/useTranslation\s*\(\s*[\"']?(?P<namespace>[\w\/]+)?[\"']?\s*\)/";
        // This pattern captures keys with placeholders like :count
//        $patternTFunction = "/t\s*\(\s*[\"'](?P<key>[^\"']+)[\"']\s*\)/";
//        $patternTFunction = "/t\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/";
        $patternTFunction = "/\bt\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/";
        // This pattern captures keys with placeholders and complex objects
//        $patternTFunctionWithPlaceholders = "/t\s*\(\s*[\"'](?P<key>[^\"']+(:[^\"']+)*)[\"']\s*,\s*\{.*\}\s*\)/";
        //react i18n的占位符，试试看
//        $patternTFunctionWithPlaceholders = "/t\s*\(\s*['\"]([^'\"]+(\{\{[^}]+\}\})*)['\"]\s*,/";
//        $patternTFunctionWithPlaceholders = "/t\s*\(\s*['\"]([^'\"]+(\{\{[^}]+\}\})*)['\"]\s*,\s*\{.*\}\s*\)/";
//        $patternTFunctionWithPlaceholders = "/t\s*\(\s*['\"]([^'\"]+(\{\{[^}]+\}\})*)['\"]\s*,/";
//        $patternTFunctionWithPlaceholders = "/t\s*\(\s*['\"]([^'\"]*?\{\{[^}]+\}\}[^'\"]*?)['\"]\s*,/s";
        $patternTFunctionWithPlaceholders = "/t\s*\(\s*['\"](.*?)['\"]\s*,/";


        foreach ($finder as $file) {
//            if (Str::contains($file, 'Dashboard')) {
//                echo $file . "\n";
//            }

            $contents = $file->getContents();

            // Extract namespace from useTranslation()
            preg_match_all($patternUseTranslation, $contents, $namespaceMatches);
            $namespace = $namespaceMatches['namespace'][0] ?? 'translation';

            // Extract keys from t() (basic keys without objects)
            preg_match_all($patternTFunction, $contents, $keyMatches);
            foreach ($keyMatches[1] as $key) {
                $translationKeys[$namespace][] = $key;
                $this->line("Found key in namespace '{$namespace}': {$key}");
            }

            // Extract keys with placeholders like :count or complex keys with objects
            preg_match_all($patternTFunctionWithPlaceholders, $contents, $keyMatchesWithPlaceholders);

            foreach ($keyMatchesWithPlaceholders[1] as $key) {
                $translationKeys[$namespace][] = $key;
                $this->line("Found key with placeholder in namespace '{$namespace}': {$key}");
            }

//            foreach ($keyMatchesWithPlaceholders[1] as $key) {
//                echo "Found key with placeholder: {$key}\n";
//            }
        }

        return $translationKeys;
    }


    private function saveTranslations(array $translationKeys, string $target_locale): void
    {
        $basePath = public_path("locales/{$target_locale}");

        if (!array_key_exists('translation', $translationKeys)) {
            // If 'translation' doesn't exist, create an empty translation file or handle the case
            $translationKeys['translation'] = [];
        }

        foreach ($translationKeys as $namespace => $keys) {
            $filePath = "{$basePath}/{$namespace}.json";
            $existingTranslations = $this->loadExistingTranslations($filePath);

            foreach ($keys as $key) {
                // Skip if translation already exists and force is not enabled
                if (isset($existingTranslations[$key]) && !$this->force) {
                    $this->line("Skipping existing translation: {$key}");
                    continue;
                }

                if ($target_locale === $this->base_locale) {
                    $existingTranslations[$key] = addslashes($key);
                } else {
                    // Translate the key using the API (in your case, using Str::apiTranslateWithAttributes)
                    $translated = addslashes(Str::apiTranslateWithAttributes($key, $target_locale, $this->base_locale));
                    $existingTranslations[$key] = $translated;
//                    $existingTranslations[$key] = addslashes($key);
                }
                $this->line("Adding translation for '{$key}': {$key}");
            }

            $this->writeToFile($filePath, $existingTranslations);
        }
    }


    private function loadExistingTranslations(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $jsonContent = file_get_contents($filePath);
        return json_decode($jsonContent, true) ?? [];
    }

    private function writeToFile(string $filePath, array $translations): void
    {
        // Ensure the directory exists, create it if necessary
        $directory = dirname($filePath);  // Get the directory part of the file path
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);  // Create the directory recursively
        }

        // Compress the JSON content into a single line (no pretty print)
        $jsonContent = json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);

        // Save the JSON content to the file
        file_put_contents($filePath, $jsonContent);
    }

}
