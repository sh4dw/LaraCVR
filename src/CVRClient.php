<?php
namespace Sh4dw\Laracvr;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class CVRClient implements CVRClientInterface
{
    public static function request(array $query, string $requestType = 'POST', int $from = null, int $size = null)
    {
        //attributes to pass the query
        $queryAttributes = [];

        //validate query
        if (empty($query)) {
            throw new \InvalidArgumentException('The query is empty which is currently not supported');
        }
        //validate request type
        $_requestTypes = ['POST', 'GET'];
        if (!in_array(strtoupper($requestType), $_requestTypes, true)) {
            throw new \InvalidArgumentException('The request type is invalid');
        }
        //validate from (offset)
        if ($from !== null) {
            if ($from < 0) {
                throw new \InvalidArgumentException('The "from" offset should be unsigned or just null');
            } else {
                $queryAttributes['from'] = $from;
            }
        }
        //validate size
        if ($size !== null) {
            if ($size < 1) {
                throw new \InvalidArgumentException('The "size" should be larger than zero or just null');
            } else {
                $queryAttributes['size'] = $size;
            }
        }

        //instantiate a new GuzzleHttp Client with JSON headers
        $client = new Client([
            'headers' => [ 'Content-Type' => 'application/json' ]
        ]);

        //load a request with authentication from the config file
        $request = $client->request(
            $requestType,
            config('laracvr.cvr_api_path'),
            [
                'auth' =>  [config('laracvr.cvr_user'), config('laracvr.cvr_password')],
                'json' =>
                [
                    'from' => $from,
                    'size' => $size,
                    'query' => $query
                ]
            ]
        );

        // has content and is 200
        if ($request->hasHeader('Content-Length') && $request->getStatusCode() === 200) {
            $response = $request->getBody();
            $content = json_decode($response->getContents());
            $requestDetails = [
                'millis' => $content->took,
                'timedOut' => $content->timed_out,
                'totalHits' => $content->hits->total,
                'data' => null
            ];
            //has hits
            if ($content->hits->total > 0) {
                $extractedResults = [];
                foreach ($content->hits->hits as $hit) {
                    $newEntry = new \stdClass;
                    foreach ($hit->_source as $row) {
                        foreach ($row as $k => $v) {
                            $newEntry->$k = $v;
                        }
                    }
                    array_push($extractedResults, $newEntry);
                }
                $requestDetails['data'] = $extractedResults;
            }
            return $requestDetails;
        } else {
            $errContent = $request->hasHeader('Content-Length') ? 'Had content' : 'Had no content';
            $errHttpCode = $request->getStatusCode();
            throw new \UnexpectedValueException(
                "CVR data request $errContent and failed with HTTP status code: $errHttpCode"
            );
        }
    }
}
