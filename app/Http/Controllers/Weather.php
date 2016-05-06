<?php

namespace App\Http\Controllers;

use App\Services\Weather\Forecast;
use App\Http\Requests;

class Weather extends Controller
{
    public function index()
    {
        $key = env('SLACK_API_KEY', '');
        if ($key === '') {
            return response()->json([
                'error'   => 'error',
                'message' => 'API key does not exists.'
            ]);
        }

        $forecast = new Forecast($key);
        $result   = $forecast->exec();


        return response()->json($result);
    }
}
