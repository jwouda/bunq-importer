<?php
/**
 * GenerateTransactions.php
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

namespace App\Services\Sync;

use App\Exceptions\ImportException;
use App\Services\Configuration\Configuration;
use App\Services\Sync\JobStatus\ProgressInformation;
use Log;

/**
 * Class GenerateTransactions
 */
class GenerateTransactions
{
    use ProgressInformation;
    /** @var array */
    private $accounts;
    /** @var Configuration */
    private $configuration;

    /**
     * @param array $bunq
     *
     * @return array
     */
    public function getTransactions(array $bunq): array
    {
        $return = [];
        /** @var array $entry */
        foreach ($bunq as $bunqAccountId => $entries) {
            $bunqAccountId = (int)$bunqAccountId;
            foreach ($entries as $entry) {
                $return[] = $this->generateTransaction($bunqAccountId, $entry);
                // TODO error handling at this point.
            }
        }
        $this->addMessage(0, sprintf('Parsed %d bunq transactions for further processing.', count($return)));

        return $return;
    }


    /**
     * @param Configuration $configuration
     */
    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
        $this->accounts      = $configuration->getAccounts();
    }

    /**
     * @param array $entry
     *
     * @return array
     * @throws ImportException
     */
    private function generateTransaction(int $bunqAccountId, array $entry): array
    {

        $return = [
            'transactions' => [
                [
                    'type'          => 'withdrawal', // reverse
                    'date'          => substr($entry['created'], 0, 10),
                    'amount'        => 0,
                    'description'   => $entry['description'],
                    'order'         => 0,
                    'currency_code' => $entry['currency_code'],
                    'tags'          => [$entry['type'], $entry['sub_type']],
                ],
            ],
        ];
        if (1 === bccomp($entry['amount'], '0')) {
            // amount is positive: deposit or transfer. Bunq account is destination
            $return['transactions'][0]['type']   = 'deposit';
            $return['transactions'][0]['amount'] = $entry['amount'];

            // destination is bunq
            $return['transactions'][0]['destination_id'] = (int)$this->accounts[$bunqAccountId];

            // source is the other side:
            $return['transactions'][0]['source_iban'] = $entry['counter_party']['iban'];
            $return['transactions'][0]['source_name'] = $entry['counter_party']['display_name'];

            $mappedId = $this->getMappedId($entry['counter_party']['display_name'], (string)$entry['counter_party']['iban']);
            if (null !== $mappedId) {
                $mappedType = $this->getMappedType($mappedId);

                $return['transactions'][0]['type']      = $this->getTransactionType($mappedType, 'asset');
                $return['transactions'][0]['source_id'] = $mappedId;
                unset($return['transactions'][0]['source_iban'], $return['transactions'][0]['source_name']);
            }
        }
        if (-1 === bccomp($entry['amount'], '0')) {
            // amount is negative: withdrawal or transfer.
            $return['transactions'][0]['amount'] = bcmul($entry['amount'], '-1');

            // source is bunq:
            $return['transactions'][0]['source_id'] = (int)$this->accounts[$bunqAccountId];

            // dest is shop
            $return['transactions'][0]['destination_iban']   = $entry['counter_party']['iban'];
            $return['transactions'][0]['destination_name']   = $entry['counter_party']['display_name'];
            $return['transactions'][0]['bunq_payment_id']    = $entry['id'];
            $return['transactions'][0]['external_id']        = $entry['id'];
            $return['transactions'][0]['internal_reference'] = $bunqAccountId;

            $mappedId = $this->getMappedId($entry['counter_party']['display_name'], (string)$entry['counter_party']['iban']);
            if (null !== $mappedId) {
                $return['transactions'][0]['destination_id'] = $mappedId;
                // source is asset, destination is ??, set the transaction type:
                $mappedType = $this->getMappedType($mappedId);

                $return['transactions'][0]['type'] = $this->getTransactionType('asset', $mappedType);
                unset($return['transactions'][0]['destination_iban'], $return['transactions'][0]['destination_name']);
            }
        }

        return $return;
    }

    /**
     * @param string $name
     * @param string $iban
     *
     * @return int|null
     */
    private function getMappedId(string $name, string $iban): ?int
    {
        $fullName = $name;
        if ('' !== $iban) {
            $fullName = sprintf('%s (%s)', $name, $iban);
        }

        return $this->configuration->getMapping()[$fullName] ?? null;
    }

    /**
     * @param int $mappedId
     *
     * @return string
     */
    private function getMappedType(int $mappedId): string
    {
        if (!isset($this->configuration->getAccountTypes()[$mappedId])) {
            Log::warning(sprintf('Cannot find account type for Firefly III account #%d.', $mappedId));
            // TODO contact Firefly III to get it.
        }

        return $this->configuration->getAccountTypes()[$mappedId] ?? 'expense';
    }

    /**
     * @param string $source
     * @param string $destination
     *
     * @return string
     * @throws ImportException
     */
    private function getTransactionType(string $source, string $destination): string
    {
        $combination = sprintf('%s-%s', $source, $destination);
        switch ($combination) {
            default:
                throw new ImportException(sprintf('Unknown combination: %s and %s', $source, $destination));
            case 'asset-expense':
                return 'withdrawal';
            case 'asset-asset':
                return 'transfer';
            case 'revenue-asset':
                return 'deposit';
        }
    }

}