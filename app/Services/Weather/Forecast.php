<?php
/**
 * Created by PhpStorm.
 * User: yusuke
 * Date: 2016/05/06
 * Time: 19:55
 */

namespace App\Services\Weather;


class Forecast
{
    const WEB_BASE_URL = 'http://weather.livedoor.com/area/forecast/';
    const API_BASE_URL = 'http://weather.livedoor.com/forecast/webservice/json/v1';
    private $channelId = '';
    private $key = '';
    public $result = [];

    public function __construct($key = '', $channelId = '')
    {
        $this->key       = $key;
        $this->channelId = isset($channelId) ? $channelId : env('WEATHER_CHANNEL_ID', '');
    }

    public function getPrefectureForecast($locId)
    {
        return json_decode(
            file_get_contents(self::API_BASE_URL . '?city=' . $locId),
            true);

    }

    public function getSpecificForecast($locId)
    {
        $weatherInfo = $this->getPrefectureForecast($locId);
        $r           = array_filter(
            $weatherInfo['pinpointLocations'],
            function ($v) {
                return $v['name'] === '板橋区' || $v['name'] === '新座市';
            });
        $r           = array_first($r);
        $r['name'] .= 'の天気';
        $r['prefLink'] = self::WEB_BASE_URL . $locId;
        $formattedDate = (new \Datetime($weatherInfo['publicTime']))->format('Y年m月d日 H時i分');
        $r['message']  = <<<EOT
[ {$formattedDate} 発表 {$weatherInfo['title']} ]
{$weatherInfo['forecasts'][0]['dateLabel']} の天気 {$weatherInfo['forecasts'][0]['telop']}
{$weatherInfo['forecasts'][1]['dateLabel']} の天気 {$weatherInfo['forecasts'][1]['telop']} 予想最高気温 {$weatherInfo['forecasts'][1]['temperature']['max']['celsius']}℃ / 予想最低気温 {$weatherInfo['forecasts'][1]['temperature']['min']['celsius']}℃
データ提供 {$weatherInfo['copyright']['provider']['name']} {$weatherInfo['copyright']['provider']['link']}
{$weatherInfo['copyright']['title']}
EOT;

        $currentHour  = date('H');
        $r['iconUrl'] = ($currentHour >= 20) ? $weatherInfo['forecasts'][0]['image']['url'] : $weatherInfo['forecasts'][1]['image']['url'];

        return $r;
    }

    public function exec($botChannel = '')
    {
        if ($botChannel === '') {
            $botChannel = env('WEATHER_CHANNEL_ID', '');
        }

        $locationIds = ['130010', '110010'];
        $result      = [];
        foreach ($locationIds as $locId) {
            $result[] = $this->getSpecificForecast($locId);
        }
        $this->result = $result;
        $this->postMessages($botChannel, $result);

        return $result;
    }

    /**
     * @param string $botChannel
     * @param array $result
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
     * @param array $params
     * @return string
     */
    private function postMessage($params)
    {
        $postUrl = 'https://slack.com/api/chat.postMessage';
        $q       = $postUrl . '?' . http_build_query($params);

        return file_get_contents($q);
    }
}
