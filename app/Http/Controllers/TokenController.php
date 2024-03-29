<?php
declare(strict_types=1);
/**
 * TokenController.php
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

namespace App\Http\Controllers;


use App\Bunq\ApiContext\ApiContextManager;
use bunq\Exception\BunqException;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Request\SystemInformationRequest;
use GrumpyDictator\FFIIIApiSupport\Response\SystemInformationResponse;
use Illuminate\Http\JsonResponse;
use Log;

/**
 * Class TokenController
 */
class TokenController extends Controller
{
    /**
     * Check if the Firefly III API responds properly.
     *
     * @return JsonResponse
     * @throws \App\Exceptions\ImportException
     */
    public function doValidate(): JsonResponse
    {
        $response = ['result' => 'OK', 'message' => null];
        $token    = (string)config('bunq.access_token');
        $uri      = (string)config('bunq.uri');
        Log::debug(sprintf('Going to try and access %s', $uri));
        $request = new SystemInformationRequest($uri, $token);
        try {
            $result = $request->get();
        } catch (ApiHttpException $e) {
            $response = ['result' => 'NOK', 'message' => $e->getMessage()];
        }

        if (isset($result)) {
            $minimum = config('bunq.minimum_version');
            $compare = version_compare($minimum, $result->version);
            if (1 === $compare) {
                $errorMessage = sprintf('Your Firefly III version %s is below the minimum required version %s', $result->version, $minimum);
                $response     = ['result' => 'NOK', 'message' => $errorMessage];
            }
        }

        // validate connection to bunq, create API context.
        try {
            ApiContextManager::getApiContext();
        } catch (ApiHttpException $e) {
            $errorMessage = sprintf('bunq complained: %s', $e->getMessage());
            $response     = ['result' => 'NOK', 'message' => $errorMessage];
        }

        return response()->json($response);
    }

    /**
     * Same thing but not over JSON.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     * @throws \App\Exceptions\ImportException
     */
    public function index()
    {
        $token = config('bunq.access_token');
        $uri   = config('bunq.uri');
        Log::debug(sprintf('Going to try and access %s', $uri));
        $request      = new SystemInformationRequest($uri, $token);
        $errorMessage = 'No error message.';
        $isError      = false;
        $result       = null;
        $compare      = 1;
        try {
            /** @var SystemInformationResponse $result */
            $result = $request->get();
        } catch (ApiHttpException $e) {
            $errorMessage = $e->getMessage();
            $isError      = true;
        }

        if (false === $isError) {
            $minimum = config('bunq.minimum_version');
            $compare = version_compare($minimum, $result->version);
        }
        if (false === $isError && 1 === $compare) {
            $errorMessage = sprintf('Your Firefly III version %s is below the minimum required version %s', $result->version, $minimum);
            $isError      = true;
        }

        // validate connection to bunq, create API context.
        try {
            ApiContextManager::getApiContext();
        } catch (ApiHttpException $e) {
            $errorMessage = sprintf('bunq complained: %s', $e->getMessage());
            $isError =true;
        }

        if (false === $isError) {
            return redirect(route('index'));
        }
        $pageTitle = 'Token error';

        return view('token.index', compact('errorMessage', 'pageTitle'));
    }

}
