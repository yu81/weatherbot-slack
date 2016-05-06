<?php

namespace App\Services\Weather;

/**
 * Class Forecast
 *
 * @package App\Services\Weather
 */
class Forecast
{
    const WEB_BASE_URL = 'http://weather.livedoor.com/area/forecast/';
    const API_BASE_URL = 'http://weather.livedoor.com/forecast/webservice/json/v1';
    private $channelId = '';
    private $key = '';
    public $result = [];

    /**
     * Forecast constructor.
     *
     * @param string $key       SlackのAPIキー
     * @param string $channelId postするSlackチャンネル
     */
    public function __construct($key = '', $channelId = '')
    {
        $this->key       = $key;
        $this->channelId = isset($channelId) ?
            $channelId :
            env('WEATHER_CHANNEL_ID', '');
    }

    /**
     * 都道府県エリアレベルの予報を取得
     *
     * @param string|int $locId エリアID
     *
     * @return mixed
     */
    public function getPrefectureForecast($locId)
    {
        return json_decode(
            file_get_contents(self::API_BASE_URL . '?city=' . $locId),
            true
        );

    }

    /**
     * 都道府県エリアレベルの予報から、特定市町村レベルの部分の情報を取得し、表示用データを生成。
     *
     * @param string|int $locId エリアID
     *
     * @return mixed
     */
    public function getSpecificForecast($locId)
    {
        $weatherInfo = $this->getPrefectureForecast($locId);
        $r           = array_filter(
            $weatherInfo['pinpointLocations'],
            function ($v) {
                return count(array_intersect([$v['name']], $this->getCityNames())) === 1;
            }
        );
        $r           = array_first($r);
        $r['name'] .= 'の天気';
        $r['prefLink'] = self::WEB_BASE_URL . $locId;
        $formattedDate = (new \Datetime($weatherInfo['publicTime']))->format('Y年m月d日 H時i分');
        $r['message']  = <<<EOT
[ {$formattedDate} 発表 {$weatherInfo['title']} ]
{$weatherInfo['forecasts'][0]['dateLabel']} の天気 {$weatherInfo['forecasts'][0]['telop']}
{$weatherInfo['forecasts'][1]['dateLabel']} の天気 {$weatherInfo['forecasts'][1]['telop']} 予想最高気温 {$weatherInfo['forecasts'][1]['temperature']['max']['celsius']}℃ / 予想最低気温 {$weatherInfo['forecasts'][1]['temperature']['min']['celsius']}℃

{$weatherInfo['description']['text']}

データ提供 {$weatherInfo['copyright']['provider'][0]['name']} {$weatherInfo['copyright']['provider'][0]['link']}
{$weatherInfo['copyright']['title']}
EOT;

        $currentHour  = date('H');
        $r['iconUrl'] = ($currentHour >= 20) ? $weatherInfo['forecasts'][0]['image']['url'] : $weatherInfo['forecasts'][1]['image']['url'];

        return $r;
    }

    /**
     * データ取得とSlackへのpostを実行
     *
     * @param string $botChannel  postするSlackチャンネル
     * @param bool   $postToSlack Slackにpostするかどうか
     *
     * @return array
     */
    public function exec($botChannel = '', $postToSlack = true)
    {
        if ($botChannel === '') {
            $botChannel = env('WEATHER_CHANNEL_ID', '');
        }

        $locationIds = explode(',', env('WEATHER_LOCATION_IDS', ''));
        $result      = [];
        foreach ($locationIds as $locId) {
            $result[] = $this->getSpecificForecast($locId);
        }
        $this->result = $result;
        if ($postToSlack) {
            $this->postMessages($botChannel, $result);
        }

        return $result;
    }

    /**
     * Slackへのpostを実行
     *
     * @param string $botChannel postするSlackチャンネル
     * @param array  $result     postするtextデータのもととなる
     *                           API実行結果が入った配列
     */
    private function postMessages($botChannel, $result)
    {
        $params = [
            'token'        => $this->key,
            'channel'      => $botChannel,
            'as_user'      => false,
            'username'     => 'WeatherBot',
            'parse'        => 'full',
            'unfurl_links' => true,
            'unfurl_media' => true,
        ];

        foreach ($result as $f) {
            $this->postMessage(
                array_merge(
                    $params,
                    [
                        'text'     => $f['link'] . ' ' . $f['prefLink'] . "\n" . $f['message'],
                        'icon_url' => $f['iconUrl'],
                    ]
                )
            );
        }
    }

    /**
     * Slackへのpostを実行する。
     *
     * @param array $params postするtextデータのもととなるAPI実行結果と
     *                      リクエストパラメータが入った配列
     *
     * @return string post実行結果
     */
    private function postMessage($params)
    {
        $postUrl = 'https://slack.com/api/chat.postMessage';
        // 引数パラメータからクエリストリングを生成。
        $q = $postUrl . '?' . http_build_query($params);

        return file_get_contents($q);
    }

    /**
     * 環境変数にあるデータ取得対象市町村名を配列に入れて返す。
     *
     * @return string[]
     */
    public function getCityNames()
    {
        $cityNames = env('WEATHER_LOCATION_CITY_NAMES', '');

        return explode(',', $cityNames);
    }
}
