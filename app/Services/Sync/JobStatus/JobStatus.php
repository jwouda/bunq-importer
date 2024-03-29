<?php
declare(strict_types=1);
/**
 * JobStatus.php
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

namespace App\Services\Sync\JobStatus;

/**
 * Class JobStatus
 */
class JobStatus
{
    /** @var string */
    public const JOB_WAITING = 'waiting_to_start';
    /** @var string */
    public const JOB_RUNNING = 'job_running';
    /** @var string */
    public const JOB_ERRORED = 'job_errored';
    /** @var string */
    public const JOB_DONE = 'job_done';

    /** @var string */
    public $status;
    /** @var array */
    public $errors;
    /** @var array */
    public $warnings;
    /** @var array */
    public $messages;

    /**
     * ImportJobStatus constructor.
     */
    public function __construct()
    {
        $this->status   = self::JOB_WAITING;
        $this->errors   = [];
        $this->warnings = [];
        $this->messages = [];
    }

    /**
     * @param array $array
     *
     * @return static
     */
    public static function fromArray(array $array): self
    {
        $config           = new self;
        $config->status   = $array['status'];
        $config->errors   = $array['errors'] ?? [];
        $config->warnings = $array['warnings'] ?? [];
        $config->messages = $array['messages'] ?? [];

        return $config;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'status'   => $this->status,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
            'messages' => $this->messages,
        ];
    }
}
