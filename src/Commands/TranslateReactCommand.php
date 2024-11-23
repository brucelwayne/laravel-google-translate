<?php

namespace Tanmuhittin\LaravelGoogleTranslate\Commands;

use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Tanmuhittin\LaravelGoogleTranslate\TranslationFileTranslators\ReactArrayFileTranslator;

class TranslateReactCommand extends TranslateFilesCommand
{
    //php artisan translate:react
    protected $signature = 'translate:react';

    public function __construct($base_locale = 'en', $target_files = '', $force = false, $json = false, $verbose = true, $excluded_files = '')
    {
        $locales = implode(',', array_keys(LaravelLocalization::getSupportedLocales()));
        parent::__construct($base_locale, $locales, $target_files, $force, $json, $verbose, $excluded_files);
        $this->force = false;
        $this->verbose = false;
        $this->json = true;
    }

    public function handle()
    {
        //Collect input
        $this->base_locale = $this->ask('What is base locale?', config('app.locale', 'en'));
        $file_translator = new ReactArrayFileTranslator($this->base_locale, $this->verbose, $this->force);

        //Start Translating
        $bar = $this->output->createProgressBar(count($this->locales));
        $bar->start();
        $this->line("");
        // loop target locales
        foreach ($this->locales as $locale) {
//            if ($locale == $this->base_locale) {
//                continue;
//            }
            $this->line($this->base_locale . " -> " . $locale . " translating...");
            $file_translator->handle($locale);
            $this->line($this->base_locale . " -> " . $locale . " translated.");
            $bar->advance();
            $this->line("");
        }
        $bar->finish();
        $this->line("");
        $this->line("Translations Completed.");
    }
}