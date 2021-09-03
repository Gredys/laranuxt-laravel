<?php

namespace App\Exceptions;

use App;
use Config;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Client as GuzzleClient;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (App::environment('production')) {
                $path = str_replace("/", "\\", $request->getPathInfo());
                $filePath = base_path() . '\\resources\\nuxt\\dist' . $path;

                if (is_dir($filePath) && !is_dir($filePath . "\\index.html"))
                    $filePath .= "\\index.html";

                if (file_exists($filePath)) {
                    return response(file_get_contents($filePath));
                } else {
                    return response()->json([
                        'error' => 'Path ' . $path . " not found"
                    ], 404);
                }
            } else {
                $path = $request->getPathInfo();

                $guzzleClient = new GuzzleClient();

                $url = "http://localhost:" . Config::get("app.nuxtPort") . $path;

                try {
                    if (($accept = $request->headers->get('Accept')) === 'text/event-stream') {
                        return Redirect::to($url);
                    } else {
                        $guzzleRequest = new GuzzleRequest($request->getMethod(), $url);
                        $guzzleResponse = $guzzleClient->send($guzzleRequest, [
                            'timeout' => 5,
                            'headers' => $request->headers->all()
                        ]);

                        return response($guzzleResponse->getBody()->getContents())
                            ->withHeaders($guzzleResponse->getHeaders());
                    }
                } catch (Exception $e) {
                    return response()->json([
                        'error' => [$e->getMessage(), $e->getTraceAsString()],
                        'headers' => $request->headers->all(),
                        'url' => $url
                    ], 404);
                }
            }
        });
    }
}
