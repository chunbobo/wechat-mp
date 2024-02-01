<?php

namespace app\common\service\wechat;


use EasyWeChat\Factory;
use EasyWeChat\OfficialAccount\Application;
use GuzzleHttp\Exception\GuzzleException;
use Overtrue\Socialite\Exceptions\AuthorizeFailedException;
use Overtrue\Socialite\User;
use support\Log;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class OfficialAccount
{
    static Application $app;

    /**
     * 获取网页授权链接
     * @param $redirect
     * @param $state
     * @return string
     */
    static function getOauthUrl($redirect, $state): string
    {
        return self::getApp()->oauth->scopes(['snsapi_base'])->withState($state)->redirect($redirect);
    }

    static function getApp(): Application
    {
        self::$app = Factory::officialAccount([
            'app_id' => env('OFFICIAL_APPID'),         // AppID
            'secret' => env('OFFICIAL_SECRET'),        // AppSecret
            'token' => env('OFFICIAL_TOKEN'),        // Token
            'aes_key' => env('OFFICIAL_AES_KEY'),      // EncodingAESKey，兼容与安全模式下请一定要填写！！！
            'response_type' => 'array',
            'log' => [
                'default' => env('APP_DEBUG') ? 'dev' : 'prod', // 默认使用的 channel，生产环境可以改为下面的 prod
                'channels' => [
                    // 测试环境
                    'dev' => [
                        'driver' => 'single',
                        'path' => runtime_path("logs/wechat-official-account" . date('Y-m-d') . ".log"),
                        'level' => 'debug',
                    ],
                    // 生产环境
                    'prod' => [
                        'driver' => 'daily',
                        'path' => runtime_path("logs/wechat-official-account" . date('Y-m-d') . ".log"),
                        'level' => 'info',
                    ],
                ],
            ],
        ]);
        return self::$app;
    }

    /**
     * @param $code
     * @return User
     * @throws GuzzleException
     * @throws AuthorizeFailedException
     */
    static function userFromCode($code): User
    {
        return self::getApp()->oauth->userFromCode($code);
    }

    // 发送消息
    static function sendMessage($openId, $message)
    {
        return self::getApp()->customer_service->message($message)->to($openId)->send();
    }

    // 上传临时图片素材
    static function uploadTempImage($path)
    {
        return self::getApp()->media->uploadImage($path);
    }

    public function handle($request)
    {
        try {
            self::getApp();
            $symfony_request = new SymfonyRequest($request->get(), $request->post(), [], $request->cookie(), [], [], $request->rawBody());
            $symfony_request->headers = new HeaderBag($request->header());
            self::$app->rebind('request', $symfony_request);
            self::$app->server->push(function ($message) {
                switch ($message['MsgType']) {
                    case 'event':
                        if ($message['Event'] == 'subscribe') {
                            return '欢迎使用筋斗云开店，快速生成微信小程序，<a href="https://wechat.1zh888.com/wechat/officialAccount/oauth">点击立即体验</a>';
                        }
                        break;
//                    case 'text':
//                        break;
//                    case 'image':
//                        break;
//                    case 'voice':
//                        break;
//                    case 'video':
//                        break;
//                    case 'location':
//                        break;
//                    case 'link':
//                        break;
//                    case 'file':
//                    // ... 其它消息
//                    default:
//                    break;
                }
                // ...
            });
            $response = self::$app->server->serve();
            return $response->getContent();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $e->getMessage();
        }
    }
}