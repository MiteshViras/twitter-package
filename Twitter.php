<?php

namespace App\Services;

use Exception;
use Throwable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use PhpParser\Node\Stmt\Switch_;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\ClientException;

class Twitter
{
    public static function tweet($access_token, $secret, $text)
    {
        $client = new Client();
        $time = time();
        $nonce = md5(mt_rand());
        $body = '{ "text": "' . $text . '" }';
        $signature = self::generateOauthSignature('post', 'https://api.twitter.com/2/tweets', env('TWITTER_CLIENT_ID'), $nonce, 'HMAC-SHA1', $time, '1.0', env('TWITTER_CLIENT_SECRET'), $secret, $access_token);
        $headers = [
            'Authorization' => 'OAuth oauth_consumer_key="' . env('TWITTER_CLIENT_ID') . '",oauth_token="' . $access_token . '",oauth_signature_method="HMAC-SHA1",oauth_timestamp="' . $time . '",oauth_nonce="' . $nonce . '",oauth_version="1.0",oauth_signature="' . $signature . '"',
            'Content-Type' => 'application/json',
        ];

        $request = new Request('POST', 'https://api.twitter.com/2/tweets', $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $data = json_decode($res->getBody());
        return $data->data;
    }

    public static function generateOauthSignature($method, $url, $consumerKey, $nonce, $signatureMethod, $timestamp, $version, $consumerSecret, $tokenSecret, $tokenValue, $extraParams = array())
    {
        $base = strtoupper($method) . "&" . rawurlencode($url) . "&"
            . rawurlencode("oauth_consumer_key=" . $consumerKey
                . "&oauth_nonce=" . $nonce
                . "&oauth_signature_method=" . $signatureMethod
                . "&oauth_timestamp=" . $timestamp
                . "&oauth_token=" . $tokenValue
                . "&oauth_version=" . $version);

        if (!empty($extraParams)) {
            $base .= rawurlencode("&" . http_build_query($extraParams));
        }

        $key = rawurlencode($consumerSecret) . '&' . rawurlencode($tokenSecret);
        $signature = base64_encode(hash_hmac('sha1', $base, $key, true));

        return rawurlencode($signature);
    }

    protected static function tweeterHeader($url, $method = 'get')
    {
        $method = strtolower($method);

        if (!in_array($method, ['get', 'post', 'put', 'delete'])) {
            throw new Exception('Method Should Be get,post,put or delete');
        }

        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . config('twitter.berer_token')
                ],
            ]);
            return json_decode($response->getBody()->getContents());
        } catch (ClientException $e) {
            switch ($e->getCode()) {
                case 404:
                    throw new Exception('Not Found');
                    break;
                case 429:
                    throw new Exception('Too Many Requests');
                    break;
                case 422:
                    throw new Exception('Unprocessable Entity');
                    break;
                case 500:
                    throw new Exception('Internal Server Error');
                    break;
                case 502:
                    throw new Exception('Bad Gateway');
                    break;
                case 503:
                    throw new Exception('Service Unavailable');
                    break;
                case 504:
                    throw new Exception('Gateway Timeout');
                    break;
                default:
                Log::info($e);
                    throw new Exception('Something went wrong');
            }
        }
    }

    public static function getTweetLikeBY($tweetId, $max_results = 100, $pagination_token = null)
    {
        $url = 'https://api.twitter.com/2/tweets/' . $tweetId . '/liking_users?max_results=' . $max_results . '&user.fields=public_metrics';
        if (!empty($pagination_token)) {
            $url .= '&pagination_token=' . $pagination_token;
        }
        return  Twitter::tweeterHeader($url);
    }

    public static function getRetweetBY($tweetId, $max_results = 100, $pagination_token = null)
    {
        $url = 'https://api.twitter.com/2/tweets/' . $tweetId . '/retweeted_by?max_results=' . $max_results . '&user.fields=public_metrics';
        if (!empty($pagination_token)) {
            $url .= '&pagination_token=' . $pagination_token;
        }
        return Twitter::tweeterHeader($url);
    }

    public static function getUserFollowBy($userId, $max_results = 100, $pagination_token = null)
    {
        $url = 'https://api.twitter.com/2/users/' . $userId . '/followers?max_results=' . $max_results;
        if (!empty($pagination_token)) {
            $url .= '&pagination_token=' . $pagination_token;
        }
        return Twitter::tweeterHeader($url);
    }

    public static function getUserFollowings($userId, $max_results = 100, $pagination_token = null)
    {
        $url = 'https://api.twitter.com/2/users/' . $userId . '/following?max_results=' . $max_results;
        if (!empty($pagination_token)) {
            $url .= '&pagination_token=' . $pagination_token;
        }
        return Twitter::tweeterHeader($url);
    }

    public static function getUsernameInfo($username)
    {
        $url = 'https://api.twitter.com/2/users/by/username/' . $username . '?user.fields=description,id,name,profile_image_url,public_metrics';
        return Twitter::tweeterHeader($url);
    }

    public static function getTweetReplies($tweetId, $max_results = 100, $pagination_token = null)
    {
        $url = 'https://api.twitter.com/2/tweets/search/recent?max_results=' . $max_results . '&query=in_reply_to_status_id:' . $tweetId .'&expansions=author_id&user.fields=id,name,username,public_metrics';
        if (!empty($pagination_token)) {
            $url .= '&pagination_token=' . $pagination_token;
        }
        return Twitter::tweeterHeader($url);
    }

    public static function getTweetStatistics($tweetId)
    {
        $url = 'https://api.twitter.com/2/tweets/' . $tweetId . '?tweet.fields=public_metrics';
        return Twitter::tweeterHeader($url);
    }
}
