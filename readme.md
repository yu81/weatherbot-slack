# weatherbot-slack
- [![Deploy](https://www.herokucdn.com/deploy/button.png)](https://heroku.com/deploy)

## What is it?
http://weather.livedoor.com/weather_hacks/webservice  
の天気予報APIを利用して、SlackBotを使って特定のSlackチャンネルに投稿する。
事前のSlack関連設定が必要。

## deploy
### Heroku
- php artisan key:generate
- heroku create YOUR_APP_NAME
- configure git remote heroku as official instruction https://devcenter.heroku.com/articles/git
- git push heroku master
- heroku config:set $(cat .env | egrep "^APP_KEY")
- heroku config:set SLACK_API_KEY=YOUR_SLACK_API_KEY
- heroku config:set WEATHER_CHANNEL_ID=YOUR_SLACK_CHANNEL_ID
- access http://YOUR_APP_NAME/weather
