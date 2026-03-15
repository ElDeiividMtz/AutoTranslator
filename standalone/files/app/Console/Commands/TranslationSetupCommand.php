<?php
/*
 * AutoTranslator for Pterodactyl Panel
 * Author: ElDeiividMtz
 * License: MIT
 */

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslationSetupCommand extends Command
{
    protected $signature = 'translate:setup';
    protected $description = 'Setup AutoTranslator plugin: create language folders, permissions, and directories';

    public function handle(): int
    {
        $this->info('Setting up AutoTranslator plugin...');
        $this->newLine();

        $languages = array_keys(config('autotranslator.languages', ['es' => 'Spanish', 'pt' => 'Portuguese', 'fr' => 'French', 'de' => 'German', 'it' => 'Italian']));

        // 1. Create language folders in resources/lang
        $langBase = resource_path('lang');
        foreach ($languages as $lang) {
            $langDir = "{$langBase}/{$lang}";
            if (!File::isDirectory($langDir)) {
                File::makeDirectory($langDir, 0755, true);
                // Copy validation.php from en as base
                $enValidation = "{$langBase}/en/validation.php";
                if (File::exists($enValidation)) {
                    File::copy($enValidation, "{$langDir}/validation.php");
                }
                $this->info("  Created language folder: resources/lang/{$lang}");
            } else {
                $this->line("  Language folder exists: resources/lang/{$lang}");
            }
        }

        // 2. Create translations storage directory
        $translationsDir = storage_path('app/translations');
        if (!File::isDirectory($translationsDir)) {
            File::makeDirectory($translationsDir, 0775, true);
            $this->info('  Created storage/app/translations/');
        }

        // 3. Fix permissions
        $this->fixPermissions();

        // 4. Clear caches
        $this->call('view:clear');
        $this->call('route:clear');
        $this->call('cache:clear');

        $this->newLine();
        $this->info('AutoTranslator setup complete!');
        $this->info('Go to Admin > Translations to scan and auto-translate your panel.');

        return 0;
    }

    private function fixPermissions(): void
    {
        $paths = [
            storage_path('app/translations'),
            resource_path('lang'),
        ];

        foreach ($paths as $path) {
            if (PHP_OS_FAMILY !== 'Windows' && File::isDirectory($path)) {
                $user = preg_replace('/[^a-zA-Z0-9_.-]/', '', config('autotranslator.web_user', 'www-data'));
                $group = preg_replace('/[^a-zA-Z0-9_.-]/', '', config('autotranslator.web_group', 'www-data'));
                @exec("chown -R " . escapeshellarg("{$user}:{$group}") . " " . escapeshellarg($path) . " 2>/dev/null");
                @exec("chmod -R 775 " . escapeshellarg($path) . " 2>/dev/null");
            }
        }

        $this->info('  Permissions fixed.');
    }
}
