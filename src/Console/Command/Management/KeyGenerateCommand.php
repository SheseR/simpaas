<?php

namespace Levtechdev\Simpaas\Console\Command\Management;

use Illuminate\Console\ConfirmableTrait;
use SimPass\Console\Command\AbstractCommand;

class KeyGenerateCommand extends AbstractCommand
{
    use ConfirmableTrait;

    const ALLOWED_KEY_NAMES = [
        'SENSITIVE_DATA_TOKEN',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'key:generate
                    {--keyName= : Specify key to generate}
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the application key';

    /**
     * Execute the console command.
     * @param string $keyName
     *
     * @return void
     * @throws \Exception
     */
    public function handle(string $keyName = 'APP_KEY'): void
    {
        $baseEncode = true;

        $keyNameOption = $this->option('keyName');
        if (!empty($keyNameOption)) {
            if (!in_array($keyNameOption, self::ALLOWED_KEY_NAMES)) {

                $this->warn(sprintf('%s not allowed to change.', $keyNameOption));

                return;
            }

            $keyName = $keyNameOption;
            $baseEncode = false;
        }

        if (!empty(env($keyName))) {

            $this->warn(sprintf(
                'Key [%s] already exists. Please remove the value manually from the .env file to re-generate it',
                $keyName
            ));

            return;
        }

        $key = $this->generateRandomKey($baseEncode);

        if ($this->option('show')) {

            $this->line('<comment>'.$key.'</comment>');

            return;
        }

        // Next, we will replace the application key in the environment file so it is
        // automatically setup for this developer. This key gets generated using a
        // secure random byte generator and is later base64 encoded for storage.
        if (! $this->setKeyInEnvironmentFile($key, $keyName)) {

            return;
        }

        $this->laravel['config']['app.key'] = $key;

        $this->info("Key $keyName with value [$key] set successfully.");
    }

    /**
     * Generate a random key for the application.
     * @param bool $baseEncode
     *
     * @return string
     * @throws \Exception
     */
    protected function generateRandomKey(bool $baseEncode = true): string
    {
        $lengthKey = $this->laravel['config']['app.cipher'] == 'AES-128-CBC' ? 16 : 32;
        $key = random_bytes($lengthKey);
        if ($baseEncode) {

            return 'base64:'.base64_encode($key);
        }

        return substr(bin2hex($key), 0, $lengthKey);
    }

    /**
     * Set the application key in the environment file.
     *
     * @param string $key
     * @param string $keyName
     *
     * @return bool
     */
    protected function setKeyInEnvironmentFile(string $key, string $keyName): bool
    {
        if (strlen($this->getCurrentKey($keyName)) !== 0 && (! $this->confirmToProceed())) {
            return false;
        }

        $this->writeNewEnvironmentFileWith($key, $keyName);

        return true;
    }

    /**
     * Write a new environment file with the given key.
     *
     * @param string $key
     * @param string $keyName
     *
     * @return void
     */
    protected function writeNewEnvironmentFileWith(string $key, string $keyName): void
    {
        file_put_contents($this->laravel->basePath('.env'), preg_replace(
            $this->keyReplacementPattern($keyName),
            $keyName.'='.$key,
            file_get_contents($this->laravel->basePath('.env'))
        ));
    }

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @param string $keyName
     *
     * @return string
     */
    protected function keyReplacementPattern(string $keyName): string
    {
        $escaped = preg_quote('='.$this->getCurrentKey($keyName), '/');

        return "/^$keyName{$escaped}/m";
    }

    /**
     * @param string $keyName
     *
     * @return string|null
     */
    protected function getCurrentKey(string $keyName): string|null
    {
        if ($keyName === 'APP_KEY') {
            $currentKey = $this->laravel['config']['app.key'] ?? env($keyName);
        } else {
            $currentKey = env($keyName);
        }

        return $currentKey;
    }
}
