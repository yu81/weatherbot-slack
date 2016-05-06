<?php

namespace App\Http\Controllers;

use App\Services\Weather\Forecast;
use App\Http\Requests;

/**
 * Class Weather
 *
 * @package App\Http\Controllers
 */
class Weather extends Controller
{
    /**
     * Default method
     *
     * @return mixed
     */
    public function index()
    {
        $key = env('SLACK_API_KEY', '');
        if ($key === '') {
            return response()->json(
                [
                    'error'   => 'error',
                    'message' => 'API key does not exists.'
                ]
            );
        }

        $forecast = new Forecast($key);
        $result   = $forecast->exec();


        return response()->json($result);
    }
}
