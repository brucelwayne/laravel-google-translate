<?php

namespace Tanmuhittin\LaravelGoogleTranslate\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CompressLanguageFileCommand extends Command
{
    //php artisan compress:json
    protected $signature = 'compress:json';

    protected $description = '压缩 resources/lang 目录和 public/locales 目录下的 JSON 文件';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // 需要遍历的目录
        $directories = [
            resource_path('lang'),        // resources/lang 目录
            public_path('locales')        // public/locales 目录（包括子目录）
        ];

        // 遍历每个目录
        foreach ($directories as $directory) {
            if ($directory === public_path('locales')) {
                // 处理 public/locales 目录及其二级目录
                $this->processLocalesDirectory($directory);
            } else {
                // 直接处理 resources/lang 目录
                $this->processJsonFiles($directory);
            }
        }

        $this->info('所有 JSON 文件已成功压缩。');
    }

    // 处理 public/locales 目录及其子目录中的 JSON 文件
    private function processLocalesDirectory(string $directory)
    {
        // 获取 public/locales 目录下的所有子目录
        $subdirectories = File::directories($directory);

        foreach ($subdirectories as $subdirectory) {
            // 获取每个子目录中的所有文件
            $files = File::files($subdirectory);

            foreach ($files as $file) {
                // 只处理 .json 文件
                if ($file->getExtension() === 'json') {
                    $this->processJsonFile($file);
                }
            }
        }
    }

    // 处理 resources/lang 或单一目录中的 JSON 文件
    private function processJsonFile($file)
    {
        // 读取文件内容
        $jsonContent = File::get($file);

        // 压缩 JSON 内容
        $compressedContent = $this->compressJson($jsonContent);

        // 将压缩后的内容保存回文件
        File::put($file, $compressedContent);

        // 输出成功信息
        $this->info("压缩文件: {$file->getRelativePathname()}");
    }

    // 压缩处理每个 JSON 文件
    private function compressJson(string $jsonContent): string
    {
        // 将 JSON 内容解析为 PHP 数组
        $data = json_decode($jsonContent, true);

        // 如果解析成功，直接使用压缩选项进行编码
        if (json_last_error() === JSON_ERROR_NONE) {
            // 压缩 JSON 格式，移除空白字符（没有改变 JSON 结构）
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // 解析失败，返回原始内容
        $this->error('JSON 格式错误!');
        return $jsonContent;
    }

    // 处理 resources/lang 或单一目录中的 JSON 文件
    private function processJsonFiles(string $directory)
    {
        $files = File::files($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'json') {
                $this->processJsonFile($file);
            }
        }
    }
}
