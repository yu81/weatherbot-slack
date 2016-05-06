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

        return $r;
    }

    public function exec($botChannel)
    {
        $locationIds = ['130010', '110010'];
        $result      = [];
        foreach ($locationIds as $locId) {
            $result[] = $this->getSpecificForecast($locId);
        }

        $this->postMessages($botChannel, $result);

        return $result;
    }

    /**
     * @param string $botChannel
     * @param array $result
     */
    private function postMessages($botChannel, $result)
    {
        $postUrl = 'https://slack.com/api/chat.postMessage?token=' . $this->key . '&channel=' . $botChannel . '&as_user=false' . '&username=WeatherBot' . '&parse=full' . '&unfurl_links=true' . '&unfurl_media=true' . '&text=';
        foreach ($result as $f) {
            $this->postMessage($postUrl, $f);
        }
    }

    /**
     * @param string $postUrl
     * @param string $f
     * @return string
     */
    private function postMessage($postUrl, $f)
    {
        return file_get_contents($postUrl . rawurlencode($f['link'] . ' ' . $f['prefLink']));
    }
}
