<?php
declare(strict_types=1);
/**
 * BunqImport.php
 * Copyright (c) 2020 james@firefly-iii.org
 *
 * This file is part of the Firefly III bunq importer
 * (https://github.com/firefly-iii/bunq-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Console\Commands;

use App\Console\HaveAccess;
use App\Console\StartDownload;
use App\Console\StartSync;
use App\Console\VerifyJSON;
use Illuminate\Console\Command;
use Log;

/**
 *
 * Class BunqImport
 */
class BunqImport extends Command
{
    use HaveAccess, VerifyJSON, StartDownload, StartSync;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import from bunq using a pre-defined configuration file.';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bunq:import {config : The JSON configuration file}';

    /** @var string */
    protected $downloadIdentifier;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $access = $this->haveAccess();
        if (false === $access) {
            $this->error('Could not connect to your local Firefly III instance.');

            return 1;
        }


        $this->info(sprintf('Welcome to the Firefly III bunq importer, v%s', config('bunq.version')));
        Log::debug(sprintf('Now in %s', __METHOD__));
        $config = $this->argument('config');

        if (!file_exists($config) || (file_exists($config) && !is_file($config))) {
            $message = sprintf('The importer can\'t import: configuration file "%s" does not exist or could not be read.', $config);
            $this->error($message);
            Log::error($message);

            return 1;
        }
        $jsonResult = $this->verifyJSON($config);
        if (false === $jsonResult) {
            $message = 'The importer can\'t import: could not decode the JSON in the config file.';
            $this->error($message);

            return 1;
        }
        $configuration = json_decode(file_get_contents($config), true, 512, JSON_THROW_ON_ERROR);

        $this->line('The import routine is about to start.');
        $this->line('This is invisible and may take quite some time.');
        $this->line('Once finished, you will see a list of errors, warnings and messages (if applicable).');
        $this->line('--------');
        $this->line('Running...');
        $result = $this->startDownload($configuration);
        if (0 === $result) {
            $this->line('Download from bunq complete.');
        }
        if (0 !== $result) {
            $this->warn('Download from bunq resulted in errors.');

            return $result;
        }
        $secondResult = $this->startSync($configuration);
        if (0 === $secondResult) {
            $this->line('Sync to Firefly III complete.');
        }
        if (0 !== $result) {
            $this->warn('Sync to Firefly III resulted in errors.');

            return $result;
        }

        return 0;
    }
}
