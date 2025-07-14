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
            ->name('*.tsx') // 添加对 .tsx 文件的支持（如果使用 TypeScript）
            ->files();

        $patternUseTranslation = "/useTranslation\s*\(\s*['\"]?([\w\/-]+)?['\"]?\s*\)/";
        // 匹配 t('key') 或 t("key")，包括 JSX 属性中的 {t('key')}
        $patternTFunction = '/\bt\s*\(\s*([\'"])(.*?)\1/s';
        // 匹配带占位符或额外参数的 t() 调用，包括 JSX 属性
        $patternTFunctionWithPlaceholders = "/\{?\s*\bt\s*\(\s*['\"]([^'\"]*?(?:\{\{[^}]+\}\}[^'\"]*?)*)['\"]\s*(?:,\s*\{.*?\})?\s*\}?\s*/s";

        foreach ($finder as $file) {
            $contents = $file->getContents();

            // 调试：输出文件路径
            $this->line("Processing file: {$file->getPathname()}");

            // Extract namespace from useTranslation()
            preg_match_all($patternUseTranslation, $contents, $namespaceMatches);
            $namespace = $namespaceMatches[1][0] ?? 'translation';
            $this->line("Detected namespace: {$namespace}");

            // Extract simple t() keys
            preg_match_all($patternTFunction, $contents, $keyMatches);

            foreach ($keyMatches[2] as $key) { // 注意这里是 [2]，因为正则用了2个捕获组
                $translationKeys[$namespace][] = $key;
                $this->line("Found key in namespace '{$namespace}': {$key}");

                if (stripos($key, 'one-time code') !== false) {
                    $this->line("🔍 Matched one-time code string: {$key}");
                }
            }

            // Extract t() keys with placeholders or in JSX
            preg_match_all($patternTFunctionWithPlaceholders, $contents, $keyMatchesWithPlaceholders);
            foreach ($keyMatchesWithPlaceholders[1] as $key) {
                $translationKeys[$namespace][] = $key;
                $this->line("Found key with placeholder or JSX in namespace '{$namespace}': {$key}");

                if (stripos($key, 'one-time code') !== false) {
                    $this->line("🔍 Matched one-time code string (with placeholder): {$key}");
                }
            }
        }

        // 去重翻译键
        foreach ($translationKeys as $namespace => $keys) {
            $translationKeys[$namespace] = array_unique($keys);
        }

        return $translationKeys;
    }

    private function saveTranslations(array $translationKeys, string $target_locale): void
    {
        $basePath = public_path("locales/{$target_locale}");
        foreach ($translationKeys as $namespace => $keys) {
            if (empty($namespace)) {
                $namespace = 'translation';
            }
            $filePath = "{$basePath}/{$namespace}.json";
            $existingTranslations = $this->loadExistingTranslations($filePath);

            foreach ($keys as $key) {
                if (isset($existingTranslations[$key]) && !$this->force) {
                    $this->line("Skipping existing translation: {$key}");
                    continue;
                }

                if ($target_locale === $this->base_locale) {
                    $existingTranslations[$key] = $key;
                } else {
                    $translated = Str::apiTranslateWithAttributes($key, $target_locale, $this->base_locale);
                    $this->line("Translating '{$key}' to '{$translated}' for locale '{$target_locale}'");
                    $existingTranslations[$key] = $translated;
                }
                $this->line("Adding translation for '{$key}': {$existingTranslations[$key]}");
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
