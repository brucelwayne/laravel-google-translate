<?php

namespace Tanmuhittin\LaravelGoogleTranslate\TranslationFileTranslators;

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
        $patternTFunction = "/t\s*\(\s*[\"'](?P<key>[^\"']+)[\"']\s*\)/";
        // This pattern captures keys with placeholders and complex objects
        $patternTFunctionWithPlaceholders = "/t\s*\(\s*[\"'](?P<key>[^\"']+(:[^\"']+)*)[\"']\s*,\s*\{.*\}\s*\)/";

        foreach ($finder as $file) {
            $contents = $file->getContents();

            // Extract namespace from useTranslation()
            preg_match_all($patternUseTranslation, $contents, $namespaceMatches);
            $namespace = $namespaceMatches['namespace'][0] ?? 'translation';

            // Extract keys from t() (basic keys without objects)
            preg_match_all($patternTFunction, $contents, $keyMatches);
            foreach ($keyMatches['key'] as $key) {
                // Check if key contains placeholders and add it to translation array
                $translationKeys[$namespace][] = $key;
                $this->line("Found key in namespace '{$namespace}': {$key}");
            }

            // Extract keys with placeholders like :count or complex keys with objects
            preg_match_all($patternTFunctionWithPlaceholders, $contents, $keyMatchesWithPlaceholders);
            foreach ($keyMatchesWithPlaceholders['key'] as $key) {
                $translationKeys[$namespace][] = $key;
                $this->line("Found key with placeholder in namespace '{$namespace}': {$key}");
            }
        }

        return $translationKeys;
    }


    private function saveTranslations(array $translationKeys, string $target_locale): void
    {
        $basePath = base_path("resources/react/Locales/{$target_locale}");

        foreach ($translationKeys as $namespace => $keys) {
            $filePath = "{$basePath}/{$namespace}.json";
            $existingTranslations = $this->loadExistingTranslations($filePath);

            foreach ($keys as $key) {
                // Skip if translation already exists and force is not enabled
                if (isset($existingTranslations[$key]) && !$this->force) {
                    $this->line("Skipping existing translation: {$key}");
                    continue;
                }

                // For demonstration purposes, translation is the same as the key
                $existingTranslations[$key] = addslashes($key);
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
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        $jsonContent = json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($filePath, $jsonContent);
    }
}
